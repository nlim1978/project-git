<?php

namespace App\Services;

use Config\Encryption;
use Config\Services;
use RuntimeException;
use Throwable;

class NotificationDeliveryService extends BaseService
{
    private const UNCONFIGURED_TELEGRAM_TOKEN = 'DISABLED_NOT_CONFIGURED';

    /** @param array<string,mixed> $document */
    public function afterRegistration(array $document, bool $sendEmail): void
    {
        if ($sendEmail) {
            $this->deliverRegistrationEmail($document);
        }

        $userId = trim((string) ($document['initial_responsible_user_id'] ?? ''));
        if ($userId !== '') {
            $this->deliverTelegram($document, null, $userId, 'Initial Assignment', 'notify_initial_assignment');
        }
    }

    public function afterRouting(string $documentId, string $routingId, ?string $destinationUserId, bool $isReassigned): void
    {
        if ($destinationUserId === null || $destinationUserId === '') {
            return;
        }

        $document = $this->db->table('documents d')
            ->select('d.document_id, d.receiving_number, d.document_control_number, d.subject, d.sender_name, d.current_section_id, s.section_name')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->where('d.document_id', $documentId)->get()->getRowArray();
        if ($document === null) {
            return;
        }

        $this->deliverTelegram(
            $document,
            $routingId,
            $destinationUserId,
            $isReassigned ? 'Reassignment' : 'Routing',
            $isReassigned ? 'notify_reassignment' : 'notify_routing'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function logsForDocument(string $documentId): array
    {
        return $this->db->table('notification_logs nl')
            ->select('nl.notification_id, nl.routing_id, nl.recipient_user_id, nl.recipient_address, nl.notification_channel, nl.notification_type, nl.status, nl.attempt_count, nl.sent_at, nl.error_message, nl.created_at')
            ->select('u.first_name AS recipient_first_name, u.last_name AS recipient_last_name')
            ->join('users u', 'u.user_id = nl.recipient_user_id', 'left')
            ->where('nl.document_id', $documentId)
            ->orderBy('nl.created_at', 'DESC')->get()->getResultArray();
    }

    /** @param array<string,mixed> $document */
    private function deliverRegistrationEmail(array $document): void
    {
        try {
            $settings = $this->db->table('email_settings')->orderBy('updated_at', 'DESC')->get(1)->getRowArray();
            if ($settings === null || (int) $settings['enabled'] !== 1) {
                return;
            }

            $recipient = trim((string) ($document['sender_email'] ?? ''));
            if ($recipient === '') {
                return;
            }
            $replacements = [
                '{{sender_name}}' => (string) ($document['sender_name'] ?? ''),
                '{{receiving_number}}' => (string) ($document['receiving_number'] ?? ''),
                '{{document_control_number}}' => (string) ($document['document_control_number'] ?? ''),
                '{{subject}}' => (string) ($document['subject'] ?? ''),
            ];
            $subject = strtr((string) $settings['subject_template'], $replacements);
            $message = strtr((string) $settings['body_template'], $replacements);

            $this->attemptDelivery([
                'document_id' => $document['document_id'], 'routing_id' => null, 'recipient_user_id' => null,
                'recipient_address' => $recipient, 'notification_channel' => 'Email',
                'notification_type' => 'Registration Confirmation',
            ], (int) $settings['retry_attempts'], function () use ($settings, $recipient, $subject, $message): void {
                $this->sendEmail($settings, $recipient, $subject, $message);
            });
        } catch (Throwable) {
            log_message('error', 'iDocTrack email notification processing failed after document commit.');
        }
    }

    /** @param array<string,mixed> $document */
    private function deliverTelegram(array $document, ?string $routingId, string $userId, string $type, string $eventSetting): void
    {
        try {
            $settings = $this->db->table('telegram_settings')->orderBy('updated_at', 'DESC')->get(1)->getRowArray();
            if ($settings === null || (int) $settings['enabled'] !== 1 || (int) ($settings[$eventSetting] ?? 0) !== 1) {
                return;
            }

            $user = $this->db->table('users')
                ->select('user_id, first_name, last_name, telegram_chat_id, telegram_notification_enabled')
                ->where('user_id', $userId)->where('account_status', 'Active')->get()->getRowArray();
            if ($user === null || (int) $user['telegram_notification_enabled'] !== 1) {
                return;
            }

            $chatId = trim((string) ($user['telegram_chat_id'] ?? ''));
            $log = [
                'document_id' => $document['document_id'], 'routing_id' => $routingId,
                'recipient_user_id' => $userId, 'recipient_address' => $chatId !== '' ? $chatId : null,
                'notification_channel' => 'Telegram', 'notification_type' => $type,
            ];
            if ($chatId === '') {
                $this->recordUnavailable($log, 'Telegram Chat ID is not configured for the assigned user.');
                return;
            }

            $recipientName = trim((string) ($user['first_name'] . ' ' . $user['last_name']));
            $message = "iDocTrack {$type}\n"
                . "For: {$recipientName}\n"
                . 'Document: ' . (string) ($document['document_control_number'] ?? '') . "\n"
                . 'Receiving No.: ' . (string) ($document['receiving_number'] ?? '') . "\n"
                . 'Subject: ' . (string) ($document['subject'] ?? '');
            if (($document['section_name'] ?? '') !== '') {
                $message .= "\nAssigned Section: " . (string) $document['section_name'];
            }

            $this->attemptDelivery($log, (int) $settings['retry_attempts'], function () use ($settings, $chatId, $message): void {
                $this->sendTelegram($settings, $chatId, $message);
            });
        } catch (Throwable) {
            log_message('error', 'iDocTrack Telegram notification processing failed after workflow commit.');
        }
    }

    /** @param array<string,mixed> $logData */
    private function attemptDelivery(array $logData, int $retryAttempts, callable $send): void
    {
        $notificationId = $this->uuidV4();
        $inserted = $this->db->table('notification_logs')->insert($logData + [
            'notification_id' => $notificationId, 'status' => 'Pending', 'attempt_count' => 0,
        ]);
        if (! $inserted) {
            log_message('error', 'iDocTrack notification was not sent because its delivery log could not be created.');
            return;
        }

        $maxAttempts = 1 + max(0, min(10, $retryAttempts));
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->db->table('notification_logs')->where('notification_id', $notificationId)->update(['attempt_count' => $attempt]);
                $send();
                $this->db->table('notification_logs')->where('notification_id', $notificationId)->update([
                    'status' => 'Sent', 'sent_at' => gmdate('Y-m-d H:i:s'), 'error_message' => null,
                ]);
                return;
            } catch (Throwable) {
                // Retry immediately according to the saved channel policy. Never propagate delivery
                // failures into the already-committed Receiving/Routing transaction.
            }
        }

        $this->db->table('notification_logs')->where('notification_id', $notificationId)->update([
            'status' => 'Failed', 'error_message' => 'Delivery failed after ' . $maxAttempts . ' attempt(s). Review the channel configuration and recipient settings.',
        ]);
    }

    /** @param array<string,mixed> $logData */
    private function recordUnavailable(array $logData, string $reason): void
    {
        $this->db->table('notification_logs')->insert($logData + [
            'notification_id' => $this->uuidV4(), 'status' => 'Failed', 'attempt_count' => 0,
            'error_message' => $reason,
        ]);
    }

    /** @param array<string,mixed> $settings */
    private function sendEmail(array $settings, string $recipient, string $subject, string $message): void
    {
        $crypto = match ((string) $settings['encryption_type']) {
            'STARTTLS' => 'tls', 'SSL/TLS' => 'ssl', default => '',
        };
        $email = Services::email([
            'protocol' => 'smtp', 'SMTPHost' => (string) $settings['smtp_server'],
            'SMTPPort' => (int) $settings['smtp_port'], 'SMTPUser' => (string) ($settings['smtp_username'] ?? ''),
            'SMTPPass' => $this->decryptSecret($settings['encrypted_password'] ?? null, 'SMTP password'),
            'SMTPCrypto' => $crypto, 'SMTPTimeout' => 8, 'mailType' => 'text', 'charset' => 'UTF-8',
            'CRLF' => "\r\n", 'newline' => "\r\n",
        ], false);
        $email->setFrom((string) $settings['sender_email'], (string) $settings['sender_name']);
        $email->setTo($recipient);
        $email->setSubject($subject);
        $email->setMessage($message);
        if (! $email->send(false)) {
            throw new RuntimeException('Email delivery failed.');
        }
    }

    /** @param array<string,mixed> $settings */
    private function sendTelegram(array $settings, string $chatId, string $message): void
    {
        $cipher = (string) ($settings['encrypted_bot_token'] ?? '');
        if ($cipher === '' || $cipher === self::UNCONFIGURED_TELEGRAM_TOKEN) {
            throw new RuntimeException('Telegram Bot Token is not configured.');
        }
        $token = $this->decryptSecret($cipher, 'Telegram Bot Token');
        try {
            $client = Services::curlrequest(['timeout' => 8, 'connect_timeout' => 8, 'http_errors' => false], null, null, false);
            $response = $client->post('https://api.telegram.org/bot' . $token . '/sendMessage', [
                'form_params' => ['chat_id' => $chatId, 'text' => $message],
            ]);
            $payload = json_decode((string) $response->getBody(), true);
        } catch (Throwable) {
            throw new RuntimeException('Telegram network delivery failed.');
        }
        if ($response->getStatusCode() !== 200 || ! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram rejected the notification.');
        }
    }

    private function decryptSecret(?string $cipherText, string $label): string
    {
        if ($cipherText === null || $cipherText === '') return '';
        if ((string) config(Encryption::class)->key === '') {
            throw new RuntimeException($label . ' cannot be decrypted because encryption.key is missing.');
        }
        $decoded = base64_decode($cipherText, true);
        if ($decoded === false) throw new RuntimeException($label . ' has an invalid encrypted format.');
        try {
            return (string) Services::encrypter()->decrypt($decoded);
        } catch (Throwable) {
            throw new RuntimeException($label . ' cannot be decrypted with the current encryption key.');
        }
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

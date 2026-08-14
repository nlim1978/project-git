<?php

namespace App\Services;

use Config\Encryption;
use Config\Services;
use RuntimeException;
use Throwable;

class EmailConfigurationService extends BaseService
{
    /** @return array<string, mixed> */
    public function settings(): array
    {
        $row = $this->rawSettings();
        $row['password_configured'] = ! empty($row['encrypted_password']);
        unset($row['encrypted_password']);
        $row['encryption_key_configured'] = $this->hasEncryptionKey();
        return $row;
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function save(array $input, string $actorId, array $meta): void
    {
        $old = $this->rawSettings();
        $data = [
            'smtp_server' => trim((string) $input['smtp_server']),
            'smtp_port' => (int) $input['smtp_port'],
            'encryption_type' => (string) $input['encryption_type'],
            'smtp_username' => trim((string) ($input['smtp_username'] ?? '')) ?: null,
            'sender_email' => trim((string) $input['sender_email']),
            'sender_name' => trim((string) $input['sender_name']),
            'subject_template' => trim((string) $input['subject_template']),
            'body_template' => trim((string) $input['body_template']),
            'retry_attempts' => (int) $input['retry_attempts'],
            'enabled' => ! empty($input['enabled']) ? 1 : 0,
            'updated_by' => $actorId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $password = (string) ($input['smtp_password'] ?? '');
        if (! empty($input['clear_password'])) {
            $data['encrypted_password'] = null;
        } elseif ($password !== '') {
            $data['encrypted_password'] = $this->encryptSecret($password);
        }

        $this->db->transBegin();
        try {
            if (! $this->db->table('email_settings')->where('email_setting_id', $old['email_setting_id'])->update($data)) {
                throw new RuntimeException('Email configuration could not be saved.');
            }
            $oldAudit = $this->auditSnapshot($old);
            $newAudit = $this->auditSnapshot($data + $old);
            $this->audit($actorId, 'UPDATE', 'Updated email configuration', $oldAudit, $newAudit, $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array{ip:string,browser:string} $meta */
    public function sendTest(string $actorId, array $meta): void
    {
        $settings = $this->rawSettings();
        $password = $this->decryptSecret($settings['encrypted_password'] ?? null);
        $crypto = match ((string) $settings['encryption_type']) {
            'STARTTLS' => 'tls',
            'SSL/TLS' => 'ssl',
            default => '',
        };

        $email = Services::email([
            'protocol' => 'smtp',
            'SMTPHost' => (string) $settings['smtp_server'],
            'SMTPPort' => (int) $settings['smtp_port'],
            'SMTPUser' => (string) ($settings['smtp_username'] ?? ''),
            'SMTPPass' => $password,
            'SMTPCrypto' => $crypto,
            'SMTPTimeout' => 8,
            'mailType' => 'text',
            'charset' => 'UTF-8',
            'CRLF' => "\r\n",
            'newline' => "\r\n",
        ], false);
        $email->setFrom((string) $settings['sender_email'], (string) $settings['sender_name']);
        $email->setTo((string) $settings['sender_email']);
        $email->setSubject('iDocTrack SMTP Test');
        $email->setMessage("This is an iDocTrack SMTP configuration test.\n\nIf you received this message, the saved SMTP settings successfully sent email from this server.");

        if (! $email->send(false)) {
            throw new RuntimeException('The SMTP test email could not be sent. Verify the server, port, encryption, username/password, and network access.');
        }
        $this->audit($actorId, 'TEST', 'Sent SMTP test email to configured sender address', null, ['recipient' => $settings['sender_email']], $meta);
    }

    /** @return array<string, mixed> */
    private function rawSettings(): array
    {
        $row = $this->db->table('email_settings')->orderBy('updated_at', 'DESC')->get(1)->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Email settings are missing. Run the admin/reference seeder once before configuring email.');
        }
        return $row;
    }

    private function hasEncryptionKey(): bool
    {
        return (string) config(Encryption::class)->key !== '';
    }

    private function encryptSecret(string $plainText): string
    {
        if (! $this->hasEncryptionKey()) {
            throw new RuntimeException('SMTP password was not saved because encryption.key is not configured. Run "php spark key:generate" once, then try again.');
        }
        return base64_encode(Services::encrypter()->encrypt($plainText));
    }

    private function decryptSecret(?string $cipherText): string
    {
        if ($cipherText === null || $cipherText === '') {
            return '';
        }
        if (! $this->hasEncryptionKey()) {
            throw new RuntimeException('The saved SMTP password cannot be decrypted because encryption.key is not configured.');
        }
        $decoded = base64_decode($cipherText, true);
        if ($decoded === false) {
            throw new RuntimeException('The saved SMTP password is not in the expected encrypted format. Save a new password.');
        }
        try {
            return Services::encrypter()->decrypt($decoded);
        } catch (Throwable) {
            throw new RuntimeException('The saved SMTP password could not be decrypted with the current encryption key.');
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function auditSnapshot(array $row): array
    {
        return [
            'smtp_server' => $row['smtp_server'] ?? null, 'smtp_port' => isset($row['smtp_port']) ? (int) $row['smtp_port'] : null,
            'encryption_type' => $row['encryption_type'] ?? null, 'smtp_username' => $row['smtp_username'] ?? null,
            'password_configured' => ! empty($row['encrypted_password']), 'sender_email' => $row['sender_email'] ?? null,
            'sender_name' => $row['sender_name'] ?? null, 'subject_template' => $row['subject_template'] ?? null,
            'body_template' => $row['body_template'] ?? null, 'retry_attempts' => isset($row['retry_attempts']) ? (int) $row['retry_attempts'] : null,
            'enabled' => isset($row['enabled']) ? (int) $row['enabled'] : 0,
        ];
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new @param array{ip:string,browser:string} $meta */
    private function audit(string $actorId, string $action, string $description, ?array $old, ?array $new, array $meta): void
    {
        if (! $this->db->table('audit_logs')->insert([
            'user_id' => $actorId, 'document_id' => null, 'module_name' => 'Email Configuration', 'action_name' => $action,
            'description' => $description,
            'old_value' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
            'ip_address' => $meta['ip'] !== '' ? $meta['ip'] : null,
            'browser' => $meta['browser'] !== '' ? $meta['browser'] : null,
        ])) {
            throw new RuntimeException('The email configuration audit record could not be saved.');
        }
    }
}

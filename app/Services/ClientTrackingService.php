<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ClientTrackingService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function available(): bool
    {
        try {
            return $this->db->fieldExists('client_tracking_token', 'documents');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function status(string $rawToken): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $token = self::normalizeToken($rawToken);
        $reference = self::normalizeReference($rawToken);
        if ($token === null && $reference === null) {
            return null;
        }
        if ($reference !== null && ! $this->db->fieldExists('client_tracking_reference', 'documents')) {
            return null;
        }

        $builder = $this->db->table('documents d')
            ->select('d.document_id, d.client_tracking_reference, d.subject, d.date_received, d.updated_at, d.status_id, dt.type_name, ds.status_code, ds.status_name, ds.is_terminal, s.section_name')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('sections s', 's.section_id = d.current_section_id');
        $token !== null
            ? $builder->where('d.client_tracking_token', $token)
            : $builder->where('d.client_tracking_reference', $reference);
        $document = $builder->get()->getRowArray();
        if ($document === null) {
            return null;
        }

        $routes = $this->db->table('routing_history rh')
            ->select('rh.routed_at, rh.is_reassigned, ra.action_name, ds.status_name, ds.is_terminal')
            ->join('routing_actions ra', 'ra.action_id = rh.action_id', 'left')
            ->join('document_statuses ds', 'ds.status_id = rh.resulting_status_id')
            ->where('rh.document_id', $document['document_id'])
            ->orderBy('rh.routed_at', 'ASC')
            ->get()->getResultArray();

        $engagement = null;
        if ($this->db->tableExists('document_engagements')) {
            $engagement = $this->db->table('document_engagements')
                ->selectMin('confirmed_at', 'confirmed_at')
                ->where('document_id', $document['document_id'])
                ->get()->getRowArray();
        }

        $timeline = [[
            'key' => 'received',
            'label' => 'Received',
            'detail' => 'Your document was received and registered.',
            'at' => (string) $document['date_received'],
        ]];

        $firstForwardedAt = null;
        $terminal = null;
        foreach ($routes as $route) {
            if ($firstForwardedAt === null && (int) $route['is_terminal'] === 0) {
                $firstForwardedAt = (string) $route['routed_at'];
            }
            if ((int) $route['is_terminal'] === 1) {
                $terminal = $route;
            }
        }
        if ($firstForwardedAt !== null) {
            $timeline[] = ['key' => 'forwarded', 'label' => 'Forwarded', 'detail' => 'Forwarded for processing.', 'at' => $firstForwardedAt];
        }
        if ($engagement !== null && ! empty($engagement['confirmed_at'])) {
            $timeline[] = ['key' => 'acknowledged', 'label' => 'Acknowledged', 'detail' => 'The assigned office acknowledged the document.', 'at' => (string) $engagement['confirmed_at']];
        }
        if ($terminal !== null) {
            $action = strtolower(trim((string) ($terminal['action_name'] ?? '')));
            $released = $action === 'released';
            $timeline[] = [
                'key' => $released ? 'released' : 'completed',
                'label' => $released ? 'Released' : 'Completed',
                'detail' => $released ? 'The document has been released.' : 'Processing has been completed.',
                'at' => (string) $terminal['routed_at'],
            ];
        }

        usort($timeline, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));
        $last = end($timeline);
        $status = $terminal !== null
            ? (strtolower(trim((string) ($terminal['action_name'] ?? ''))) === 'released' ? 'Released' : 'Completed')
            : (! empty($engagement['confirmed_at']) ? 'Processing' : ($firstForwardedAt !== null ? 'Forwarded for Processing' : 'Received'));

        return [
            'reference' => (string) $document['client_tracking_reference'],
            'document_type' => (string) $document['type_name'],
            'subject' => (string) $document['subject'],
            'date_received' => (string) $document['date_received'],
            'status' => $status,
            'current_section' => (string) $document['section_name'],
            'last_activity' => (string) ($last['at'] ?? $document['updated_at']),
            'timeline' => $timeline,
        ];
    }

    public function auditLookup(?string $documentId, bool $found, ?string $ipAddress, ?string $browser): void
    {
        if (! $this->db->tableExists('audit_logs')) {
            return;
        }
        try {
            $this->db->table('audit_logs')->insert([
                'user_id' => null,
                'document_id' => $documentId,
                'module_name' => 'Client Tracking',
                'action_name' => $found ? 'TRACK_FOUND' : 'TRACK_NOT_FOUND',
                'description' => $found ? 'Client tracking lookup matched a document.' : 'Client tracking lookup did not match a document.',
                'ip_address' => $ipAddress !== null ? mb_substr($ipAddress, 0, 45) : null,
                'browser' => $browser !== null ? mb_substr($browser, 0, 1000) : null,
            ]);
        } catch (\Throwable) {
            // Tracking availability must not depend on diagnostic logging.
        }
    }

    public static function normalizeToken(string $rawToken): ?string
    {
        $token = strtolower(trim($rawToken));
        if (str_starts_with($token, 'trk-')) {
            $token = substr($token, 4);
        }
        $token = str_replace('-', '', $token);
        return preg_match('/^[a-f0-9]{32}$/', $token) === 1 ? $token : null;
    }

    public static function normalizeReference(string $rawReference): ?string
    {
        $compact = strtoupper((string) preg_replace('/[\s-]+/', '', trim($rawReference)));
        if (str_starts_with($compact, 'TRK')) {
            $compact = substr($compact, 3);
        }
        if (preg_match('/^(0[1-9]|1[0-2])(\d{2})(\d{4})$/', $compact, $matches) !== 1) {
            return null;
        }
        return 'TRK-' . $matches[1] . $matches[2] . '-' . $matches[3];
    }

    public function documentIdForInput(string $rawInput): ?string
    {
        $token = self::normalizeToken($rawInput);
        $reference = self::normalizeReference($rawInput);
        if ($token === null && $reference === null) {
            return null;
        }
        if ($reference !== null && ! $this->db->fieldExists('client_tracking_reference', 'documents')) {
            return null;
        }
        $builder = $this->db->table('documents')->select('document_id');
        $token !== null
            ? $builder->where('client_tracking_token', $token)
            : $builder->where('client_tracking_reference', $reference);
        $id = $builder->get()->getRow('document_id');
        return $id === null ? null : (string) $id;
    }

    public static function displayToken(string $token): string
    {
        $normalized = self::normalizeToken($token);
        return $normalized === null ? '' : 'TRK-' . strtoupper(implode('-', str_split($normalized, 4)));
    }
}

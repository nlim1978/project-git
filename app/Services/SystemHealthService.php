<?php

namespace App\Services;

use Throwable;

class SystemHealthService extends BaseService
{
    /** @return array{ok: bool, app: string, database: string, driver: string} */
    public function check(): array
    {
        try {
            $this->db->query('SELECT 1 AS connection_test');

            return [
                'ok'       => true,
                'app'      => 'iDocTrack',
                'database' => 'connected',
                'driver'   => $this->db->DBDriver,
            ];
        } catch (Throwable) {
            return [
                'ok'       => false,
                'app'      => 'iDocTrack',
                'database' => 'unavailable',
                'driver'   => 'SQLSRV',
            ];
        }
    }
}

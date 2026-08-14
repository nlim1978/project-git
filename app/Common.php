<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('short_control_number')) {
    /**
     * Compact a generated control number for screen display without changing
     * the canonical value used by storage, search, QR, exports, or audit logs.
     */
    function short_control_number(?string $controlNumber): string
    {
        $value = trim((string) $controlNumber);
        if (preg_match('/^([A-Za-z0-9]+)-(\d{4})(\d{2})(\d{2})-([A-Za-z0-9]+)$/', $value, $matches) === 1) {
            $token = (string) $matches[5];
            return strtoupper((string) $matches[1]) . '-' . substr((string) $matches[2], -2)
                . (string) $matches[3] . (string) $matches[4] . '-' . substr($token, -4);
        }

        return $value;
    }
}

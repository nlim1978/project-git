<?php

namespace App\Policies;

/**
 * Stable names for the system roles that currently carry policy semantics.
 * Custom roles remain permission-driven and are not added here.
 */
final class SystemRole
{
    public const ADMINISTRATOR = 'Administrator';
    public const SUPER_ADMINISTRATOR = 'Super Administrator';
    public const DEPARTMENT_HEAD = 'Department Head';
    public const SECTION_HEAD = 'Section Head';

    public const GLOBAL_ADMINISTRATORS = [
        self::ADMINISTRATOR,
        self::SUPER_ADMINISTRATOR,
    ];

    private function __construct()
    {
    }
}

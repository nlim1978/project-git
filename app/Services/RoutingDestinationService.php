<?php

namespace App\Services;

use App\Models\RoutingModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * One source for both routing destination UI options and mutation validation.
 * Routing scope answers where work may be sent; it is deliberately distinct
 * from document visibility and receiving-management scope.
 */
final class RoutingDestinationService extends BaseService
{
    private RoutingModel $routing;
    private OrganizationScopeService $scope;

    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->routing = new RoutingModel();
        $this->scope = new OrganizationScopeService($this->db);
    }

    /** @return array{sections:array<int,array<string,mixed>>,section_users:array<int,array<string,mixed>>} */
    public function options(string $actorId): array
    {
        $global = $this->scope->isSuperAdmin($actorId);
        $officeId = $global ? null : $this->scope->requireOfficeId($actorId);
        $sections = $this->routing->activeSections($officeId);
        if (! $global && $this->scope->isDepartmentHead($actorId)) {
            $departmentSections = array_fill_keys(array_map('strtolower', $this->scope->departmentSectionIds($actorId)), true);
            $sections = array_values(array_filter(
                $sections,
                static fn (array $section): bool => isset($departmentSections[strtolower((string) $section['section_id'])])
            ));
        }
        $allowedSections = array_fill_keys(array_map(
            static fn (array $section): string => strtolower((string) $section['section_id']),
            $sections
        ), true);
        $sectionUsers = array_values(array_filter(
            $this->routing->activeSectionUsers($officeId),
            static fn (array $user): bool => isset($allowedSections[strtolower((string) $user['section_id'])])
        ));

        return ['sections' => $sections, 'section_users' => $sectionUsers];
    }

    public function assertAllowed(
        string $actorId,
        string $sectionId,
        ?string $userId = null,
        string $userError = 'The destination user must be active and assigned to the destination section.'
    ): void {
        if (! $this->scope->canAccessSection($actorId, $sectionId)) {
            throw new RuntimeException('The destination section is outside your office scope.');
        }

        $options = $this->options($actorId);
        $allowedSection = false;
        foreach ($options['sections'] as $section) {
            if (hash_equals(strtolower((string) $section['section_id']), strtolower($sectionId))) {
                $allowedSection = true;
                break;
            }
        }
        if (! $allowedSection) {
            throw new RuntimeException('Select an active destination section.');
        }

        $userId = trim((string) ($userId ?? ''));
        if ($userId === '') {
            return;
        }
        foreach ($options['section_users'] as $user) {
            if (hash_equals(strtolower((string) $user['section_id']), strtolower($sectionId))
                && hash_equals(strtolower((string) $user['user_id']), strtolower($userId))) {
                return;
            }
        }
        throw new RuntimeException($userError);
    }
}

<?php

namespace App\Services;

use App\Policies\DocumentDataScope;
use App\Policies\SystemRole;
use RuntimeException;

class OrganizationScopeService extends BaseService
{
    public function isDepartmentHead(string $userId): bool
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.role_id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.role_name', SystemRole::DEPARTMENT_HEAD)
            ->where('r.active', 1)
            ->countAllResults() > 0;
    }

    public function isSectionHead(string $userId): bool
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.role_id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.role_name', SystemRole::SECTION_HEAD)
            ->where('r.active', 1)
            ->countAllResults() > 0;
    }

    /** @return array<int, string> */
    public function managedSectionIds(string $userId): array
    {
        $membershipRows = [];
        if ($this->isSectionHead($userId)) {
            $membershipRows = $this->db->table('user_sections us')
                ->select('us.section_id')
                ->join('sections s', 's.section_id = us.section_id')
                ->where('us.user_id', $userId)
                ->where('s.active', 1)
                ->get()->getResultArray();
        }

        $explicitHeadRows = $this->db->table('sections')
            ->select('section_id')
            ->where('head_user_id', $userId)
            ->where('active', 1)
            ->get()->getResultArray();

        return array_values(array_unique(array_map(
            'strval',
            array_merge(array_column($membershipRows, 'section_id'), array_column($explicitHeadRows, 'section_id'))
        )));
    }

    public function departmentId(string $userId): ?string
    {
        $row = $this->db->table('user_sections us')
            ->select('s.department_id')
            ->join('sections s', 's.section_id = us.section_id')
            ->where('us.user_id', $userId)
            ->where('s.active', 1)
            ->orderBy('us.is_primary', 'DESC')
            ->orderBy('s.section_id', 'ASC')
            ->limit(1)->get()->getRowArray();

        return $row === null ? null : (string) $row['department_id'];
    }

    public function requireDepartmentId(string $userId): string
    {
        $departmentId = $this->departmentId($userId);
        if ($departmentId === null || $departmentId === '') {
            throw new RuntimeException('Your account is not assigned to a department.');
        }
        return $departmentId;
    }

    /** @return array<int, string> */
    public function departmentSectionIds(string $userId): array
    {
        if (! $this->isDepartmentHead($userId)) {
            return [];
        }

        $rows = $this->db->table('sections')
            ->select('section_id')
            ->where('department_id', $this->requireDepartmentId($userId))
            ->where('active', 1)
            ->get()->getResultArray();

        return array_values(array_unique(array_map('strval', array_column($rows, 'section_id'))));
    }

    /**
     * Resolve document-derived query visibility once, with explicit semantics
     * for global, office-wide, and section-restricted access.
     */
    public function documentDataScope(string $userId): DocumentDataScope
    {
        if ($this->isSuperAdmin($userId)) {
            return DocumentDataScope::global();
        }

        $officeId = $this->requireOfficeId($userId);
        if ($this->isDepartmentHead($userId)) {
            return DocumentDataScope::sections($officeId, $this->departmentSectionIds($userId));
        }
        $managedSectionIds = $this->managedSectionIds($userId);
        if ($this->isSectionHead($userId) || $managedSectionIds !== []) {
            return DocumentDataScope::sections($officeId, $managedSectionIds);
        }

        return DocumentDataScope::office($officeId);
    }

    /**
     * Whether a section is inside the actor's document-management scope.
     * Unlike canAccessSection(), this honors supervisor section restrictions
     * and is intended for receiving/administrative ownership decisions, not
     * routing destinations.
     */
    public function canManageDocumentsInSection(string $userId, string $sectionId): bool
    {
        $scope = $this->documentDataScope($userId);
        if ($scope->isGlobal()) {
            return true;
        }

        $sectionIds = $scope->sectionIds();
        if ($sectionIds !== null) {
            return in_array($sectionId, $sectionIds, true);
        }

        return $this->canAccessSection($userId, $sectionId);
    }

    public function isSuperAdmin(string $userId): bool
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.role_id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.active', 1)
            ->whereIn('r.role_name', SystemRole::GLOBAL_ADMINISTRATORS)
            ->countAllResults() > 0;
    }

    public function officeId(string $userId): ?string
    {
        $row = $this->db->table('user_sections us')
            ->select('d.office_id')
            ->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->where('us.user_id', $userId)
            ->orderBy('us.is_primary', 'DESC')
            ->orderBy('s.section_id', 'ASC')
            ->limit(1)->get()->getRowArray();

        return $row === null ? null : (string) $row['office_id'];
    }

    public function requireOfficeId(string $userId): string
    {
        $officeId = $this->officeId($userId);
        if ($officeId === null || $officeId === '') {
            throw new RuntimeException('Your account is not assigned to an office.');
        }
        return $officeId;
    }

    public function canAccessOffice(string $userId, string $officeId): bool
    {
        return $this->isSuperAdmin($userId) || hash_equals($this->requireOfficeId($userId), $officeId);
    }

    public function canAccessDepartment(string $userId, string $departmentId): bool
    {
        if ($this->isSuperAdmin($userId)) {
            return true;
        }
        if ($this->isDepartmentHead($userId)) {
            return hash_equals($this->requireDepartmentId($userId), $departmentId);
        }
        return $this->db->table('departments')
            ->where('department_id', $departmentId)
            ->where('office_id', $this->requireOfficeId($userId))
            ->countAllResults() === 1;
    }

    public function canAccessSection(string $userId, string $sectionId): bool
    {
        if ($this->isSuperAdmin($userId)) {
            return true;
        }
        $builder = $this->db->table('sections s')
            ->join('departments d', 'd.department_id = s.department_id')
            ->where('s.section_id', $sectionId)
            ->where('d.office_id', $this->requireOfficeId($userId));
        if ($this->isDepartmentHead($userId)) {
            $builder->where('s.department_id', $this->requireDepartmentId($userId));
        }
        return $builder->countAllResults() === 1;
    }

    public function canAccessDocument(string $userId, string $documentId): bool
    {
        if ($this->isSuperAdmin($userId)) {
            return true;
        }

        $scope = $this->documentDataScope($userId);
        $builder = $this->db->table('documents doc')
            ->join('sections s', 's.section_id = doc.current_section_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->where('doc.document_id', $documentId)
            ->where('d.office_id', $scope->officeId());

        $sectionIds = $scope->sectionIds();
        if ($sectionIds === []) {
            return false;
        }
        if ($sectionIds !== null) {
            $builder->whereIn('doc.current_section_id', $sectionIds);
        }

        return $builder->countAllResults() === 1;
    }

    public function canAccessUser(string $actorId, string $targetUserId): bool
    {
        if ($this->isSuperAdmin($actorId)) {
            return true;
        }
        if ($this->isSuperAdmin($targetUserId)) {
            return false;
        }
        $scope = $this->documentDataScope($actorId);
        $builder = $this->db->table('user_sections us')
            ->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->where('us.user_id', $targetUserId)
            ->where('d.office_id', $scope->officeId());

        $sectionIds = $scope->sectionIds();
        if ($sectionIds === []) {
            return false;
        }
        if ($sectionIds !== null) {
            $builder->whereIn('us.section_id', $sectionIds);
        }

        return $builder->countAllResults() > 0;
    }

    public function canManageSection(string $userId, string $sectionId): bool
    {
        if (! $this->canAccessSection($userId, $sectionId)) {
            return false;
        }
        if ($this->isDepartmentHead($userId)) {
            return $this->db->table('sections')
                ->where('section_id', $sectionId)
                ->where('department_id', $this->requireDepartmentId($userId))
                ->countAllResults() === 1;
        }
        if (! $this->isSectionHead($userId)) {
            return true;
        }
        return in_array($sectionId, $this->managedSectionIds($userId), true);
    }
}

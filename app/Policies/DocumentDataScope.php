<?php

namespace App\Policies;

/**
 * Explicit query scope for document-derived data.
 *
 * A null office means global access. A null section list means every section
 * inside the office. An empty section list means no sections and must fail
 * closed. This avoids the old ambiguous convention where [] sometimes meant
 * unrestricted access.
 */
final class DocumentDataScope
{
    /** @param list<string>|null $sectionIds */
    private function __construct(
        private readonly ?string $officeId,
        private readonly ?array $sectionIds,
    ) {
    }

    public static function global(): self
    {
        return new self(null, null);
    }

    public static function office(string $officeId): self
    {
        return new self($officeId, null);
    }

    /** @param list<string> $sectionIds */
    public static function sections(string $officeId, array $sectionIds): self
    {
        return new self($officeId, array_values(array_unique(array_map('strval', $sectionIds))));
    }

    public function officeId(): ?string
    {
        return $this->officeId;
    }

    /** @return list<string>|null */
    public function sectionIds(): ?array
    {
        return $this->sectionIds;
    }

    public function isGlobal(): bool
    {
        return $this->officeId === null;
    }

    public function restrictsSections(): bool
    {
        return $this->sectionIds !== null;
    }
}

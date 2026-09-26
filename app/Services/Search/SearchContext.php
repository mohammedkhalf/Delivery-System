<?php

namespace App\Services\Search;

class SearchContext
{
    public function __construct(
        public readonly string $panelPrefix,
        public readonly ?string $moduleType = null,
        public readonly int|string|null $moduleId = null,
        public readonly bool $inModuleWorkspace = true,
        public readonly int|string|null $storeId = null,
        public readonly int|string|null $vendorId = null,
    ) {}

    public function moduleTypeIs(string ...$types): bool
    {
        return in_array($this->moduleType, $types, true);
    }

    public function moduleTypeIsNot(string ...$types): bool
    {
        return ! $this->moduleTypeIs(...$types);
    }

    public function hasModuleId(): bool
    {
        return is_numeric($this->moduleId);
    }
}

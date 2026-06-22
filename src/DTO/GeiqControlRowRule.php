<?php

namespace App\DTO;

class GeiqControlRowRule
{
    /**
     * @param array<int, string> $includeCodes
     * @param array<int, string> $excludeCodes
     */
    public function __construct(
        private string $matchLabel,
        private array $includeCodes = [],
        private array $excludeCodes = [],
        private ?string $note = null,
        private ?string $valueField = null,
        private bool $skipTotalisation = false,
    ) {
    }

    public function getMatchLabel(): string
    {
        return $this->matchLabel;
    }

    /**
     * @return array<int, string>
     */
    public function getIncludeCodes(): array
    {
        return $this->includeCodes;
    }

    /**
     * @return array<int, string>
     */
    public function getExcludeCodes(): array
    {
        return $this->excludeCodes;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getValueField(): ?string
    {
        return $this->valueField;
    }

    public function isSkipTotalisation(): bool
    {
        return $this->skipTotalisation;
    }
}

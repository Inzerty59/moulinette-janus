<?php

namespace App\DTO;

class GeiqControlAnomalyDTO
{
    /**
     * @param array<int, string> $codes
     */
    public function __construct(
        private int $row,
        private string $label,
        private array $codes,
        private string $reason
    ) {
    }

    public function getRow(): int
    {
        return $this->row;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array<int, string>
     */
    public function getCodes(): array
    {
        return $this->codes;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

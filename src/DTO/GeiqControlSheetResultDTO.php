<?php

namespace App\DTO;

class GeiqControlSheetResultDTO
{
    /**
     * @param array<int, GeiqControlAnomalyDTO> $anomalies
     */
    public function __construct(
        private int $writtenCount,
        private array $anomalies
    ) {
    }

    public function getWrittenCount(): int
    {
        return $this->writtenCount;
    }

    /**
     * @return array<int, GeiqControlAnomalyDTO>
     */
    public function getAnomalies(): array
    {
        return $this->anomalies;
    }
}

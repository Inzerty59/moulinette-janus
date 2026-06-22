<?php

namespace App\DTO;

class GeiqParsedDataDTO
{
    /**
     * @param array<int, array<string, mixed>> $summaryLines
     * @param array<int, array<string, mixed>> $totalisationRows
     * @param array<int, array<string, mixed>> $dsnRows
     */
    public function __construct(
        private string $period,
        private string $establishment,
        private array $summaryLines,
        private array $totalisationRows,
        private array $dsnRows
    ) {
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function getEstablishment(): string
    {
        return $this->establishment;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSummaryLines(): array
    {
        return $this->summaryLines;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTotalisationRows(): array
    {
        return $this->totalisationRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDsnRows(): array
    {
        return $this->dsnRows;
    }
}

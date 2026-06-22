<?php

namespace App\DTO;

class GeiqRequestDTO
{
    public function __construct(
        private string $territory,
        private GeiqFilesDTO $files
    ) {
    }

    public function getTerritory(): string
    {
        return $this->territory;
    }

    public function getFiles(): GeiqFilesDTO
    {
        return $this->files;
    }
}

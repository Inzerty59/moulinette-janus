<?php

namespace App\DTO;

class MoulinetteRequestDTO
{
    public function __construct(
        private ?int $agencyId,
        private MoulinetteFilesDTO $files
    ) {}

    public function getAgencyId(): ?int
    {
        return $this->agencyId;
    }

    public function getFiles(): MoulinetteFilesDTO
    {
        return $this->files;
    }
}
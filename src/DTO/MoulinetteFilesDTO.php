<?php

namespace App\DTO;

class MoulinetteFilesDTO
{
    public function __construct(
        private \Symfony\Component\HttpFoundation\File\UploadedFile $cotisationFile,
        private \Symfony\Component\HttpFoundation\File\UploadedFile $matriculeFile,
        private \Symfony\Component\HttpFoundation\File\UploadedFile $rubriqueFile,
        private \Symfony\Component\HttpFoundation\File\UploadedFile $outputFile
    ) {}

    public function getCotisationFile(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->cotisationFile;
    }

    public function getMatriculeFile(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->matriculeFile;
    }

    public function getRubriqueFile(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->rubriqueFile;
    }

    public function getOutputFile(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->outputFile;
    }
}
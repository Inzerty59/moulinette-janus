<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class GeiqFilesDTO
{
    public function __construct(
        private UploadedFile $controlFile,
        private UploadedFile $dsnFile,
        private UploadedFile $totalisationFile
    ) {
    }

    public function getControlFile(): UploadedFile
    {
        return $this->controlFile;
    }

    public function getDsnFile(): UploadedFile
    {
        return $this->dsnFile;
    }

    public function getTotalisationFile(): UploadedFile
    {
        return $this->totalisationFile;
    }
}

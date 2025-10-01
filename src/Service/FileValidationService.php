<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileValidationService
{
    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
        'text/plain',
    ];

    private const REQUIRED_FILES = [
        'cotisation_file',
        'matricule_file',
        'rubrique_file',
        'output_file',
    ];

    public function validateUploadedFiles(array $files): array
    {
        $errors = [];

        foreach (self::REQUIRED_FILES as $fileKey) {
            if (!isset($files[$fileKey]) || !$files[$fileKey] instanceof UploadedFile) {
                $errors[] = "Le fichier {$fileKey} est manquant.";
                continue;
            }

            $file = $files[$fileKey];

            if (!$file->isValid()) {
                $errors[] = "Le fichier {$fileKey} est invalide : " . $file->getErrorMessage();
                continue;
            }

            if (!$this->isValidMimeType($file)) {
                $errors[] = "Le fichier {$fileKey} n'est pas un fichier Excel ou CSV valide.";
            }
        }

        return $errors;
    }

    private function isValidMimeType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        return in_array($mimeType, self::ALLOWED_MIME_TYPES, true);
    }

    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    public function getRequiredFiles(): array
    {
        return self::REQUIRED_FILES;
    }
}
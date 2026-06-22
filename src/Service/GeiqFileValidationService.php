<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class GeiqFileValidationService
{
    private const REQUIRED_FILES = [
        'control_file',
        'dsn_file',
        'totalisation_file',
    ];

    private const ALLOWED_MIME_TYPES = [
        'control_file' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ],
        'dsn_file' => [
            'application/pdf',
        ],
        'totalisation_file' => [
            'application/pdf',
        ],
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

            if ($file->getSize() > 20 * 1024 * 1024) {
                $errors[] = "Le fichier {$fileKey} est trop volumineux (maximum 20MB).";
                continue;
            }

            if ($file->getSize() === 0) {
                $errors[] = "Le fichier {$fileKey} est vide.";
                continue;
            }

            if (!$this->isValidMimeType($fileKey, $file)) {
                $errors[] = $this->getInvalidMimeTypeMessage($fileKey);
            }
        }

        return $errors;
    }

    private function isValidMimeType(string $fileKey, UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        return in_array($mimeType, self::ALLOWED_MIME_TYPES[$fileKey], true);
    }

    private function getInvalidMimeTypeMessage(string $fileKey): string
    {
        return match ($fileKey) {
            'control_file' => "Le fichier control_file doit être un fichier Excel valide.",
            'dsn_file', 'totalisation_file' => "Le fichier {$fileKey} doit être un PDF valide.",
            default => "Le fichier {$fileKey} n'a pas un format valide.",
        };
    }
}

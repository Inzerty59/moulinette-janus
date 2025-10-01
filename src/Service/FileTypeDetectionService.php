<?php

namespace App\Service;

class FileTypeDetectionService
{
    public function analyzeFileOrder(array $files): array
    {
        $suggestions = [];
        $errors = [];
        
        $mismatches = [];

        foreach ($files as $expectedType => $file) {
            if ($expectedType === 'output_file') continue;
            
            $filename = strtolower($file->getClientOriginalName());
            $actualType = $this->detectFileTypeFromName($filename);
            
            if ($actualType && $actualType !== $expectedType) {
                $mismatches[] = [
                    'expected' => $expectedType,
                    'actual' => $actualType,
                    'filename' => $file->getClientOriginalName()
                ];
            }
        }

        if (!empty($mismatches)) {
            $errors[] = "L'ordre des fichiers semble incorrect :";
            foreach ($mismatches as $mismatch) {
                $expectedLabel = $this->getFileTypeLabel($mismatch['expected']);
                $actualLabel = $this->getFileTypeLabel($mismatch['actual']);
                
                $errors[] = "• '{$mismatch['filename']}' semble être un fichier {$actualLabel} mais est placé dans le champ {$expectedLabel}";
            }
            
            $suggestions[] = "Vérifiez que vous avez sélectionné les bons fichiers dans les bons champs :";
            $suggestions[] = "• COT = Fichier de cotisations (doit contenir 'cot' dans le nom)";
            $suggestions[] = "• MAT = Fichier matricule (doit contenir 'mat' dans le nom)";
            $suggestions[] = "• RUB = Fichier rubriques (doit contenir 'rub' dans le nom)";
        }

        return [
            'suggestions' => $suggestions,
            'errors' => $errors
        ];
    }

    private function detectFileTypeFromName(string $filename): ?string
    {
        $filename = strtolower($filename);
        
        if (strpos($filename, 'cot') !== false) {
            return 'cotisation_file';
        }
        
        if (strpos($filename, 'mat') !== false) {
            return 'matricule_file';
        }
        
        if (strpos($filename, 'rub') !== false) {
            return 'rubrique_file';
        }
        
        return null;
    }

    private function getFileTypeLabel(string $fileType): string
    {
        $labels = [
            'cotisation_file' => 'cotisations',
            'matricule_file' => 'matricule',
            'rubrique_file' => 'rubriques'
        ];

        return $labels[$fileType] ?? $fileType;
    }
}
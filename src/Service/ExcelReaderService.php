<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class ExcelReaderService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function readAllRows(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $rowData = [];

                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                $rows[] = $rowData;
            }

            $this->logger->info('Fichier lu avec succès', [
                'filename' => $file->getClientOriginalName(),
                'rows_count' => count($rows)
            ]);

            return $rows;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la lecture du fichier', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Impossible de lire le fichier {$file->getClientOriginalName()}: " . $e->getMessage());
        }
    }

    public function loadSpreadsheet(UploadedFile $file): Spreadsheet
    {
        try {
            return IOFactory::load($file->getPathname());
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du chargement du spreadsheet', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Impossible de charger le fichier {$file->getClientOriginalName()}: " . $e->getMessage());
        }
    }

    public function hasColumnInHeader(array $rows, string $columnName): bool
    {
        if (empty($rows)) {
            return false;
        }

        $header = array_map('strtolower', $rows[0] ?? []);
        return in_array(strtolower($columnName), $header, true);
    }

    public function normalizeString(string $str): string
    {
        $str = strtolower($str);
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $str = preg_replace('/[^a-z0-9 ]/i', '', $str);
        $str = preg_replace('/[\s\p{Zs}]+/u', ' ', $str);
        return trim($str);
    }
}
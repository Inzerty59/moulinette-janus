<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class ExcelWriterService
{
    private LoggerInterface $logger;
    private ExcelReaderService $excelReader;

    public function __construct(LoggerInterface $logger, ExcelReaderService $excelReader)
    {
        $this->logger = $logger;
        $this->excelReader = $excelReader;
    }

    public function generateOutputFile(
        UploadedFile $outputTemplateFile,
        array $calculatedValues
    ): array {
        try {
            $outputSpreadsheet = $this->excelReader->loadSpreadsheet($outputTemplateFile);
            
            $outputSheet = $this->findTempoBancoSheet($outputSpreadsheet);
            
            if ($outputSheet === null) {
                $outputSheet = $outputSpreadsheet->getActiveSheet();
                $this->logger->info('Utilisation de la feuille active', [
                    'sheet_name' => $outputSheet->getTitle()
                ]);
            }
            
            $maxRow = $outputSheet->getHighestRow();
            $writtenCount = 0;

            for ($row = 1; $row <= $maxRow; $row++) {
                $result = $this->processRow($outputSheet, $row, $calculatedValues);
                if ($result) {
                    $writtenCount++;
                }
            }

            $response = $this->createDownloadResponse($outputSpreadsheet, $outputTemplateFile->getClientOriginalName());

            $this->logger->info('Fichier de sortie généré', [
                'original_filename' => $outputTemplateFile->getClientOriginalName(),
                'sheet_name' => $outputSheet->getTitle(),
                'values_written' => $writtenCount,
                'total_rows' => $maxRow
            ]);

            return [
                'response' => $response,
                'written_count' => $writtenCount
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du fichier de sortie', [
                'filename' => $outputTemplateFile->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Impossible de générer le fichier de sortie : " . $e->getMessage());
        }
    }

    private function findTempoBancoSheet(Spreadsheet $spreadsheet): ?Worksheet
    {
        $possibleNames = [
            'Tempo-Banco',
            'tempo-banco',
            'TEMPO-BANCO',
            'Tempo Banco',
            'tempo banco',
            'TEMPO BANCO',
            'TempoBanco',
            'tempobanco',
            'TEMPOBANCO',
        ];

        foreach ($possibleNames as $name) {
            try {
                $sheet = $spreadsheet->getSheetByName($name);
                if ($sheet !== null) {
                    $this->logger->info('Onglet Tempo-Banco trouvé', [
                        'sheet_name' => $name
                    ]);
                    return $sheet;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            $normalizedSheetName = $this->normalizeSheetName($sheetName);
            
            if ($normalizedSheetName === 'tempobanco') {
                $this->logger->info('Onglet Tempo-Banco trouvé par normalisation', [
                    'original_name' => $sheetName
                ]);
                return $sheet;
            }
        }

        $this->logger->warning('Onglet Tempo-Banco non trouvé, utilisation de la feuille active');
        return null;
    }

    private function normalizeSheetName(string $name): string
    {
        return strtolower(str_replace(['-', ' ', '_'], '', $name));
    }

    private function processRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $outputSheet, int $row, array $calculatedValues): bool
    {
        $cellValueA = $this->getCellValue($outputSheet, 1, $row);
        $cellValueB = $this->getCellValue($outputSheet, 2, $row);

        if (empty($cellValueA)) {
            return false;
        }

        $cellValueComplete = trim($cellValueA . ' ' . $cellValueB);
        $rubricInfo = $this->parseRubricFromCell($cellValueComplete, $cellValueA);

        if (!$rubricInfo) {
            return false;
        }

        $compositeKey = $rubricInfo['code'] . '_' . $rubricInfo['category'];
        
        if (isset($calculatedValues[$compositeKey])) {
            $value = $this->prepareValueForExcel($calculatedValues[$compositeKey]);
            $outputSheet->getCell([3, $row])->setValue($value);
            
            $this->logger->debug('Valeur écrite dans le fichier', [
                'row' => $row,
                'composite_key' => $compositeKey,
                'value' => $value
            ]);
            
            return true;
        }

        return false;
    }

    private function getCellValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $outputSheet, int $column, int $row): string
    {
        try {
            $value = $outputSheet->getCell([$column, $row])->getValue();
            return trim((string) $value);
        } catch (\Exception $e) {
            return '';
        }
    }

    private function parseRubricFromCell(string $cellValueComplete, string $cellValueA): ?array
    {
        $cellValueCompleteLower = strtolower($cellValueComplete);

        $totalMappings = [
            'brut total' => ['code' => 'total', 'category' => 'brut'],
            'brut tranche a' => ['code' => 'total', 'category' => 'tranche a'],
            'brut tranche b' => ['code' => 'total', 'category' => 'tranche b'],
            'heures travaillées' => ['code' => 'total', 'category' => 'heures travaillees'],
            'net à payer' => ['code' => 'total', 'category' => 'net a payer'],
            'net a payer' => ['code' => 'total', 'category' => 'net a payer'],
        ];

        foreach ($totalMappings as $pattern => $mapping) {
            if (stripos($cellValueCompleteLower, $pattern) !== false) {
                return $mapping;
            }
        }

        if (stripos($cellValueCompleteLower, 'agence') !== false && stripos($cellValueCompleteLower, 'patronal') !== false) {
            return ['code' => 'agence', 'category' => 'patronal montant'];
        }

        if (stripos($cellValueCompleteLower, 'total') !== false) {
            if (stripos($cellValueCompleteLower, 'brut') !== false && stripos($cellValueCompleteLower, 'payer') !== false) {
                return ['code' => 'total', 'category' => 'brut_a_payer'];
            } elseif (stripos($cellValueCompleteLower, 'fiscal') !== false) {
                return ['code' => 'total', 'category' => 'fiscal'];
            } elseif (stripos($cellValueCompleteLower, 'net') !== false) {
                return ['code' => 'total', 'category' => 'net_a_payer'];
            } else {
                return ['code' => 'total', 'category' => 'a payer'];
            }
        }

        $matches = [];
        if (preg_match('/^(\d+)\s*(.*?)$/', $cellValueA, $matches)) {
            $code = $matches[1];
            $textAfterCode = trim($matches[2]);
            $category = $this->determineCategoryFromText($textAfterCode);
            
            return ['code' => $code, 'category' => $category];
        }

        return null;
    }

    private function determineCategoryFromText(string $text): string
    {
        $textLower = strtolower($text);

        if (stripos($textLower, 'patronal montant') !== false) {
            return 'patronal montant';
        } elseif (stripos($textLower, 'salarié montant') !== false || stripos($textLower, 'salarie montant') !== false) {
            return 'salarie montant';
        } elseif (stripos($textLower, 'à payer') !== false || stripos($textLower, 'a payer') !== false) {
            return 'a payer';
        } elseif (stripos($textLower, 'à retenir') !== false || stripos($textLower, 'a retenir') !== false) {
            return 'a retenir';
        } else {
            return 'base';
        }
    }

    private function prepareValueForExcel(string $value): float|string
    {
        $numericValue = str_replace([' ', ','], ['', '.'], $value);
        
        if (is_numeric($numericValue)) {

            return floatval($numericValue);
        }
        
        return $value;
    }

    private function createDownloadResponse(Spreadsheet $spreadsheet, string $originalFileName): Response
    {
        try {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $tempFile = tempnam(sys_get_temp_dir(), 'output_') . '.xlsx';
            $writer->save($tempFile);

            $content = file_get_contents($tempFile);
            unlink($tempFile);

            if ($content === false) {
                throw new \Exception("Impossible de lire le fichier temporaire");
            }

            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $originalFileName . '"');

            return $response;

        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la création de la réponse de téléchargement : " . $e->getMessage());
        }
    }
}
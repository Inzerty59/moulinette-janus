<?php

namespace App\Service;

use App\Entity\Agency;
use App\Entity\AgencyRubric;
use Psr\Log\LoggerInterface;

class RubricProcessingService
{
    private const RUBRIQUE_PAR_ZERO = ['1175', '1201', '4072', '4076', '5101', '5103'];

    private LoggerInterface $logger;
    private ExcelReaderService $excelReader;

    public function __construct(LoggerInterface $logger, ExcelReaderService $excelReader)
    {
        $this->logger = $logger;
        $this->excelReader = $excelReader;
    }

    public function processRubrics(
        array $cotisationRows,
        array $rubriqueRows,
        array $matriculeRows,
        array $agencyRubrics
    ): array {
        $valeursParCode = [];
        $rubriqueHeader = isset($rubriqueRows[0]) ? array_map('strtolower', $rubriqueRows[0]) : [];
        $isJALCOT = in_array('mont_base', $rubriqueHeader);

        foreach ($agencyRubrics as $rubric) {
            $code = trim($rubric->getCode() ?? '');
            $category = trim($rubric->getCategory() ?? '');
            $name = trim($rubric->getName() ?? '');
            
            if (empty($code)) {
                $this->logger->warning('Rubrique ignorée car sans code', [
                    'rubric_id' => $rubric->getId(),
                    'category' => $category,
                    'name' => $name
                ]);
                continue;
            }
            
            $result = $this->processRubric(
                $code,
                $category,
                $name,
                $cotisationRows,
                $rubriqueRows,
                $matriculeRows,
                $isJALCOT
            );

            $compositeKey = $this->generateCompositeKey($code, $category, $name);
            $valeursParCode[$compositeKey] = $result['value'];

            $this->logger->info('Rubrique traitée', [
                'code' => $code,
                'category' => $category,
                'value' => $result['value'],
                'detail' => $result['detail']
            ]);
        }

        return $valeursParCode;
    }

    private function processRubric(
        string $code,
        string $category,
        string $name,
        array $cotisationRows,
        array $rubriqueRows,
        array $matriculeRows,
        bool $isJALCOT
    ): array {
        $categoryNormalized = $this->excelReader->normalizeString($category);

        if ($code === 'Agence' && $categoryNormalized === 'patronal montant') {
            return $this->calculateTotalCharges($cotisationRows);
        }

        $sources = $this->configureSources($cotisationRows, $matriculeRows, $rubriqueRows, $categoryNormalized);

        $result = $this->searchInSources($code, $categoryNormalized, $name, $sources);

        if (!$result['found']) {
            return $this->applyDefaultRules($code, $category);
        }

        return $result;
    }

    private function calculateTotalCharges(array $cotisationRows): array
    {
        $totalCharges = 0;
        foreach ($cotisationRows as $rowIdx => $row) {
            if ($rowIdx === 0) continue;
            
            if (isset($row[8]) && is_numeric($row[8])) {
                $totalCharges += floatval($row[8]);
            }
        }

        return [
            'value' => number_format(abs($totalCharges), 2, ',', ' '),
            'detail' => 'Somme calculée des charges patronales du fichier cotisation'
        ];
    }

    private function configureSources(
        array $cotisationRows,
        array $matriculeRows,
        array $rubriqueRows,
        string $categoryNormalized
    ): array {
        $baseSources = [
            [
                'rows' => $cotisationRows,
                'type' => 'cotisation',
                'colMapping' => [
                    'base' => 4,
                    'salarie montant' => 6,
                    'patronal montant' => 8,
                    'total' => 10,
                ]
            ],
            [
                'rows' => $matriculeRows,
                'type' => 'matricule',
                'colMapping' => [
                    'brut' => 4,
                    'brut total' => 4,
                    'tranche a' => 12,
                    'tranche b' => 13,
                    'heures travaillees' => 10,
                    'net a payer' => 9,
                ]
            ],
            [
                'rows' => $rubriqueRows,
                'type' => 'rubrique',
                'colMapping' => [
                    'base' => 6,
                    'a payer' => 7,
                    'a retenir' => 8,
                    'salarie montant' => 7,
                    'patronal montant' => 8,
                    'total' => 11,
                ]
            ],
        ];

        return $this->orderSourcesByPriority($baseSources, $categoryNormalized);
    }

    private function orderSourcesByPriority(array $sources, string $categoryNormalized): array
    {
        $orderedSources = [];

        switch ($categoryNormalized) {
            case 'patronal montant':
                $orderedSources = $this->prioritizeSources($sources, ['cotisation']);
                break;
            case 'salarie montant':
                $orderedSources = $this->prioritizeSources($sources, ['rubrique', 'cotisation', 'matricule']);
                break;
            case 'a payer':
            case 'a retenir':
                $orderedSources = $this->prioritizeSources($sources, ['rubrique']);
                break;
            case 'base':
                $orderedSources = $this->prioritizeSources($sources, ['cotisation', 'rubrique', 'matricule']);
                break;
            default:
                $orderedSources = $sources;
        }

        return $orderedSources;
    }

    private function prioritizeSources(array $sources, array $priority): array
    {
        $ordered = [];
        
        foreach ($priority as $type) {
            foreach ($sources as $source) {
                if ($source['type'] === $type) {
                    $ordered[] = $source;
                }
            }
        }

        foreach ($sources as $source) {
            if (!in_array($source['type'], $priority)) {
                $ordered[] = $source;
            }
        }

        return $ordered;
    }

    private function searchInSources(string $code, string $categoryNormalized, string $name, array $sources): array
    {
        foreach ($sources as $source) {
            $result = $this->searchInSource($code, $categoryNormalized, $name, $source);
            if ($result['found']) {
                return $result;
            }
        }

        return ['value' => '', 'detail' => '', 'found' => false];
    }

    private function searchInSource(string $code, string $categoryNormalized, string $name, array $source): array
    {
        $rows = $source['rows'];
        $sourceType = $source['type'];
        $colMapping = $source['colMapping'];

        $columnsToTry = $this->getColumnsToTry($categoryNormalized, $sourceType, $colMapping);
        
        if (empty($columnsToTry)) {
            return ['value' => '', 'detail' => '', 'found' => false];
        }

        $matchCount = 0;
        foreach ($rows as $rowIdx => $row) {
            if ($rowIdx === 0) continue;

            $searchMatch = $this->isRowMatch($code, $name, $row, $categoryNormalized, $sourceType);
            
            if ($searchMatch) {
                $matchCount++;
                $result = $this->extractValueFromRow($code, $row, $columnsToTry, $matchCount, $categoryNormalized, $sourceType);
                
                if ($result['found']) {
                    return $result;
                }
            }
        }

        return ['value' => '', 'detail' => '', 'found' => false];
    }

    private function getColumnsToTry(string $categoryNormalized, string $sourceType, array $colMapping): array
    {
        $columnsToTry = [];

        switch ($categoryNormalized) {
            case 'base':
                if ($sourceType === 'cotisation' && isset($colMapping['base'])) {
                    $columnsToTry[] = $colMapping['base'];
                } elseif ($sourceType === 'rubrique' && isset($colMapping['base'])) {
                    $columnsToTry[] = $colMapping['base'];
                }
                break;
            case 'patronal montant':
                if ($sourceType === 'cotisation' && isset($colMapping['patronal montant'])) {
                    $columnsToTry[] = $colMapping['patronal montant'];
                }
                break;
            case 'salarie montant':
                if ($sourceType === 'rubrique' && isset($colMapping['a retenir'])) {
                    $columnsToTry[] = $colMapping['a retenir'];
                } elseif ($sourceType === 'cotisation' && isset($colMapping['salarie montant'])) {
                    $columnsToTry[] = $colMapping['salarie montant'];
                }
                break;
            case 'a payer':
                if ($sourceType === 'rubrique' && isset($colMapping['a payer'])) {
                    $columnsToTry[] = $colMapping['a payer'];
                }
                break;
            case 'a retenir':
                if ($sourceType === 'rubrique' && isset($colMapping['a retenir'])) {
                    $columnsToTry[] = $colMapping['a retenir'];
                }
                break;
        }

        if (empty($columnsToTry) && isset($colMapping[$categoryNormalized])) {
            $columnsToTry[] = $colMapping[$categoryNormalized];
        }

        return $columnsToTry;
    }

    private function isRowMatch(string $code, string $name, array $row, string $categoryNormalized, string $sourceType): bool
    {
        if ($code === 'total' && $categoryNormalized === 'a payer' && $sourceType === 'rubrique') {
            return $this->isTotalMatch($row, $name);
        }

        if (isset($row[1]) && $this->excelReader->normalizeString($row[1]) === $this->excelReader->normalizeString($code)) {
            return true;
        }

        return false;
    }

    private function isTotalMatch(array $row, string $name): bool
    {
        if (!isset($row[2])) {
            return false;
        }

        $rowName = strtolower($row[2]);
        $searchName = strtolower($name);

        if (stripos($rowName, 'brut') !== false && stripos($searchName, 'brut') !== false) {
            return true;
        }
        
        if (stripos($rowName, 'fiscal') !== false && stripos($searchName, 'fiscal') !== false) {
            return true;
        }
        
        if (stripos($rowName, 'net') !== false && stripos($searchName, 'net') !== false) {
            if (stripos($rowName, 'net (1)') === false && stripos($rowName, 'net    ***') !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractValueFromRow(
        string $code,
        array $row,
        array $columnsToTry,
        int $matchCount,
        string $categoryNormalized,
        string $sourceType
    ): array {
        $shouldUseMatch = ($code === '3031' && $matchCount === 2) || ($code !== '3031' && $matchCount === 1);
        
        if (!$shouldUseMatch) {
            return ['value' => '', 'detail' => '', 'found' => false];
        }

        foreach ($columnsToTry as $colIndex) {
            if (isset($row[$colIndex]) && $row[$colIndex] !== '' && $row[$colIndex] !== null) {
                $value = $this->formatValue($row[$colIndex]);
                $detail = $this->generateDetail($code, $categoryNormalized, $sourceType, $colIndex, $matchCount);
                
                return [
                    'value' => $value,
                    'detail' => $detail,
                    'found' => true
                ];
            }
        }

        $detail = $this->generateEmptyDetail($code, $categoryNormalized, $sourceType, $matchCount);
        return [
            'value' => '0',
            'detail' => $detail,
            'found' => true
        ];
    }

    private function applyDefaultRules(string $code, string $category): array
    {
        if (in_array($code, self::RUBRIQUE_PAR_ZERO)) {
            return [
                'value' => '0',
                'detail' => "Rubrique définie par défaut à 0 ({$code}, {$category})"
            ];
        }

        return [
            'value' => '0',
            'detail' => "Code rubrique non trouvé ou montant absent dans les fichiers ({$code}, {$category}), valeur mise à 0"
        ];
    }

    private function generateCompositeKey(string $code, string $category, string $name): string
    {
        $categoryNormalized = $this->excelReader->normalizeString($category);
        
        if ($code === 'total' && $categoryNormalized === 'a payer') {
            $nameNormalized = strtolower($name);
            
            if (stripos($nameNormalized, 'brut') !== false) {
                return 'total_brut_a_payer';
            } elseif (stripos($nameNormalized, 'fiscal') !== false) {
                return 'total_fiscal';
            } elseif (stripos($nameNormalized, 'net') !== false) {
                return 'total_net_a_payer';
            }
        }

        return $code . '_' . $categoryNormalized;
    }

    private function formatValue($value): string
    {
        if (is_numeric($value)) {
            return number_format(abs(floatval($value)), 2, ',', ' ');
        }
        
        return (string) $value;
    }

    private function generateDetail(string $code, string $category, string $sourceType, int $columnIndex, int $matchCount): string
    {
        $occurrenceText = $code === '3031' && $matchCount === 2 ? ' (2ème occurrence)' : '';
        return "Correspondance sur code ({$code}) et catégorie ({$category}) dans le fichier {$sourceType}{$occurrenceText} en colonne montant : {$columnIndex}";
    }

    private function generateEmptyDetail(string $code, string $category, string $sourceType, int $matchCount): string
    {
        $occurrenceText = $code === '3031' && $matchCount === 2 ? ' (2ème occurrence)' : '';
        return "Rubrique trouvée mais toutes colonnes vides, valeur mise à 0 ({$code}, {$category}) dans le fichier {$sourceType}{$occurrenceText}";
    }
}
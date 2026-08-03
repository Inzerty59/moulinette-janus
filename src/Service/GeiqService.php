<?php

namespace App\Service;

use App\DTO\GeiqRequestDTO;
use App\DTO\GeiqControlRowRule;
use App\DTO\GeiqControlAnomalyDTO;
use App\DTO\GeiqControlSheetResultDTO;
use App\DTO\GeiqParsedDataDTO;
use App\DTO\GeiqResultDTO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class GeiqService
{
    public function __construct(
        private readonly GeiqPdfParsingService $pdfParser,
        private readonly LoggerInterface $logger
    ) {
    }

    public function processGeiq(GeiqRequestDTO $request): GeiqResultDTO
    {
        try {
            $config = $this->loadConfig($request->getTerritory());
            $controlSpreadsheet = IOFactory::load($request->getFiles()->getControlFile()->getPathname());
            $dsnPages = $this->pdfParser->extractPages($request->getFiles()->getDsnFile()->getPathname());
            $totalisationPages = $this->pdfParser->extractPages($request->getFiles()->getTotalisationFile()->getPathname());

            if (empty($dsnPages)) {
                return GeiqResultDTO::error('Le PDF DSN ne contient pas de pages exploitables.');
            }

            if (empty($totalisationPages)) {
                return GeiqResultDTO::error('Le PDF de totalisation ne contient pas de pages exploitables.');
            }

            $parsedData = $this->buildParsedData($dsnPages, $totalisationPages, $config);

            $controlSheetResult = $this->fillControlSheet(
                $controlSpreadsheet,
                $parsedData->getPeriod(),
                $parsedData->getSummaryLines(),
                $parsedData->getTotalisationRows(),
                $parsedData->getDsnRows(),
                $config
            );
            $this->writeGeiqSummarySheet(
                $controlSpreadsheet,
                $request->getTerritory(),
                $parsedData->getPeriod(),
                $parsedData->getEstablishment(),
                $parsedData->getSummaryLines(),
                $parsedData->getTotalisationRows(),
                $controlSheetResult->getAnomalies()
            );

            $response = $this->createDownloadResponse(
                $controlSpreadsheet,
                $request->getFiles()->getControlFile()->getClientOriginalName()
            );

            $this->logger->info('GEIQ traité avec succès', [
                'territory' => $request->getTerritory(),
                'summary_lines' => count($parsedData->getSummaryLines()),
                'totalisation_rows' => count($parsedData->getTotalisationRows()),
                'control_cells_written' => $controlSheetResult->getWrittenCount(),
                'control_anomalies' => count($controlSheetResult->getAnomalies()),
            ]);

            return GeiqResultDTO::success($response, $controlSheetResult->getWrittenCount());
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors du traitement GEIQ', [
                'error' => $e->getMessage(),
            ]);

            return GeiqResultDTO::error('Impossible de traiter le dossier GEIQ : ' . $e->getMessage());
        }
    }

    private function buildParsedData(array $dsnPages, array $totalisationPages, array $config): GeiqParsedDataDTO
    {
        $dsnHeaderText = (string) ($dsnPages[0]['raw'] ?? '');
        $period = $this->pdfParser->extractPeriod($dsnHeaderText);
        if ($period === null) {
            throw new \RuntimeException("La période DSN n'a pas été détectée.");
        }

        $establishment = $this->pdfParser->extractEstablishmentFromPages(
            $dsnPages,
            $config['expected_establishment'] ?? null,
            $config['establishment_patterns'] ?? []
        );
        if ($establishment === null) {
            throw new \RuntimeException("L'établissement DSN n'a pas été détecté.");
        }

        return new GeiqParsedDataDTO(
            $period,
            $establishment,
            $this->pdfParser->extractSummaryLines($dsnPages),
            $this->pdfParser->extractTotalisationRows($totalisationPages),
            $this->pdfParser->extractStructuredRows($dsnPages, true)
        );
    }

    private function writeGeiqSummarySheet(
        Spreadsheet $spreadsheet,
        string $territory,
        string $period,
        string $establishment,
        array $summaryLines,
        array $totalisationRows,
        array $anomalies
    ): void {
        $sheetName = 'GEIQ Synthèse';
        $existingSheet = $spreadsheet->getSheetByName($sheetName);
        if ($existingSheet !== null) {
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($existingSheet));
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);

        $sheet->setCellValue('A1', 'GEIQ - Synthèse de contrôle');
        $sheet->setCellValue('A2', 'Territoire');
        $sheet->setCellValue('B2', strtoupper($territory));
        $sheet->setCellValue('A3', 'Période détectée');
        $sheet->setCellValue('B3', $period);
        $sheet->setCellValue('A4', 'Établissement');
        $sheet->setCellValue('B4', $establishment);
        $sheet->setCellValue('A6', 'DSN - dernière page');

        $row = 7;
        $sheet->setCellValue("A{$row}", 'Ligne');
        $sheet->setCellValue("B{$row}", 'Texte');
        $row++;
        foreach ($summaryLines as $line) {
            $sheet->setCellValue("A{$row}", round((float) $line['y'], 1));
            $sheet->setCellValue("B{$row}", $line['text']);
            $row++;
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Totalisation par services');
        $row++;
        $headers = ['Page', 'Code', 'Libellé', 'Base', 'Montant', 'Part patronale', 'Ligne brute'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->cellAddress($index + 1, $row), $header);
        }
        $row++;

        foreach ($totalisationRows as $dataRow) {
            $sheet->setCellValue($this->cellAddress(1, $row), $dataRow['page']);
            $sheet->setCellValue($this->cellAddress(2, $row), $dataRow['code']);
            $sheet->setCellValue($this->cellAddress(3, $row), $dataRow['label']);
            $sheet->setCellValue($this->cellAddress(4, $row), $dataRow['base']);
            $sheet->setCellValue($this->cellAddress(5, $row), $dataRow['montant']);
            $sheet->setCellValue($this->cellAddress(6, $row), $dataRow['part_patronale'] ?? '');
            $sheet->setCellValue($this->cellAddress(7, $row), $dataRow['raw']);
            $row++;
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Anomalies');
        $row++;
        $anomalyHeaders = ['Ligne', 'Libellé', 'Codes', 'Raison'];
        foreach ($anomalyHeaders as $index => $header) {
            $sheet->setCellValue($this->cellAddress($index + 1, $row), $header);
        }
        $row++;
        foreach ($anomalies as $anomaly) {
            $sheet->setCellValue($this->cellAddress(1, $row), $anomaly->getRow());
            $sheet->setCellValue($this->cellAddress(2, $row), $anomaly->getLabel());
            $sheet->setCellValue($this->cellAddress(3, $row), implode(', ', $anomaly->getCodes()));
            $sheet->setCellValue($this->cellAddress(4, $row), $anomaly->getReason());
            $row++;
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function fillControlSheet(
        Spreadsheet $spreadsheet,
        string $period,
        array $summaryLines,
        array $totalisationRows,
        array $dsnRows,
        array $config
    ): GeiqControlSheetResultDTO {
        $sheet = $spreadsheet->getSheet(0);
        if (!$sheet instanceof Worksheet) {
            return new GeiqControlSheetResultDTO(0, []);
        }

        $monthName = $this->extractMonthNameFromPeriod($period);
        if ($monthName === null) {
            return new GeiqControlSheetResultDTO(0, []);
        }

        $monthColumnStart = $this->findMonthColumnStart($sheet, $monthName);
        if ($monthColumnStart === null) {
            return new GeiqControlSheetResultDTO(0, []);
        }

        $controlRowRules = $this->buildControlRowRules($config['control_row_rules'] ?? []);
        $totLookups = [
            'auto'           => $this->indexStructuredRowsByCode($totalisationRows),
            'base'           => $this->indexStructuredRowsByCode($totalisationRows, 'base'),
            'montant'        => $this->indexStructuredRowsByCode($totalisationRows, 'montant'),
            'part_patronale' => $this->indexStructuredRowsByCode($totalisationRows, 'part_patronale'),
        ];
        $dsnLookup = $this->indexStructuredRowsByCode($dsnRows);

        $maxRow = $sheet->getHighestRow();
        $written = 0;
        $anomalies = [];

        for ($row = 1; $row <= $maxRow; $row++) {
            $codeCell = trim((string) $sheet->getCell([3, $row])->getValue());
            if ($codeCell === '') {
                continue;
            }

            $label = trim((string) $sheet->getCell([2, $row])->getValue());
            $labelNormalized = $this->normalizeText($label);
            $codes = $this->resolveControlRowCodes($labelNormalized, $codeCell, $controlRowRules);
            if (empty($codes)) {
                $anomalies[] = new GeiqControlAnomalyDTO(
                    $row,
                    $label,
                    $this->extractCodesFromText($codeCell),
                    'Aucune règle de contrôle ne correspond à cette ligne.'
                );
                continue;
            }

            $matchingRule = $this->findMatchingRule($labelNormalized, $controlRowRules);

            if ($matchingRule !== null && $matchingRule->isSkipTotalisation()) {
                $totalisationValue = null;
                $totalisationSkipped = true;
            } else {
                $totalisationSkipped = false;
                $valueField = $matchingRule?->getValueField();
                $totLookup = $valueField !== null ? ($totLookups[$valueField] ?? $totLookups['auto']) : $totLookups['auto'];
                $totalisationValue = $this->resolveSheetValue($codes, $labelNormalized, $totLookup);
            }

            $dsnValue = $this->resolveSheetValue($codes, $labelNormalized, $dsnLookup);

            if ($dsnValue === null && $totalisationValue !== null && !$totalisationSkipped) {
                $dsnValue = $totalisationValue;
            }

            if ($totalisationValue === null && $dsnValue === null && !$totalisationSkipped) {
                $anomalies[] = new GeiqControlAnomalyDTO(
                    $row,
                    $label,
                    $codes,
                    'Aucune valeur retrouvée dans la totalisation ni dans la DSN.'
                );
                continue;
            }

            if ($totalisationValue !== null) {
                $sheet->setCellValue($this->cellAddress($monthColumnStart, $row), $totalisationValue);
                $written++;
            }

            if ($dsnValue !== null) {
                $sheet->setCellValue($this->cellAddress($monthColumnStart + 1, $row), $dsnValue);
                $written++;
            }

            if (!$totalisationSkipped && $totalisationValue === null) {
                $anomalies[] = new GeiqControlAnomalyDTO(
                    $row,
                    $label,
                    $codes,
                    'Valeur totalisation manquante.'
                );
            }
        }

        return new GeiqControlSheetResultDTO($written, $anomalies);
    }

    /**
     * @param array<int, GeiqControlRowRule> $controlRowRules
     */
    private function resolveControlRowCodes(string $labelNormalized, string $codeCell, array $controlRowRules): array
    {
        $codes = $this->extractCodesFromText($codeCell);

        foreach ($controlRowRules as $rule) {
            $matchLabel = $this->normalizeText($rule->getMatchLabel());
            if ($matchLabel === '' || !str_contains($labelNormalized, $matchLabel)) {
                continue;
            }

            if (!empty($rule->getIncludeCodes())) {
                $codes = array_map(static fn($code): string => (string) $code, $rule->getIncludeCodes());
            }

            if (!empty($rule->getExcludeCodes())) {
                $exclude = array_map(static fn($code): string => (string) $code, $rule->getExcludeCodes());
                $codes = array_values(array_filter(
                    $codes,
                    static fn(string $code): bool => !in_array($code, $exclude, true)
                ));
            }

            break;
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, GeiqControlRowRule>
     */
    private function buildControlRowRules(array $rules): array
    {
        $builtRules = [];

        foreach ($rules as $rule) {
            $builtRules[] = new GeiqControlRowRule(
                (string) ($rule['match_label'] ?? ''),
                array_map(static fn($code): string => (string) $code, $rule['include_codes'] ?? []),
                array_map(static fn($code): string => (string) $code, $rule['exclude_codes'] ?? []),
                isset($rule['note']) ? (string) $rule['note'] : null,
                isset($rule['value_field']) ? (string) $rule['value_field'] : null,
                (bool) ($rule['skip_totalisation'] ?? false),
            );
        }

        return $builtRules;
    }

    private function findMatchingRule(string $labelNormalized, array $controlRowRules): ?GeiqControlRowRule
    {
        foreach ($controlRowRules as $rule) {
            $matchLabel = $this->normalizeText($rule->getMatchLabel());
            if ($matchLabel !== '' && str_contains($labelNormalized, $matchLabel)) {
                return $rule;
            }
        }
        return null;
    }

    private function indexStructuredRowsByCode(array $rows, ?string $field = null): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $value = $field !== null
                ? $this->pickFieldValue($row, $field)
                : $this->pickStructuredValue($row);
            $label = $this->normalizeText((string) ($row['label'] ?? ''));

            if (!isset($indexed[$code])) {
                $indexed[$code] = [
                    'value' => 0.0,
                    'rows' => [],
                ];
            }

            $indexed[$code]['value'] += $value ?? 0.0;
            $indexed[$code]['rows'][] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $indexed;
    }

    private function pickFieldValue(array $row, string $field): ?float
    {
        $v = $row[$field] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        return $this->normalizeNumericValue((string) $v);
    }

    private function resolveSheetValue(array $codes, string $labelNormalized, array $lookup): ?float
    {
        $matches = [];

        foreach ($codes as $code) {
            if (isset($lookup[$code])) {
                $matches[] = (float) $lookup[$code]['value'];
            }
        }

        if (!empty($matches)) {
            return array_sum($matches);
        }

        foreach ($lookup as $entry) {
            foreach ($entry['rows'] as $row) {
                $rowLabel = $row['label'];
                if ($rowLabel === '') {
                    continue;
                }

                if ($labelNormalized !== '' && (
                    str_contains($rowLabel, $labelNormalized) ||
                    str_contains($labelNormalized, $rowLabel)
                )) {
                    return (float) $entry['value'];
                }
            }
        }

        return null;
    }

    private function pickStructuredValue(array $row): ?float
    {
        // Prefer the first positive value across fields; negative values are cotisation charges,
        // not the assiette (base) we need for control sheet comparisons.
        $fallback = null;
        foreach (['montant', 'base', 'part_patronale'] as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }

            $numeric = $this->normalizeNumericValue((string) $row[$field]);
            if ($numeric === null) {
                continue;
            }

            if ($numeric >= 0.0) {
                return $numeric;
            }

            if ($fallback === null) {
                $fallback = $numeric;
            }
        }

        return $fallback;
    }

    private function normalizeNumericValue(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xc2\xa0", "\xe2\x80\xaf", ' '], '', $value);
        $value = str_replace(',', '.', $value);

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function extractCodesFromText(string $text): array
    {
        preg_match_all('/\d{3,4}/', $text, $matches);
        return $matches[0] ?? [];
    }

    private function extractMonthNameFromPeriod(string $period): ?string
    {
        if (!preg_match('/(\d{2})\/\d{4}/', $period, $matches)) {
            return null;
        }

        $month = (int) $matches[1];
        $months = [
            1 => 'JANVIER',
            2 => 'FEVRIER',
            3 => 'MARS',
            4 => 'AVRIL',
            5 => 'MAI',
            6 => 'JUIN',
            7 => 'JUILLET',
            8 => 'AOUT',
            9 => 'SEPTEMBRE',
            10 => 'OCTOBRE',
            11 => 'NOVEMBRE',
            12 => 'DECEMBRE',
        ];

        return $months[$month] ?? null;
    }

    private function findMonthColumnStart(Worksheet $sheet, string $monthName): ?int
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $cellValue = trim((string) $sheet->getCell([$column, 2])->getValue());
            if ($cellValue === '') {
                continue;
            }

            if ($this->normalizeText($cellValue) === $this->normalizeText($monthName)) {
                return $column;
            }
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $value = strtolower($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $value = preg_replace('/[^a-z0-9]+/i', '', $value);

        return $value ?? '';
    }

    private function loadConfig(string $territory): array
    {
        $configFile = __DIR__ . '/../../config/geiq/' . $territory . '.php';
        if (!is_file($configFile)) {
            return [];
        }

        $config = require $configFile;
        return is_array($config) ? $config : [];
    }

    private function cellAddress(int $column, int $row): string
    {
        return Coordinate::stringFromColumnIndex($column) . $row;
    }

    private function createDownloadResponse(Spreadsheet $spreadsheet, string $originalFileName): Response
    {
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'geiq_') . '.xlsx';
        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le fichier temporaire GEIQ.');
        }

        $downloadName = preg_replace('/\.(xlsx|xls)$/i', '', $originalFileName) . '_GEIQ.xlsx';

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $downloadName . '"');

        return $response;
    }
}

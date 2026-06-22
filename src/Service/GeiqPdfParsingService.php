<?php

namespace App\Service;

class GeiqPdfParsingService
{
    public function extractPages(string $pdfPath): array
    {
        $content = file_get_contents($pdfPath);
        if ($content === false) {
            throw new \RuntimeException('Impossible de lire le PDF.');
        }

        preg_match_all('/<<(.*?)>>\s*stream\r?\n(.*?)endstream/s', $content, $matches, PREG_SET_ORDER);

        $pages = [];
        $pageNumber = 0;

        foreach ($matches as $match) {
            if (stripos($match[1], 'FlateDecode') === false) {
                continue;
            }

            $decoded = $this->decodeStream($match[2]);
            if ($decoded === null) {
                continue;
            }

            $pageNumber++;
            $fragments = $this->extractFragments($decoded);
            $pages[] = [
                'page' => $pageNumber,
                'raw' => $decoded,
                'fragments' => $fragments,
                'lines' => $this->groupFragmentsByLine($fragments),
            ];
        }

        return $pages;
    }

    public function extractPeriod(string $pdfText): ?string
    {
        if (preg_match('/P.*?riode\s+de\s+(\d{2}\/\d{4})(?:\s+[aà]\s+(\d{2}\/\d{4}))?/is', $pdfText, $matches)) {
            if (!empty($matches[2])) {
                return $matches[1] . ' - ' . $matches[2];
            }

            return $matches[1];
        }

        $normalizedText = $this->normalizeText($pdfText);

        if (preg_match('/periode\s+de\s+(\d{2}\/\d{4})(?:\s+a\s+(\d{2}\/\d{4}))?/i', $normalizedText, $matches)) {
            if (!empty($matches[2])) {
                return $matches[1] . ' - ' . $matches[2];
            }

            return $matches[1];
        }

        if (preg_match('/periode\s+de\s+(\d{2}\/\d{4})/i', $normalizedText, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function extractEstablishmentFromPages(array $pages, ?string $expectedEstablishment = null, array $patterns = []): ?string
    {
        if (empty($pages)) {
            return null;
        }

        $firstPage = $pages[0];
        foreach ($firstPage['lines'] ?? [] as $line) {
            $text = (string) ($line['text'] ?? '');
            if ($this->looksLikeExpectedEstablishment($text, $expectedEstablishment, $patterns)) {
                return $expectedEstablishment;
            }
        }

        $rawText = (string) ($firstPage['raw'] ?? '');
        if ($this->looksLikeExpectedEstablishment($rawText, $expectedEstablishment, $patterns)) {
            return $expectedEstablishment;
        }

        return null;
    }

    /**
     * @param array<int, string> $patterns
     */
    private function looksLikeExpectedEstablishment(string $text, ?string $expectedEstablishment, array $patterns): bool
    {
        $normalizedText = $this->normalizeText($text);

        foreach ($patterns as $pattern) {
            $normalizedPattern = $this->normalizeText(trim($pattern));
            if ($normalizedPattern !== '' && str_contains($normalizedText, $normalizedPattern)) {
                return true;
            }
        }

        if ($expectedEstablishment !== null) {
            $expectedNormalized = $this->normalizeText($expectedEstablishment);
            if ($expectedNormalized !== '' && str_contains($normalizedText, $expectedNormalized)) {
                return true;
            }
        }

        return str_contains(strtolower($text), 'geiq') && str_contains(strtolower($text), 'activit');
    }

    public function extractSummaryLines(array $pages): array
    {
        if (empty($pages)) {
            return [];
        }

        $lastPage = $pages[count($pages) - 1];
        return $lastPage['lines'];
    }

    public function extractTotalisationRows(array $pages): array
    {
        $all = $this->extractStructuredRows($pages);
        // The same summary row often appears on multiple PDF pages; deduplicate by raw text
        // to avoid counting identical rows twice. Rows with same code but different values
        // (different services) are kept because their raw texts differ.
        $seen = [];
        $result = [];
        foreach ($all as $row) {
            $key = $row['raw'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $row;
        }
        return $result;
    }

    public function extractStructuredRows(array $pages, bool $lastPageOnly = false): array
    {
        $sourcePages = $pages;
        if ($lastPageOnly && !empty($pages)) {
            $sourcePages = [end($pages)];
        }

        $rows = [];

        foreach ($sourcePages as $page) {
            foreach ($page['lines'] as $line) {
                $row = $this->parseTotalisationRow($line, $page['page']);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    public function extractSummaryText(array $pages): string
    {
        $lines = $this->extractSummaryLines($pages);
        return implode("\n", array_map(static fn(array $line): string => $line['text'], $lines));
    }

    private function decodeStream(string $streamData): ?string
    {
        $streamData = trim($streamData, "\r\n");

        $decoded = @gzuncompress($streamData);
        if ($decoded !== false) {
            return $decoded;
        }

        $decoded = @gzinflate($streamData);
        if ($decoded !== false) {
            return $decoded;
        }

        return null;
    }

    private function extractFragments(string $streamText): array
    {
        preg_match_all(
            '/(?:(?:[-+]?\d+(?:\.\d+)?)\s+){4}([-\d.]+)\s+([-\d.]+)\s+Tm\s+\((.*?)\)\s+Tj/s',
            $streamText,
            $matches,
            PREG_SET_ORDER
        );

        $fragments = [];
        foreach ($matches as $match) {
            $fragments[] = [
                'x' => (float) $match[1],
                'y' => (float) $match[2],
                'text' => $this->decodePdfText($match[3]),
            ];
        }

        return $fragments;
    }

    private function groupFragmentsByLine(array $fragments): array
    {
        $lines = [];

        usort($fragments, static function (array $a, array $b): int {
            return ($b['y'] <=> $a['y']) ?: ($a['x'] <=> $b['x']);
        });

        foreach ($fragments as $fragment) {
            $key = (string) round($fragment['y'], 1);
            if (!isset($lines[$key])) {
                $lines[$key] = [
                    'y' => $fragment['y'],
                    'parts' => [],
                ];
            }

            $lines[$key]['parts'][] = $fragment;
        }

        $normalized = [];
        foreach ($lines as $line) {
            usort($line['parts'], static fn(array $a, array $b): int => $a['x'] <=> $b['x']);
            $text = trim(implode(' ', array_map(static fn(array $part): string => $part['text'], $line['parts'])));
            if ($text === '') {
                continue;
            }

            $normalized[] = [
                'y' => $line['y'],
                'text' => preg_replace('/\s+/', ' ', $text),
            ];
        }

        usort($normalized, static fn(array $a, array $b): int => $b['y'] <=> $a['y']);

        return $normalized;
    }

    private function decodePdfText(string $text): string
    {
        $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
        $text = preg_replace_callback('/\\\\([nrtbf])/u', static function (array $matches): string {
            return match ($matches[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0C",
            };
        }, $text);

        return trim((string) $text);
    }

    private function parseTotalisationRow(array $line, int $pageNumber): ?array
    {
        $text = $line['text'];

        if (!preg_match('/^\d{3,4}\b/', $text)) {
            return null;
        }

        if (str_contains(strtolower($text), 'service m') || str_contains(strtolower($text), 'total etablissement')) {
            return null;
        }

        preg_match('/^(?<code>\d{3,4})\s+(?<rest>.*)$/', $text, $matches);
        if (!$matches) {
            return null;
        }

        $code = $matches['code'];
        $rest = trim($matches['rest']);

        // In PDF layout the label fragment (x≈55) appears before numeric columns (x≈280+),
        // so strip any leading non-numeric text before attempting number extraction.
        $rest = (string) preg_replace('/^[^\d-]+/', '', $rest);
        $rest = trim($rest);

        if ($rest === '') {
            return null;
        }

        // Extract all numbers, handling French thousands separator ("4 576.47" = 4576.47).
        // preg_match_all avoids backtracking issues: each match is greedy and independent.
        preg_match_all('/-?\s*\d+(?:\s\d{3})*(?:[.,]\d{1,2})?/', $rest, $numMatches);
        $numbers = $numMatches[0];

        if (empty($numbers)) {
            return null;
        }

        $base = null;
        $montant = null;
        $partPatronale = null;

        if (count($numbers) >= 3) {
            $base = $this->normalizeAmount($numbers[0]);
            $montant = $this->normalizeAmount($numbers[1]);
            $partPatronale = $this->normalizeAmount($numbers[2]);
        } elseif (count($numbers) === 2) {
            $base = $this->normalizeAmount($numbers[0]);
            $montant = $this->normalizeAmount($numbers[1]);
        } else {
            $montant = $this->normalizeAmount($numbers[0]);
        }

        return [
            'page' => $pageNumber,
            'code' => $code,
            'label' => '',
            'base' => $base,
            'montant' => $montant,
            'part_patronale' => $partPatronale,
            'raw' => $text,
        ];
    }

    private function normalizeAmount(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\xc2\xa0", "\xe2\x80\xaf", ' '], '', $value);
        $value = str_replace(',', '.', $value);
        return $value;
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $value);
        $converted = @iconv('Windows-1252', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted === false || $converted === '') {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        }

        if ($converted === false || $converted === '') {
            $converted = $value;
        }

        return strtolower((string) $converted);
    }
}

<?php

namespace App\Services\Spreadsheet;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class XlsxReader
{
    /**
     * @return array<int, string>
     */
    public function sheetNames(string $path): array
    {
        $archive = $this->openArchive($path);

        try {
            return array_keys($this->sheetTargets($archive));
        } finally {
            $archive->close();
        }
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public function rows(string $path, string $sheetName): array
    {
        $archive = $this->openArchive($path);

        try {
            $targets = $this->sheetTargets($archive);
            $target = $targets[$sheetName] ?? null;

            if ($target === null) {
                throw new RuntimeException("Worksheet [{$sheetName}] was not found in [{$path}].");
            }

            $worksheetXml = $archive->getFromName($target);
            if ($worksheetXml === false) {
                throw new RuntimeException("Unable to read worksheet [{$sheetName}] from [{$path}].");
            }

            return $this->parseWorksheet($worksheetXml, $this->sharedStrings($archive));
        } finally {
            $archive->close();
        }
    }

    public function uses1904Dates(string $path): bool
    {
        $archive = $this->openArchive($path);
        try {
            $xpath = $this->xpathDocument($archive->getFromName('xl/workbook.xml'));
            $value = $xpath->query('//main:workbookPr')?->item(0)?->attributes?->getNamedItem('date1904')?->nodeValue;
            return in_array(strtolower($value ?? ''), ['1', 'true'], true);
        } finally {
            $archive->close();
        }
    }

    private function resolveTarget(string $target): string
    {
        $parts = [];
        foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target) as $part) {
            if ($part === '..') { array_pop($parts); }
            elseif ($part !== '' && $part !== '.') { $parts[] = $part; }
        }
        $resolved = implode('/', $parts);
        if (! str_starts_with($resolved, 'xl/')) { throw new RuntimeException('Invalid worksheet target.'); }
        return $resolved;
    }

    private function openArchive(string $path): ZipArchive
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException("Unable to open spreadsheet [{$path}].");
        }

        return $archive;
    }

    /**
     * @return array<string, string>
     */
    private function sheetTargets(ZipArchive $archive): array
    {
        $workbook = $this->xpathDocument($archive->getFromName('xl/workbook.xml'));
        $relations = $this->xpathDocument($archive->getFromName('xl/_rels/workbook.xml.rels'));

        $targetByRelationId = [];
        foreach ($relations->query('//rel:Relationship') ?: [] as $relationship) {
            $targetByRelationId[$relationship->attributes->getNamedItem('Id')->nodeValue] =
                $this->resolveTarget($relationship->attributes->getNamedItem('Target')->nodeValue);
        }

        $targets = [];
        foreach ($workbook->query('//main:sheets/main:sheet') ?: [] as $sheet) {
            $sheetName = $sheet->attributes->getNamedItem('name')->nodeValue;
            $relationId = $sheet->attributes->getNamedItemNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            )?->nodeValue;

            if ($relationId === null || ! isset($targetByRelationId[$relationId])) {
                continue;
            }

            $targets[$sheetName] = $targetByRelationId[$relationId];
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $xpath = $this->xpathDocument($xml);
        $strings = [];

        foreach ($xpath->query('//main:si') ?: [] as $item) {
            $parts = [];
            foreach ($xpath->query('.//main:t', $item) ?: [] as $textNode) {
                $parts[] = $textNode->textContent;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string|null>>
     */
    private function parseWorksheet(string $xml, array $sharedStrings): array
    {
        $xpath = $this->xpathDocument($xml);
        $rows = [];

        foreach ($xpath->query('//main:sheetData/main:row') ?: [] as $rowNode) {
            $row = [];

            foreach ($xpath->query('./main:c', $rowNode) ?: [] as $cellNode) {
                $reference = $cellNode->attributes->getNamedItem('r')?->nodeValue ?? '';
                $index = $this->columnIndex($reference);

                while (count($row) < $index) {
                    $row[] = null;
                }

                $row[] = $this->cellValue($xpath, $cellNode, $sharedStrings);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function xpathDocument(string|false $xml): DOMXPath
    {
        if ($xml === false) {
            throw new RuntimeException('Required spreadsheet XML payload is missing.');
        }

        $document = new DOMDocument;
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('Spreadsheet XML declarations are not supported.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET)) { throw new RuntimeException('Invalid spreadsheet XML.'); }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        return $xpath;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(DOMXPath $xpath, \DOMNode $cellNode, array $sharedStrings): ?string
    {
        $type = $cellNode->attributes->getNamedItem('t')?->nodeValue;

        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($xpath->query('.//main:t', $cellNode) ?: [] as $textNode) {
                $parts[] = $textNode->textContent;
            }

            return $parts === [] ? null : implode('', $parts);
        }

        $value = $xpath->query('./main:v', $cellNode)?->item(0)?->textContent;
        if ($value === null) {
            return null;
        }

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? $value;
        }

        return $value;
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/i', '', $reference) ?? '';
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }
}

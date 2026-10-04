<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Dependency-free reader for .xlsx and .csv files. Returns a plain 2D array of
 * rows (each row an array of string cell values). For .xlsx it parses the
 * OOXML package directly via ZipArchive + SimpleXML (shared strings + the first
 * worksheet); no external package is required.
 */
class SpreadsheetReader
{
    /**
     * @return array<int, array<int, string>>
     */
    public function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException("Unsupported import format: {$extension}"),
        };
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $first = true;

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($first) {
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                }
                $first = false;
            }

            $rows[] = array_map(fn ($v) => trim((string) $v), $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the Excel file.');
        }

        $shared = $this->readSharedStrings($zip);
        $sheetXml = $this->firstWorksheetXml($zip);
        $zip->close();

        $sheet = $this->loadXml($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $maxIndex = -1;

            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $colIndex = $this->columnIndex(preg_replace('/\d+/', '', $ref) ?: 'A');
                $cells[$colIndex] = $this->cellValue($c, $shared);
                $maxIndex = max($maxIndex, $colIndex);
            }

            $ordered = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $ordered[] = $cells[$i] ?? '';
            }

            $rows[] = $ordered;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $shared
     */
    private function cellValue(SimpleXMLElement $c, array $shared): string
    {
        $type = (string) $c['t'];

        if ($type === 's') {
            $index = (int) $c->v;

            return $shared[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            return trim((string) $c->is->t);
        }

        return trim((string) $c->v);
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $xml = $this->loadXml($contents);
        $strings = [];

        foreach ($xml->si as $si) {
            // Concatenate all <t> nodes (handles rich-text runs).
            $text = '';
            foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                $text .= (string) $t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function firstWorksheetXml(ZipArchive $zip): string
    {
        $contents = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($contents !== false) {
            return $contents;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^xl/worksheets/sheet.*\.xml$#', $name)) {
                return (string) $zip->getFromName($name);
            }
        }

        throw new RuntimeException('No worksheet found in the Excel file.');
    }

    /**
     * Parse OOXML, stripping the default namespace so elements can be accessed
     * without namespace juggling (Excel writes sheetData/sst in a default ns).
     * Prefixed namespaces (e.g. r:) are preserved to keep the document valid.
     */
    private function loadXml(string $contents): SimpleXMLElement
    {
        $contents = preg_replace('/\sxmlns="[^"]*"/', '', $contents, 1);

        return new SimpleXMLElement($contents);
    }

    /**
     * Convert a column reference like "A", "B", "AA" to a zero-based index.
     */
    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}

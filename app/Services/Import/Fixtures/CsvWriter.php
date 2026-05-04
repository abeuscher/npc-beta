<?php

namespace App\Services\Import\Fixtures;

use RuntimeException;

class CsvWriter
{
    public const ENCODINGS = ['utf8', 'utf8-bom', 'windows-1252'];

    /**
     * Write rows to a CSV file with the chosen encoding + line endings.
     * - utf8 / utf8-bom: LF line endings
     * - windows-1252:    CRLF line endings (matches actual Excel-export behavior)
     *
     * Returns the SHA-256 of the file's bytes after encoding is applied.
     */
    public function write(string $path, array $headers, array $rows, string $encoding): string
    {
        if (! in_array($encoding, self::ENCODINGS, true)) {
            throw new RuntimeException("Unknown encoding: {$encoding}");
        }

        $eol = $encoding === 'windows-1252' ? "\r\n" : "\n";

        $fp = fopen($path, 'wb');

        if ($fp === false) {
            throw new RuntimeException("Failed to open {$path} for writing.");
        }

        if ($encoding === 'utf8-bom') {
            fwrite($fp, "\xEF\xBB\xBF");
        }

        fwrite($fp, $this->encodeLine($this->csvLine($headers), $encoding) . $eol);

        foreach ($rows as $row) {
            $line = array_map(fn ($h) => $row[$h] ?? null, $headers);
            fwrite($fp, $this->encodeLine($this->csvLine($line), $encoding) . $eol);
        }

        fclose($fp);

        return hash_file('sha256', $path);
    }

    /**
     * Render a CSV line in UTF-8 using PHP's standard quoting rules. Values
     * containing comma / quote / newline get wrapped; embedded quotes get
     * doubled.
     */
    private function csvLine(array $values): string
    {
        $parts = [];

        foreach ($values as $value) {
            if ($value === null) {
                $parts[] = '';
                continue;
            }

            $s = (string) $value;

            if (preg_match('/[",\r\n]/', $s)) {
                $parts[] = '"' . str_replace('"', '""', $s) . '"';
            } else {
                $parts[] = $s;
            }
        }

        return implode(',', $parts);
    }

    private function encodeLine(string $utf8Line, string $encoding): string
    {
        if ($encoding === 'windows-1252') {
            return mb_convert_encoding($utf8Line, 'Windows-1252', 'UTF-8');
        }

        return $utf8Line;
    }
}

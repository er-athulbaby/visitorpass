<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Parse an uploaded CSV whose first line is a header row.
     *
     * @return array<int, array<string, string>> keyed by spreadsheet line number
     */
    public static function records(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = null;
        $records = [];
        $line = 0;

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $line++;
            $values = array_map(fn ($value) => trim((string) $value), $row);

            if ($header === null) {
                // Excel prepends a UTF-8 BOM, which would otherwise glue itself to the first header.
                $values[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', $values[0]));
                $header = array_map('mb_strtolower', $values);

                continue;
            }

            if (implode('', $values) === '') {
                continue;
            }

            $values = array_pad(array_slice($values, 0, count($header)), count($header), '');
            $records[$line] = array_combine($header, $values);
        }

        fclose($handle);

        return $records;
    }

    public static function field(array $record, string ...$names): string
    {
        foreach ($names as $name) {
            $key = mb_strtolower($name);

            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        return '';
    }

    public static function template(string $filename, array $headers): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ',', '"', '');
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

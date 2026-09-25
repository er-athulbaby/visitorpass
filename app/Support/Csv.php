<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Parse an uploaded CSV whose first line is a header row. Each entry of
     * $required is a list of accepted names for one column the file must have.
     *
     * @throws ValidationException on the 'file' field for non-UTF-8 files or missing columns
     * @return array<int, array<string, string>> keyed by spreadsheet line number
     */
    public static function records(UploadedFile $file, array $required = []): array
    {
        if (! mb_check_encoding(file_get_contents($file->getRealPath()), 'UTF-8')) {
            throw ValidationException::withMessages(['file' => __('The file is not UTF-8. In Excel, save it as "CSV UTF-8".')]);
        }

        $handle = fopen($file->getRealPath(), 'r');

        // Excel (and our own template) prepend a UTF-8 BOM; it must go before
        // fgetcsv sees it, or a quoted first header keeps its quotes.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = null;
        $records = [];
        $line = 0;

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $line++;
            // Collapses line breaks inside quoted cells, so a name never contains a newline.
            $values = array_map(fn ($value) => trim(preg_replace('/\s+/u', ' ', (string) $value)), $row);

            if ($header === null) {
                $header = array_map('mb_strtolower', $values);
                // Blank duplicates are just trailing empty columns, not a conflict.
                $duplicates = array_filter(array_diff_assoc($header, array_unique($header)), fn ($name) => $name !== '');

                if ($duplicates) {
                    fclose($handle);

                    throw ValidationException::withMessages(['file' => __('Duplicate column: :column', ['column' => reset($duplicates)])]);
                }

                foreach ($required as $names) {
                    if (! array_intersect(array_map('mb_strtolower', $names), $header)) {
                        fclose($handle);

                        throw ValidationException::withMessages(['file' => __('Missing column: :column (found: :found)', [
                            'column' => $names[0],
                            'found' => implode(', ', $values),
                        ])]);
                    }
                }

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

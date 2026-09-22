<?php

namespace App\Services;

class EnvFileWriter
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    public function update(array $values): void
    {
        $contents = file_get_contents($this->path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        file_put_contents($this->path, $contents);
    }

    private function format(string $value): string
    {
        if (preg_match('/[\s#"]/', $value)) {
            return '"'.addslashes($value).'"';
        }

        return $value;
    }
}

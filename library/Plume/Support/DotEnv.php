<?php

declare(strict_types=1);

class PlumeDotEnv
{
    public static function parse(string $filePath): array
    {
        if (!is_readable($filePath)) {
            return [];
        }
        $result = [];
        foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if ($key === '') {
                continue;
            }
            $result[$key] = self::parseValue($value);
        }
        return $result;
    }

    private static function parseValue(string $value): mixed
    {
        // Double-quoted: parse escape sequences
        if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            return stripcslashes(substr($value, 1, -1));
        }
        // Single-quoted: literal value
        if (strlen($value) >= 2 && $value[0] === "'" && $value[-1] === "'") {
            return substr($value, 1, -1);
        }
        // Strip inline comment (space + #)
        if (($pos = strpos($value, ' #')) !== false) {
            $value = trim(substr($value, 0, $pos));
        }
        return match(strtolower($value)) {
            'true', 'yes', 'on'  => true,
            'false', 'no', 'off' => false,
            'null', ''           => null,
            default              => is_numeric($value)
                ? (str_contains($value, '.') ? (float) $value : (int) $value)
                : $value,
        };
    }
}

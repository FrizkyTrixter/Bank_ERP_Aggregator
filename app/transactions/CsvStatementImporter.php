<?php

namespace App\Transactions;

use DateTimeImmutable;
use RuntimeException;

class CsvStatementImporter
{
    private const REQUIRED_HEADERS = ['date', 'description', 'amount'];

    /**
     * Parse and validate a statement before anything is written to the database.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    public function parse(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        $fileHash = hash_file('sha256', $path);
        $handle = fopen($path, 'rb');

        if ($fileHash === false || $handle === false) {
            throw new RuntimeException('The uploaded CSV file could not be opened.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('The CSV file is empty.');
            }

            $header = array_map(
                static fn ($value): string => strtolower(trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF")),
                $header
            );

            foreach (self::REQUIRED_HEADERS as $requiredHeader) {
                if (!in_array($requiredHeader, $header, true)) {
                    throw new RuntimeException(
                        'Missing required CSV column: ' . $requiredHeader . '.'
                    );
                }
            }

            $rows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isBlankRow($values)) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw new RuntimeException(
                        "Line {$lineNumber} has " . count($values) .
                        ' columns; expected ' . count($header) . '.'
                    );
                }

                $row = array_combine($header, $values);
                if ($row === false) {
                    throw new RuntimeException("Line {$lineNumber} could not be parsed.");
                }

                $date = $this->normalizeDate((string) $row['date'], $lineNumber);
                $description = trim((string) $row['description']);
                $amount = $this->normalizeAmount((string) $row['amount'], $lineNumber);
                $category = trim((string) ($row['category'] ?? ''));

                if ($description === '') {
                    throw new RuntimeException("Line {$lineNumber} has an empty description.");
                }

                $rows[] = [
                    'transaction_date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'category' => $category === '' ? null : $category,
                    'import_hash' => hash('sha256', $fileHash . ':' . $lineNumber),
                ];
            }

            if ($rows === []) {
                throw new RuntimeException('The CSV file contains no transaction rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate(string $value, int $lineNumber): string
    {
        $value = trim($value);

        foreach (['!Y-m-d', '!Y/m/d', '!m/d/Y', '!d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        throw new RuntimeException(
            "Line {$lineNumber} has an invalid date. Use YYYY-MM-DD, YYYY/MM/DD, MM/DD/YYYY, or DD/MM/YYYY."
        );
    }

    private function normalizeAmount(string $value, int $lineNumber): float
    {
        $value = trim($value);
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $normalized = str_replace([',', '$', ' ', '(', ')'], '', $value);

        if ($normalized === '' || !is_numeric($normalized)) {
            throw new RuntimeException(
                "Line {$lineNumber} has an invalid amount. Use a signed number such as -42.50 or 1250.00."
            );
        }

        $amount = (float) $normalized;

        return $negative ? -abs($amount) : $amount;
    }
}

<?php

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/investments/InvestmentBatchImporter.php';

use App\Investments\InvestmentBatchImporter;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This importer is CLI-only.\n");
    exit(2);
}

$path = $argv[1] ?? '';
if ($path === '') {
    fwrite(STDERR, "Usage: php scripts/import_investment_batch.php <batch.json>\n");
    exit(2);
}

try {
    $result = (new InvestmentBatchImporter())->importFile($path, getenv('ERP_HMAC_SECRET') ?: null);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "Investment import failed: {$exception->getMessage()}\n");
    exit(1);
}


<?php

namespace App\Investments;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class InvestmentBatchImporter
{
    private const EVENT_TYPES = [
        'account_snapshot', 'cash_balance', 'position_snapshot', 'security', 'order',
        'fill', 'fee', 'realized_gain_loss', 'dividend', 'transfer',
        'valuation_snapshot', 'correction',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @return array{imported: int, skipped: int, batch_id: string} */
    public function importFile(string $path, ?string $hmacSecret = null): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Investment batch is not readable.');
        }
        $raw = file_get_contents($path);
        $batch = $raw === false ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($batch)) {
            throw new RuntimeException('Investment batch must be a JSON object.');
        }
        $this->validateBatch($batch, $hmacSecret);
        $batchId = (string) $batch['batch_id'];
        if ($this->batchExists($batchId)) {
            return ['imported' => 0, 'skipped' => count($batch['events']), 'batch_id' => $batchId];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO investment_account_mappings
                 (external_account_id, account_id, source_system)
                 VALUES (:external_id, NULL, :source)
                 ON CONFLICT(external_account_id) DO NOTHING'
            )->execute(['external_id' => $batch['account_external_id'], 'source' => $batch['source_system']]);
            $this->db->prepare(
                'INSERT INTO investment_import_batches
                 (batch_id, source_system, content_hash, signature, status)
                 VALUES (:batch_id, :source, :content_hash, :signature, :status)'
            )->execute([
                'batch_id' => $batchId, 'source' => $batch['source_system'],
                'content_hash' => $batch['content_hash'], 'signature' => $batch['signature'] ?? null,
                'status' => 'importing',
            ]);
            $inserted = 0;
            $skipped = 0;
            foreach ($batch['events'] as $event) {
                try {
                    $this->insertEvent($batch, $event);
                    $inserted++;
                } catch (\PDOException $exception) {
                    if ((string) $exception->getCode() === '23000') {
                        $skipped++;
                        continue;
                    }
                    throw $exception;
                }
            }
            $this->db->prepare('UPDATE investment_import_batches SET status = :status WHERE batch_id = :batch_id')
                ->execute(['status' => 'imported', 'batch_id' => $batchId]);
            $this->audit($batchId, 'investment_batch_import', 'success', ['imported' => $inserted, 'skipped' => $skipped]);
            $this->db->commit();
            return ['imported' => $inserted, 'skipped' => $skipped, 'batch_id' => $batchId];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function validateBatch(array $batch, ?string $hmacSecret): void
    {
        foreach (['schema_version', 'batch_id', 'source_system', 'account_external_id', 'events', 'content_hash'] as $field) {
            if (!array_key_exists($field, $batch)) {
                throw new RuntimeException('Missing batch field: ' . $field);
            }
        }
        if ($batch['schema_version'] !== '1.0.0' || $batch['source_system'] !== 'stock-trading-pipeline') {
            throw new RuntimeException('Unsupported investment contract version or source.');
        }
        if (!is_array($batch['events'])) {
            throw new RuntimeException('Batch events must be an array.');
        }
        $payload = [
            'schema_version' => $batch['schema_version'], 'batch_id' => $batch['batch_id'],
            'source_system' => $batch['source_system'], 'account_external_id' => $batch['account_external_id'],
            'events' => $batch['events'],
        ];
        $canonical = $this->canonicalJson($payload);
        if (!hash_equals((string) $batch['content_hash'], hash('sha256', $canonical))) {
            throw new RuntimeException('Batch content hash verification failed.');
        }
        if ($hmacSecret !== null) {
            $signature = (string) ($batch['signature'] ?? '');
            if ($signature === '' || !hash_equals($signature, hash_hmac('sha256', $canonical, $hmacSecret))) {
                throw new RuntimeException('Batch HMAC signature verification failed.');
            }
        }
        foreach ($batch['events'] as $index => $event) {
            $this->validateEvent($event, (int) $index);
        }
    }

    private function validateEvent(mixed $event, int $index): void
    {
        if (!is_array($event)) {
            throw new RuntimeException("Event {$index} must be an object.");
        }
        foreach (['event_external_id', 'event_type', 'effective_at', 'currency', 'description', 'content_hash'] as $field) {
            if (!isset($event[$field]) || trim((string) $event[$field]) === '') {
                throw new RuntimeException("Event {$index} is missing {$field}.");
            }
        }
        if (!in_array($event['event_type'], self::EVENT_TYPES, true)) {
            throw new RuntimeException("Event {$index} has an unsupported event type.");
        }
        if (!preg_match('/^[A-Z]{3}$/', (string) $event['currency'])) {
            throw new RuntimeException("Event {$index} has an invalid currency.");
        }
        try {
            $effectiveAt = new DateTimeImmutable((string) $event['effective_at']);
        } catch (\Exception) {
            throw new RuntimeException("Event {$index} has an invalid timezone-aware timestamp.");
        }
        if ($effectiveAt->getTimezone()->getName() === '') {
            throw new RuntimeException("Event {$index} timestamp must include a timezone.");
        }
        $payload = $event;
        unset($payload['content_hash']);
        if (!hash_equals((string) $event['content_hash'], hash('sha256', $this->canonicalJson($payload)))) {
            throw new RuntimeException("Event {$index} content hash verification failed.");
        }
    }

    private function insertEvent(array $batch, array $event): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO investment_events
             (batch_id, source_system, event_external_id, external_account_id, event_type,
              effective_at, currency, symbol, quantity, unit_price, gross_amount, fee_amount,
              net_amount, order_id, fill_id, description, metadata_json, content_hash)
             VALUES
             (:batch_id, :source, :event_id, :account_id, :event_type, :effective_at,
              :currency, :symbol, :quantity, :unit_price, :gross_amount, :fee_amount,
              :net_amount, :order_id, :fill_id, :description, :metadata, :content_hash)'
        );
        $stmt->execute([
            'batch_id' => $batch['batch_id'], 'source' => $batch['source_system'],
            'event_id' => $event['event_external_id'], 'account_id' => $batch['account_external_id'],
            'event_type' => $event['event_type'], 'effective_at' => $event['effective_at'],
            'currency' => $event['currency'], 'symbol' => $event['symbol'] ?? null,
            'quantity' => $event['quantity'] ?? null, 'unit_price' => $event['unit_price'] ?? null,
            'gross_amount' => $event['gross_amount'] ?? null, 'fee_amount' => $event['fee_amount'] ?? '0',
            'net_amount' => $event['net_amount'] ?? null, 'order_id' => $event['order_id'] ?? null,
            'fill_id' => $event['fill_id'] ?? null, 'description' => $event['description'],
            'metadata' => json_encode($event['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'content_hash' => $event['content_hash'],
        ]);
    }

    private function batchExists(string $batchId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM investment_import_batches WHERE batch_id = :batch_id');
        $stmt->execute(['batch_id' => $batchId]);
        return (bool) $stmt->fetchColumn();
    }

    private function audit(string $batchId, string $eventName, string $outcome, array $details): void
    {
        $this->db->prepare(
            'INSERT INTO investment_audit_log (batch_id, event_name, outcome, details_json)
             VALUES (:batch_id, :event_name, :outcome, :details)'
        )->execute([
            'batch_id' => $batchId, 'event_name' => $eventName, 'outcome' => $outcome,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }

    private function canonicalJson(mixed $value): string
    {
        $sort = function (mixed &$item) use (&$sort): void {
            if (!is_array($item)) {
                return;
            }
            if (!array_is_list($item)) {
                ksort($item, SORT_STRING);
            }
            foreach ($item as &$child) {
                $sort($child);
            }
        };
        $sort($value);
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

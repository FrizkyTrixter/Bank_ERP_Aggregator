<?php

namespace App\Trading;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * HTTP client for the Stock_Trading_Pipeline read-only API.
 *
 * Successful backend responses are cached in the system temp directory for
 * cache_ttl seconds. When the backend is unreachable, read-only fallbacks
 * against the local investment_events ledger are used and the result is
 * flagged with '_fallback' => true. Errors are sanitized: the UI only ever
 * sees a generic message, never curl details, URLs, or stack traces.
 */
final class TradingBackendClient
{
    private string $backendUrl;
    private int $timeout;
    private int $cacheTtl;
    private string $cacheDir;
    private ?string $lastError = null;
    private bool $usedFallback = false;

    /**
     * @param array{backend_url?: string, timeout?: int, cache_ttl?: int}|null $config
     */
    public function __construct(?array $config = null)
    {
        $config ??= require dirname(__DIR__, 2) . '/config/trading.php';
        $this->backendUrl = rtrim((string) ($config['backend_url'] ?? 'http://127.0.0.1:8000'), '/');
        $this->timeout = max(1, (int) ($config['timeout'] ?? 5));
        $this->cacheTtl = max(0, (int) ($config['cache_ttl'] ?? 30));
        $this->cacheDir = rtrim(sys_get_temp_dir(), '/\\') . '/bank_erp_trading_cache';
    }

    /**
     * Aggregate portfolio state. Falls back to the local ledger on failure.
     *
     * @return array{cash: string, starting_capital: string, realized_pnl: string,
     *               portfolio_peak: string, position_count: int, _fallback?: true}
     */
    public function getPortfolio(): array
    {
        $data = $this->get('/api/portfolio');
        if (is_array($data)) {
            return $data;
        }

        return $this->withFallback(
            fn() => $this->fallbackPortfolio(),
            ['cash' => '0', 'starting_capital' => '0', 'realized_pnl' => '0',
             'portfolio_peak' => '0', 'position_count' => 0]
        );
    }

    /**
     * Open positions. Falls back to the local ledger on failure.
     *
     * @return list<array{ticker: string, quantity: string, average_cost: string}>
     */
    public function getPositions(): array
    {
        $data = $this->get('/api/positions');
        if (is_array($data)) {
            return $data;
        }

        return $this->withFallback(fn() => $this->fallbackPositions(), []);
    }

    /**
     * Recent simulated trades, newest first. Falls back to ledger fills.
     *
     * @return list<array<string, mixed>>
     */
    public function getTrades(int $limit = 50): array
    {
        $data = $this->get('/api/trades', ['limit' => $this->clampLimit($limit)]);
        if (is_array($data)) {
            return $data;
        }

        return $this->withFallback(fn() => $this->fallbackTrades($limit), []);
    }

    /**
     * Recent trade decisions, newest first. Falls back to ledger orders.
     *
     * @return list<array<string, mixed>>
     */
    public function getDecisions(int $limit = 50): array
    {
        $data = $this->get('/api/decisions', ['limit' => $this->clampLimit($limit)]);
        if (is_array($data)) {
            return $data;
        }

        return $this->withFallback(fn() => $this->fallbackDecisions($limit), []);
    }

    /**
     * Latest research universe members, ranked. The ledger has no universe
     * data, so the fallback is an empty flagged list.
     *
     * @return list<array<string, mixed>>
     */
    public function getUniverse(int $limit = 100): array
    {
        $data = $this->get('/api/universe', ['limit' => $this->clampLimit($limit, 500)]);
        if (is_array($data)) {
            return $data;
        }

        return $this->withFallback(fn() => [], []);
    }

    /**
     * Whether any getter fell back to the local ledger.
     */
    public function isFallback(): bool
    {
        return $this->usedFallback;
    }

    /**
     * Sanitized error message for UI display, or null when healthy.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Cached GET against the trading API. Returns null on any failure.
     *
     * @param array<string, scalar> $query
     */
    private function get(string $path, array $query = []): ?array
    {
        $url = $this->backendUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $key = md5($url);
        $cached = $this->readCache($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Trading backend unavailable.');
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($body === false || $status < 200 || $status >= 300) {
                throw new RuntimeException('Trading backend unavailable.');
            }
            $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException('Trading backend returned invalid data.');
            }
            $this->writeCache($key, $data);

            return $data;
        } catch (Throwable $exception) {
            // Sanitized: never leak curl errors, URLs, or stack traces.
            $this->lastError = 'Trading backend unavailable.';

            return null;
        }
    }

    /**
     * Run a ledger fallback, flag the result, and never throw.
     *
     * @param callable(): array $fetch
     */
    private function withFallback(callable $fetch, array $empty): array
    {
        try {
            $result = $fetch();
        } catch (Throwable $exception) {
            $this->lastError = 'Trading data unavailable.';
            $result = $empty;
        }
        $this->usedFallback = true;
        if (array_is_list($result)) {
            foreach ($result as &$row) {
                if (is_array($row)) {
                    $row['_fallback'] = true;
                }
            }
            unset($row);
        } else {
            $result['_fallback'] = true;
        }

        return $result;
    }

    private function clampLimit(int $limit, int $max = 500): int
    {
        return max(1, min($max, $limit));
    }

    private function readCache(string $key): ?array
    {
        if ($this->cacheTtl <= 0) {
            return null;
        }
        $path = $this->cacheDir . '/' . $key . '.json';
        if (!is_readable($path)) {
            return null;
        }
        $mtime = filemtime($path);
        if ($mtime === false || (time() - $mtime) > $this->cacheTtl) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private function writeCache(string $key, array $data): void
    {
        if ($this->cacheTtl <= 0) {
            return;
        }
        try {
            if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0775, true) && !is_dir($this->cacheDir)) {
                return;
            }
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            file_put_contents($this->cacheDir . '/' . $key . '.json', $encoded, LOCK_EX);
        } catch (Throwable $exception) {
            // Caching is best-effort; a failed cache must not break the page.
        }
    }

    private function ledger(): PDO
    {
        return Database::connection();
    }

    /**
     * Portfolio state derived from immutable investment_events.
     *
     * @return array{cash: string, starting_capital: string, realized_pnl: string,
     *               portfolio_peak: string, position_count: int}
     */
    private function fallbackPortfolio(): array
    {
        $db = $this->ledger();
        $cash = $db->query(
            "SELECT net_amount FROM investment_events
             WHERE event_type = 'cash_balance'
             ORDER BY effective_at DESC, id DESC LIMIT 1"
        )->fetchColumn();
        $firstCash = $db->query(
            "SELECT net_amount FROM investment_events
             WHERE event_type = 'cash_balance'
             ORDER BY effective_at ASC, id ASC LIMIT 1"
        )->fetchColumn();
        $peak = $db->query(
            "SELECT net_amount FROM investment_events
             WHERE event_type = 'valuation_snapshot'
             ORDER BY effective_at DESC, id DESC LIMIT 1"
        )->fetchColumn();
        $realized = $db->query(
            "SELECT COALESCE(SUM(CAST(net_amount AS NUMERIC)), 0) FROM investment_events
             WHERE event_type = 'realized_gain_loss'"
        )->fetchColumn();
        $positionCount = $db->query(
            'SELECT COUNT(*) FROM (
               SELECT symbol FROM investment_events
               WHERE symbol IS NOT NULL AND event_type IN (\'fill\', \'position_snapshot\')
               GROUP BY symbol HAVING SUM(CAST(quantity AS NUMERIC)) != 0
             )'
        )->fetchColumn();

        return [
            'cash' => (string) ($cash === false ? '0' : $cash),
            'starting_capital' => (string) ($firstCash === false ? '0' : $firstCash),
            'realized_pnl' => (string) ($realized === false ? '0' : $realized),
            'portfolio_peak' => (string) ($peak === false ? '0' : $peak),
            'position_count' => (int) $positionCount,
        ];
    }

    /**
     * Positions derived from fills and position snapshots, mirroring the
     * aggregation used by public/investments.php.
     *
     * @return list<array{ticker: string, quantity: string, average_cost: string}>
     */
    private function fallbackPositions(): array
    {
        $db = $this->ledger();
        $rows = $db->query(
            "SELECT symbol, SUM(CAST(quantity AS NUMERIC)) AS quantity
             FROM investment_events
             WHERE symbol IS NOT NULL AND event_type IN ('fill', 'position_snapshot')
             GROUP BY symbol HAVING SUM(CAST(quantity AS NUMERIC)) != 0
             ORDER BY symbol"
        )->fetchAll();
        $costs = [];
        $snapshots = $db->query(
            "SELECT symbol, unit_price FROM investment_events
             WHERE event_type = 'position_snapshot' AND symbol IS NOT NULL
             ORDER BY effective_at DESC, id DESC"
        )->fetchAll();
        foreach ($snapshots as $snapshot) {
            if (!isset($costs[$snapshot['symbol']])) {
                $costs[$snapshot['symbol']] = $snapshot['unit_price'];
            }
        }
        $positions = [];
        foreach ($rows as $row) {
            $positions[] = [
                'ticker' => (string) $row['symbol'],
                'quantity' => (string) $row['quantity'],
                'average_cost' => (string) ($costs[$row['symbol']] ?? '0'),
            ];
        }

        return $positions;
    }

    /**
     * Trades derived from ledger fill events (best effort).
     *
     * @return list<array<string, mixed>>
     */
    private function fallbackTrades(int $limit): array
    {
        $limit = $this->clampLimit($limit);
        $db = $this->ledger();
        $rows = $db->query(
            'SELECT event_external_id, symbol, quantity, unit_price, gross_amount,
                    fee_amount, net_amount, effective_at, description
             FROM investment_events
             WHERE event_type = \'fill\'
             ORDER BY effective_at DESC, id DESC LIMIT ' . $limit
        )->fetchAll();
        $trades = [];
        foreach ($rows as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $trades[] = [
                'id' => (string) $row['event_external_id'],
                'ticker' => (string) $row['symbol'],
                'side' => $quantity >= 0 ? 'BUY' : 'SELL',
                'quantity' => (string) abs($quantity),
                'price' => (string) ($row['unit_price'] ?? '0'),
                'notional' => (string) ($row['gross_amount'] ?? '0'),
                'fees' => (string) ($row['fee_amount'] ?? '0'),
                'realized_pnl' => '0',
                'portfolio_value_before' => '0',
                'portfolio_value_after' => (string) ($row['net_amount'] ?? '0'),
                'reason' => (string) $row['description'],
                'market_data_timestamp' => (string) $row['effective_at'],
                'decision_confidence' => null,
                'decision_rationale' => null,
                'decision_metadata' => [],
            ];
        }

        return $trades;
    }

    /**
     * Decisions derived from ledger order events (best effort).
     *
     * @return list<array<string, mixed>>
     */
    private function fallbackDecisions(int $limit): array
    {
        $limit = $this->clampLimit($limit);
        $db = $this->ledger();
        $rows = $db->query(
            'SELECT event_external_id, symbol, quantity, effective_at, description, metadata_json
             FROM investment_events
             WHERE event_type = \'order\'
             ORDER BY effective_at DESC, id DESC LIMIT ' . $limit
        )->fetchAll();
        $decisions = [];
        foreach ($rows as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $metadata = json_decode((string) ($row['metadata_json'] ?? '[]'), true);
            $decisions[] = [
                'id' => (string) $row['event_external_id'],
                'ticker' => (string) $row['symbol'],
                'side' => $quantity >= 0 ? 'BUY' : 'SELL',
                'reason' => (string) $row['description'],
                'confidence' => null,
                'rationale' => null,
                'metadata' => is_array($metadata) ? $metadata : [],
                'created_at' => (string) $row['effective_at'],
            ];
        }

        return $decisions;
    }
}

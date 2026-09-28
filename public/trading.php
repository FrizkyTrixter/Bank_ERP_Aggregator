<?php

/**
 * Agentic Trading dashboard (read-only).
 *
 * Shows the Stock_Trading_Pipeline simulated-trading ledger through its
 * read-only API, falling back to the local investment_events ledger when
 * the backend is unreachable. ?format=json returns the same data as JSON
 * for the auto-refresh script. This page never writes anything.
 */

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/trading/TradingBackendClient.php';
require_once dirname(__DIR__) . '/app/trading/AgentReasonPresenter.php';

use App\Trading\AgentReasonPresenter as Presenter;
use App\Trading\TradingBackendClient;

$tradingConfig = require dirname(__DIR__) . '/config/trading.php';
$client = new TradingBackendClient(is_array($tradingConfig) ? $tradingConfig : null);

$portfolio = $client->getPortfolio();
$positions = $client->getPositions();
$trades = $client->getTrades(50);
$decisions = $client->getDecisions(50);
$universe = $client->getUniverse(100);
$isFallback = $client->isFallback();

$payload = [
    'source' => $isFallback ? 'ledger' : 'live',
    'tagline' => 'AI researches and explains, deterministic policies execute, the ledger remembers.',
    'portfolio' => $portfolio,
    'positions' => $positions,
    'trades' => $trades,
    'decisions' => $decisions,
    'universe' => $universe,
];

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function money(string|int|float|null $value): string
{
    return number_format((float) ($value ?? 0), 2);
}

function pnlClass(string|int|float|null $value): string
{
    return ((float) ($value ?? 0)) >= 0 ? 'pnl-positive' : 'pnl-negative';
}

function sideClass(?string $side): string
{
    return strtoupper((string) $side) === 'BUY' ? 'side-buy' : 'side-sell';
}

$exposure = 0.0;
foreach ($positions as $position) {
    $exposure += (float) ($position['quantity'] ?? 0) * (float) ($position['average_cost'] ?? 0);
}

$universeByTicker = [];
foreach ($universe as $member) {
    $universeByTicker[strtoupper((string) ($member['ticker'] ?? ''))] = $member;
}

$latestDecisionByTicker = [];
foreach ($decisions as $decision) {
    $key = strtoupper((string) ($decision['ticker'] ?? ''));
    if ($key !== '' && !isset($latestDecisionByTicker[$key])) {
        $latestDecisionByTicker[$key] = $decision;
    }
}

$holdings = $positions;
usort($holdings, function (array $a, array $b): int {
    $exposureA = (float) ($a['quantity'] ?? 0) * (float) ($a['average_cost'] ?? 0);
    $exposureB = (float) ($b['quantity'] ?? 0) * (float) ($b['average_cost'] ?? 0);

    return $exposureB <=> $exposureA;
});
$holdings = array_slice($holdings, 0, 5);

$badgeClass = $isFallback ? 'source-badge source-ledger' : 'source-badge source-live';
$badgeText = $isFallback ? 'CACHED / LEDGER' : 'LIVE';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agentic Trading | Bank ERP Aggregator</title>
    <link rel="stylesheet" href="/assets/trading.css">
</head>
<body class="trading-dark">
    <header class="trading-topbar">
        <div class="trading-topbar-inner">
            <a class="trading-brand" href="/trading.php">Agentic Trading</a>
            <nav class="trading-nav">
                <a href="/accounts.php">Accounts</a>
                <a href="/transactions.php">Transactions</a>
                <a href="/import_transactions.php">Import CSV</a>
                <a href="/trading.php" class="active">Agentic Trading</a>
            </nav>
        </div>
    </header>

    <main class="trading-main">
        <section class="trading-hero">
            <h1>Agentic Trading</h1>
            <p class="tagline">AI researches and explains, deterministic policies execute, the ledger remembers.</p>
            <div class="badge-row">
                <span id="source-badge" class="<?= $badgeClass ?>"><span class="dot"></span><?= Presenter::escape($badgeText) ?></span>
                <span class="updated-note">Updated <span id="updated-at">just now</span> · auto-refreshes every 30s</span>
            </div>
        </section>

        <section id="portfolio-cards" class="card-grid" aria-label="Portfolio overview">
            <div class="glass-card">
                <div class="stat-label">Cash</div>
                <div class="stat-value"><?= Presenter::escape(money($portfolio['cash'] ?? 0)) ?></div>
            </div>
            <div class="glass-card">
                <div class="stat-label">Realized P&amp;L</div>
                <div class="stat-value <?= pnlClass($portfolio['realized_pnl'] ?? 0) ?>"><?= Presenter::escape(money($portfolio['realized_pnl'] ?? 0)) ?></div>
            </div>
            <div class="glass-card">
                <div class="stat-label">Est. exposure</div>
                <div class="stat-value"><?= Presenter::escape(money($exposure)) ?></div>
            </div>
            <div class="glass-card">
                <div class="stat-label">Positions</div>
                <div class="stat-value"><?= Presenter::escape($portfolio['position_count'] ?? count($positions)) ?></div>
            </div>
        </section>

        <div class="trading-sections">
            <section class="glass-card" aria-label="Live trades feed">
                <h2 class="section-title">Live trades feed</h2>
                <div id="trades-feed">
                    <?php if (!$trades): ?>
                        <p class="empty-note">No trades recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($trades as $trade): ?>
                            <article class="trade-item">
                                <div class="trade-head">
                                    <span class="side-badge <?= sideClass($trade['side'] ?? null) ?>"><?= Presenter::escape($trade['side'] ?? '') ?></span>
                                    <span class="trade-ticker"><?= Presenter::escape($trade['ticker'] ?? '') ?></span>
                                    <span class="badge conf-badge <?= Presenter::confidenceBadge($trade['decision_confidence'] ?? null) ?>">AI <?= Presenter::escape(Presenter::confidenceLabel($trade['decision_confidence'] ?? null)) ?></span>
                                </div>
                                <div class="trade-meta">
                                    <span>Qty <strong><?= Presenter::escape($trade['quantity'] ?? '') ?></strong></span>
                                    <span>Price <strong><?= Presenter::escape(money($trade['price'] ?? 0)) ?></strong></span>
                                    <span>Notional <strong><?= Presenter::escape(money($trade['notional'] ?? 0)) ?></strong></span>
                                    <span>P&amp;L <strong class="<?= pnlClass($trade['realized_pnl'] ?? 0) ?>"><?= Presenter::escape(money($trade['realized_pnl'] ?? 0)) ?></strong></span>
                                    <span><?= Presenter::escape($trade['market_data_timestamp'] ?? '') ?></span>
                                </div>
                                <div class="trade-reason">
                                    <span class="reason-text"><?= Presenter::summary($trade['reason'] ?? null, $trade['decision_rationale'] ?? null) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="glass-card" aria-label="Agent reasoning">
                <h2 class="section-title">Agent reasoning · top holdings</h2>
                <div id="reasoning-panel">
                    <?php if (!$holdings): ?>
                        <p class="empty-note">No holdings to explain yet.</p>
                    <?php else: ?>
                        <?php foreach ($holdings as $holding): ?>
                            <?php
                            $tickerKey = strtoupper((string) ($holding['ticker'] ?? ''));
                            $member = $universeByTicker[$tickerKey] ?? [];
                            $decision = $latestDecisionByTicker[$tickerKey] ?? [];
                            ?>
                            <div class="holding">
                                <div class="holding-head">
                                    <h3><?= Presenter::escape($holding['ticker'] ?? '') ?></h3>
                                    <?php if (!empty($member['company_name'])): ?>
                                        <span class="company"><?= Presenter::escape($member['company_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="badge conf-badge <?= Presenter::confidenceBadge($member['confidence'] ?? null) ?>"><?= Presenter::escape(Presenter::confidenceLabel($member['confidence'] ?? null)) ?></span>
                                </div>
                                <?php if (!empty($member['thesis'])): ?>
                                    <p class="thesis"><?= Presenter::escape($member['thesis']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($member['catalysts'])): ?>
                                    <div class="factor-title">Catalysts</div>
                                    <ul class="factor-list"><?= Presenter::listItems((array) $member['catalysts']) ?></ul>
                                <?php endif; ?>
                                <?php if (!empty($member['risks'])): ?>
                                    <div class="factor-title">Risks</div>
                                    <ul class="factor-list"><?= Presenter::listItems((array) $member['risks']) ?></ul>
                                <?php endif; ?>
                                <?= Presenter::feedbackCallout($member['feedback_loop_rationale'] ?? null) ?>
                                <?php if (!empty($decision['reason'])): ?>
                                    <div class="decision-note">
                                        <strong>Latest decision:</strong> <?= Presenter::summary($decision['reason'] ?? null, $decision['rationale'] ?? null) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <section class="glass-card" aria-label="Positions">
            <h2 class="section-title">Positions</h2>
            <div class="table-responsive">
                <table class="positions-table">
                    <thead>
                        <tr><th>Ticker</th><th class="num">Quantity</th><th class="num">Avg cost</th><th class="num">Exposure</th></tr>
                    </thead>
                    <tbody id="positions-body">
                        <?php if (!$positions): ?>
                            <tr><td colspan="4" class="empty-note">No open positions.</td></tr>
                        <?php else: ?>
                            <?php foreach ($positions as $position): ?>
                                <tr>
                                    <td><strong><?= Presenter::escape($position['ticker'] ?? '') ?></strong></td>
                                    <td class="num"><?= Presenter::escape($position['quantity'] ?? '') ?></td>
                                    <td class="num"><?= Presenter::escape(money($position['average_cost'] ?? 0)) ?></td>
                                    <td class="num"><?= Presenter::escape(money((float) ($position['quantity'] ?? 0) * (float) ($position['average_cost'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <a class="back-link" href="/index.php">&larr; Back to Bank ERP</a>
    </main>

    <script>
        window.__positions = <?= json_encode(array_values($positions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="/assets/trading.js"></script>
</body>
</html>

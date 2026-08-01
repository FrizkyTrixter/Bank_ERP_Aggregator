<?php

require_once dirname(__DIR__) . '/app/core/Database.php';

use App\Core\Database;

$db = Database::connection();
$events = $db->query(
    'SELECT event_type, effective_at, symbol, quantity, unit_price, net_amount, currency, description, source_system
     FROM investment_events ORDER BY effective_at DESC, id DESC LIMIT 250'
)->fetchAll();
$positions = $db->query(
    "SELECT symbol, currency, SUM(CAST(quantity AS NUMERIC)) AS quantity
     FROM investment_events
     WHERE symbol IS NOT NULL AND event_type IN ('fill', 'position_snapshot')
     GROUP BY symbol, currency
     HAVING SUM(CAST(quantity AS NUMERIC)) != 0
     ORDER BY symbol"
)->fetchAll();

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Investments | Bank ERP Aggregator</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-4"><h1 class="h3">Imported investment events</h1>
<p class="text-muted">Read-only view of versioned, idempotent external events. Corrections are separate events.</p>
<h2 class="h5 mt-4">Position quantities</h2>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Symbol</th><th>Currency</th><th>Quantity</th></tr></thead><tbody>
<?php foreach ($positions as $position): ?><tr><td><?= e($position['symbol']) ?></td><td><?= e($position['currency']) ?></td><td><?= e($position['quantity']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<h2 class="h5 mt-4">Events</h2>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Effective</th><th>Type</th><th>Symbol</th><th>Quantity</th><th>Price</th><th>Net</th><th>Description</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr><td><?= e($event['effective_at']) ?></td><td><?= e($event['event_type']) ?></td><td><?= e($event['symbol']) ?></td><td><?= e($event['quantity']) ?></td><td><?= e($event['unit_price']) ?></td><td><?= e($event['currency']) ?> <?= e($event['net_amount']) ?></td><td><?= e($event['description']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><a href="/accounts.php">Back to accounts</a></main></body></html>

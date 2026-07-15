<?php

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/accounts/AccountRepository.php';
require_once dirname(__DIR__) . '/app/transactions/TransactionRepository.php';

use App\Accounts\AccountRepository;
use App\Transactions\TransactionRepository;

$accounts = (new AccountRepository())->all();
$selectedAccountId = isset($_GET['account_id']) && $_GET['account_id'] !== ''
    ? (int) $_GET['account_id']
    : null;
$transactions = (new TransactionRepository())->all($selectedAccountId);

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transactions | Bank ERP Aggregator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/accounts.php">Bank ERP Aggregator</a>
            <div class="navbar-nav flex-row gap-3">
                <a class="nav-link" href="/accounts.php">Accounts</a>
                <a class="nav-link active" href="/transactions.php">Transactions</a>
                <a class="nav-link" href="/import_transactions.php">Import CSV</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="card shadow-sm">
            <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <strong>Transactions</strong>
                <div class="d-flex gap-2 align-items-center">
                    <form method="get" class="d-flex gap-2">
                        <select name="account_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All accounts</option>
                            <?php foreach ($accounts as $account): ?>
                                <option
                                    value="<?= e($account['id']) ?>"
                                    <?= $selectedAccountId === (int) $account['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($account['account_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <a href="/import_transactions.php" class="btn btn-primary btn-sm">Import CSV</a>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (!$transactions): ?>
                    <div class="p-4 text-muted">No transactions found. Import a CSV statement to get started.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Account</th>
                                    <th>Category</th>
                                    <th>Source</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= e($transaction['transaction_date']) ?></td>
                                        <td><?= e($transaction['description']) ?></td>
                                        <td><?= e($transaction['account_name']) ?></td>
                                        <td><?= e($transaction['category'] ?: '—') ?></td>
                                        <td><?= e($transaction['source_file'] ?: '—') ?></td>
                                        <td class="text-end text-nowrap <?= $transaction['amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= e($transaction['currency']) ?> <?= e(number_format((float) $transaction['amount'], 2)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>

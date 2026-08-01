<?php

session_start();

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/accounts/AccountRepository.php';
require_once dirname(__DIR__) . '/app/transactions/CsvStatementImporter.php';
require_once dirname(__DIR__) . '/app/transactions/TransactionRepository.php';

use App\Accounts\AccountRepository;
use App\Transactions\CsvStatementImporter;
use App\Transactions\TransactionRepository;

$accountRepository = new AccountRepository();
$accounts = $accountRepository->all();
$error = null;
$flash = $_SESSION['import_result'] ?? null;
unset($_SESSION['import_result']);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $accountId = (int) ($_POST['account_id'] ?? 0);
    $upload = $_FILES['statement'] ?? null;

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'The form expired. Refresh the page and try again.';
    } elseif (!$accountRepository->find($accountId)) {
        $error = 'Select a valid account.';
    } elseif (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Choose a CSV file to upload.';
    } elseif (($upload['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'The CSV file must be 5 MB or smaller.';
    } elseif (strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'The uploaded file must use the .csv extension.';
    } else {
        try {
            $importer = new CsvStatementImporter();
            $rows = $importer->parse((string) $upload['tmp_name']);
            $repository = new TransactionRepository();
            $result = $repository->import(
                $accountId,
                basename((string) $upload['name']),
                $rows
            );

            $_SESSION['import_result'] = $result;
            header('Location: /import_transactions.php');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

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
    <title>Import Statement | Bank ERP Aggregator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/accounts.php">Bank ERP Aggregator</a>
            <div class="navbar-nav flex-row gap-3">
                <a class="nav-link" href="/accounts.php">Accounts</a>
                <a class="nav-link" href="/transactions.php">Transactions</a>
                <a class="nav-link active" href="/import_transactions.php">Import CSV</a>
            </div>
        </div>
    </nav>

    <main class="container" style="max-width: 850px">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Import a CSV statement</strong></div>
            <div class="card-body">
                <?php if ($flash): ?>
                    <div class="alert alert-success">
                        Imported <?= e($flash['imported']) ?> transaction(s).
                        <?php if ($flash['skipped'] > 0): ?>
                            Skipped <?= e($flash['skipped']) ?> duplicate row(s).
                        <?php endif; ?>
                        <a href="/transactions.php" class="alert-link">View transactions</a>.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!$accounts): ?>
                    <div class="alert alert-warning mb-0">
                        You need to <a href="/accounts.php" class="alert-link">create an account</a>
                        before importing a statement.
                    </div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                        <div class="mb-3">
                            <label for="account_id" class="form-label">Import into account</label>
                            <select id="account_id" name="account_id" class="form-select" required>
                                <option value="">Select an account</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= e($account['id']) ?>">
                                        <?= e($account['account_name']) ?> — <?= e($account['institution']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="statement" class="form-label">CSV statement</label>
                            <input
                                id="statement"
                                name="statement"
                                type="file"
                                class="form-control"
                                accept=".csv,text/csv"
                                required
                            >
                            <div class="form-text">Maximum file size: 5 MB.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Import transactions</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header"><strong>Accepted CSV format</strong></div>
            <div class="card-body">
                <p>
                    Columns <code>date</code>, <code>description</code>, and <code>amount</code> are required.
                    <code>category</code> is optional. Expenses are negative and deposits are positive.
                </p>
                <pre class="bg-light border rounded p-3 mb-3"><code>date,description,amount,category
2026-07-01,Payroll Deposit,2500.00,Income
2026-07-02,Grocery Store,-84.37,Groceries</code></pre>
                <a href="/sample_statement.csv" class="btn btn-outline-secondary btn-sm">Open sample CSV</a>
            </div>
        </div>
    </main>
</body>
</html>

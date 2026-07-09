<?php

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/accounts/AccountRepository.php';

use App\Accounts\AccountRepository;

$repository = new AccountRepository();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountName = trim((string) ($_POST['account_name'] ?? ''));

    if ($accountName === '') {
        $error = 'Account name is required.';
    } else {
        $repository->create([
            'institution' => $_POST['institution'] ?? '',
            'account_name' => $accountName,
            'account_type' => $_POST['account_type'] ?? 'checking',
            'currency' => $_POST['currency'] ?? 'CAD',
        ]);

        header('Location: /accounts.php');
        exit;
    }
}

$accounts = $repository->all();

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
    <title>Accounts | Bank ERP Aggregator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/accounts.php">Bank ERP Aggregator</a>
        </div>
    </nav>

    <main class="container">
        <div class="row g-4">
            <section class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <strong>Add Account</strong>
                    </div>

                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="post" action="/accounts.php">
                            <div class="mb-3">
                                <label for="account_name" class="form-label">Account Name</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="account_name"
                                    name="account_name"
                                    placeholder="RBC Chequing"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="institution" class="form-label">Institution</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="institution"
                                    name="institution"
                                    placeholder="RBC, TD, Wealthsimple"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="account_type" class="form-label">Account Type</label>
                                <select class="form-select" id="account_type" name="account_type" required>
                                    <option value="checking">Checking</option>
                                    <option value="savings">Savings</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="investment">Investment</option>
                                    <option value="loan">Loan</option>
                                    <option value="cash">Cash</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="currency"
                                    name="currency"
                                    value="CAD"
                                    maxlength="3"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Create Account
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Accounts</strong>
                        <span class="badge text-bg-secondary"><?= count($accounts) ?></span>
                    </div>

                    <div class="card-body p-0">
                        <?php if (!$accounts): ?>
                            <div class="p-4 text-muted">
                                No accounts yet. Add your first account to start building the ledger.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Account Name</th>
                                            <th>Institution</th>
                                            <th>Type</th>
                                            <th>Currency</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($accounts as $account): ?>
                                            <tr>
                                                <td><?= e($account['account_name']) ?></td>
                                                <td><?= e($account['institution']) ?></td>
                                                <td><?= e($account['account_type']) ?></td>
                                                <td><?= e($account['currency']) ?></td>
                                                <td><?= e($account['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
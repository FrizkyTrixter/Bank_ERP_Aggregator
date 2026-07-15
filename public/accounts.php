<?php

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/accounts/AccountRepository.php';

use App\Accounts\AccountRepository;

$repository = new AccountRepository();
$error = null;

$editAccount = null;

if (isset($_GET['edit'])) {
    $editAccount = $repository->find((int) $_GET['edit']);

    if (!$editAccount) {
        header('Location: /accounts.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountName = trim((string) ($_POST['account_name'] ?? ''));
    $institution = trim((string) ($_POST['institution'] ?? ''));
    $accountType = trim((string) ($_POST['account_type'] ?? 'checking'));
    $currency = strtoupper(trim((string) ($_POST['currency'] ?? 'CAD')));

    if ($accountName === '') {
        $error = 'Account name is required.';
    } elseif ($institution === '') {
        $error = 'Institution is required.';
    } elseif ($accountType === '') {
        $error = 'Account type is required.';
    } elseif ($currency === '') {
        $error = 'Currency is required.';
    } else {
        $data = [
            'institution' => $institution,
            'account_name' => $accountName,
            'account_type' => $accountType,
            'currency' => $currency,
        ];

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $repository->update($id, $data);
        } else {
            $repository->create($data);
        }

        header('Location: /accounts.php');
        exit;
    }
}

$accounts = $repository->all();

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function selected(?string $current, string $value): string
{
    return $current === $value ? 'selected' : '';
}

$isEditing = $editAccount !== null;
$formTitle = $isEditing ? 'Edit Account' : 'Add Account';
$buttonText = $isEditing ? 'Update Account' : 'Create Account';

$formAccountName = $editAccount['account_name'] ?? '';
$formInstitution = $editAccount['institution'] ?? '';
$formAccountType = $editAccount['account_type'] ?? 'checking';
$formCurrency = $editAccount['currency'] ?? 'CAD';
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
            <div class="navbar-nav flex-row gap-3">
                <a class="nav-link active" href="/accounts.php">Accounts</a>
                <a class="nav-link" href="/transactions.php">Transactions</a>
                <a class="nav-link" href="/import_transactions.php">Import CSV</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="row g-4">
            <section class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <strong><?= e($formTitle) ?></strong>
                    </div>

                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="post" action="/accounts.php<?= $isEditing ? '?edit=' . e($editAccount['id']) : '' ?>">
                            <?php if ($isEditing): ?>
                                <input type="hidden" name="id" value="<?= e($editAccount['id']) ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="account_name" class="form-label">Account Name</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="account_name"
                                    name="account_name"
                                    value="<?= e($formAccountName) ?>"
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
                                    value="<?= e($formInstitution) ?>"
                                    placeholder="RBC, TD, Wealthsimple"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="account_type" class="form-label">Account Type</label>
                                <select class="form-select" id="account_type" name="account_type" required>
                                    <option value="checking" <?= selected($formAccountType, 'checking') ?>>Checking</option>
                                    <option value="savings" <?= selected($formAccountType, 'savings') ?>>Savings</option>
                                    <option value="credit_card" <?= selected($formAccountType, 'credit_card') ?>>Credit Card</option>
                                    <option value="investment" <?= selected($formAccountType, 'investment') ?>>Investment</option>
                                    <option value="loan" <?= selected($formAccountType, 'loan') ?>>Loan</option>
                                    <option value="cash" <?= selected($formAccountType, 'cash') ?>>Cash</option>
                                    <option value="other" <?= selected($formAccountType, 'other') ?>>Other</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="currency"
                                    name="currency"
                                    value="<?= e($formCurrency) ?>"
                                    maxlength="3"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <?= e($buttonText) ?>
                            </button>

                            <?php if ($isEditing): ?>
                                <a href="/accounts.php" class="btn btn-outline-secondary w-100 mt-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
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
                                            <th class="text-end">Actions</th>
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
                                                <td class="text-end">
                                                    <a
                                                        href="/accounts.php?edit=<?= e($account['id']) ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form
                                                        method="post"
                                                        action="/delete_account.php"
                                                        onsubmit="return confirm('Delete this account?');"
                                                        class="d-inline"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= e($account['id']) ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>
                                                </td>
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

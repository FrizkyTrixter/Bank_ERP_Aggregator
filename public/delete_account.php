<?php

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/accounts/AccountRepository.php';

use App\Accounts\AccountRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounts.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $repository = new AccountRepository();
    $repository->delete($id);
}

header('Location: /accounts.php');
exit;

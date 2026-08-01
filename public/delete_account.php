<?php

session_start();

require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/accounts/AccountRepository.php';
require_once dirname(__DIR__) . '/app/security/Csrf.php';

use App\Accounts\AccountRepository;
use App\Security\Csrf;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounts.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

if ($id > 0) {
    $repository = new AccountRepository();
    $repository->delete($id);
}

header('Location: /accounts.php');
exit;

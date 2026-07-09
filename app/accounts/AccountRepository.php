<?php

namespace App\Accounts;

use App\Core\Database;
use PDO;

class AccountRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT id, institution, account_name, account_type, currency, created_at
             FROM accounts
             ORDER BY account_name ASC'
        );

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, institution, account_name, account_type, currency, created_at
             FROM accounts
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $account = $stmt->fetch();

        return $account ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO accounts (institution, account_name, account_type, currency, created_at)
             VALUES (:institution, :account_name, :account_type, :currency, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            'institution' => trim((string) $data['institution']),
            'account_name' => trim((string) $data['account_name']),
            'account_type' => trim((string) $data['account_type']),
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'CAD'))),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE accounts
             SET institution = :institution,
                 account_name = :account_name,
                 account_type = :account_type,
                 currency = :currency
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'institution' => trim((string) $data['institution']),
            'account_name' => trim((string) $data['account_name']),
            'account_type' => trim((string) $data['account_type']),
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'CAD'))),
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM accounts WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
        ]);
    }
}
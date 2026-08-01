<?php

namespace App\Transactions;

use App\Core\Database;
use PDO;
use Throwable;

class TransactionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(?int $accountId = null): array
    {
        $sql = 'SELECT transactions.id,
                       transactions.transaction_date,
                       transactions.description,
                       transactions.amount,
                       transactions.category,
                       transactions.source_file,
                       accounts.account_name,
                       accounts.currency
                FROM transactions
                INNER JOIN accounts ON accounts.id = transactions.account_id';

        if ($accountId !== null) {
            $sql .= ' WHERE transactions.account_id = :account_id';
        }

        $sql .= ' ORDER BY transactions.transaction_date DESC, transactions.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($accountId === null ? [] : ['account_id' => $accountId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(int $accountId, string $sourceFile, array $rows): array
    {
        $duplicate = $this->db->prepare(
            'SELECT 1 FROM transactions
             WHERE account_id = :account_id AND import_hash = :import_hash'
        );
        $insert = $this->db->prepare(
            'INSERT INTO transactions
                (account_id, transaction_date, description, amount, category, source_file, import_hash, created_at)
             VALUES
                (:account_id, :transaction_date, :description, :amount, :category, :source_file, :import_hash, CURRENT_TIMESTAMP)'
        );

        $imported = 0;
        $skipped = 0;
        $this->db->beginTransaction();

        try {
            foreach ($rows as $row) {
                $duplicate->execute([
                    'account_id' => $accountId,
                    'import_hash' => $row['import_hash'],
                ]);

                if ($duplicate->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    'account_id' => $accountId,
                    'transaction_date' => $row['transaction_date'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'category' => $row['category'],
                    'source_file' => $sourceFile,
                    'import_hash' => $row['import_hash'],
                ]);
                $imported++;
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}

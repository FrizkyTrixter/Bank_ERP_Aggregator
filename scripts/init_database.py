import sqlite3
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[1]
DB_PATH = BASE_DIR / "database" / "bank_erp.db"

def main():
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        institution TEXT NOT NULL,
        account_name TEXT NOT NULL,
        account_type TEXT NOT NULL,
        currency TEXT DEFAULT 'CAD',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        transaction_date TEXT NOT NULL,
        description TEXT NOT NULL,
        amount REAL NOT NULL,
        category TEXT,
        source_file TEXT,
        import_hash TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES accounts(id)
    )
    """)

    # Add CSV import support to databases created by older versions.
    transaction_columns = {
        row[1] for row in cursor.execute("PRAGMA table_info(transactions)")
    }
    if "import_hash" not in transaction_columns:
        cursor.execute("ALTER TABLE transactions ADD COLUMN import_hash TEXT")

    cursor.execute("""
    CREATE UNIQUE INDEX IF NOT EXISTS idx_transactions_account_import_hash
    ON transactions (account_id, import_hash)
    WHERE import_hash IS NOT NULL
    """)

    conn.commit()
    conn.close()

    print(f"Database initialized at: {DB_PATH}")

if __name__ == "__main__":
    main()

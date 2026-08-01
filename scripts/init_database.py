import sqlite3
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[1]
DB_PATH = Path(os.environ.get("BANK_ERP_SQLITE_PATH", BASE_DIR / "database" / "bank_erp.db"))

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

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS investment_account_mappings (
        external_account_id TEXT PRIMARY KEY,
        account_id INTEGER,
        source_system TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES accounts(id)
    )
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS investment_import_batches (
        batch_id TEXT PRIMARY KEY,
        source_system TEXT NOT NULL,
        content_hash TEXT NOT NULL,
        signature TEXT,
        status TEXT NOT NULL,
        imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS investment_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_id TEXT NOT NULL,
        source_system TEXT NOT NULL,
        event_external_id TEXT NOT NULL,
        external_account_id TEXT NOT NULL,
        event_type TEXT NOT NULL,
        effective_at TEXT NOT NULL,
        currency TEXT NOT NULL,
        symbol TEXT,
        quantity TEXT,
        unit_price TEXT,
        gross_amount TEXT,
        fee_amount TEXT NOT NULL,
        net_amount TEXT,
        order_id TEXT,
        fill_id TEXT,
        description TEXT NOT NULL,
        metadata_json TEXT NOT NULL,
        content_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES investment_import_batches(batch_id),
        UNIQUE (source_system, event_external_id)
    )
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS investment_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_id TEXT,
        event_name TEXT NOT NULL,
        outcome TEXT NOT NULL,
        details_json TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    """)

    conn.commit()
    conn.close()

    print(f"Database initialized at: {DB_PATH}")

if __name__ == "__main__":
    main()

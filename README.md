# Bank ERP Aggregator

A personal finance ERP and bank account aggregation platform.

## Vision

The goal of this project is to build an open-source financial ERP that allows users to aggregate accounts from multiple financial institutions into a single interface.

The initial prototype focuses on:

- Account management
- Transaction imports
- Budgeting
- Cash flow reporting
- Net worth tracking

## Current Features

- Create, edit, and delete financial accounts
- Import transactions from CSV statements
- Validate statement rows before saving anything
- Skip rows when the exact same file is imported again
- Browse and filter imported transactions by account
- Import versioned, integrity-checked investment events from Stock Trading Pipeline
- Prevent duplicate investment batches and source events transactionally
- View a read-only investment event report

## Run Locally

Requirements: PHP 8.2+ with PDO SQLite and Python 3.

```powershell
python scripts/init_database.py
php -S localhost:8000 -t public
```

Open `http://localhost:8000`, create an account, and select **Import CSV**.

## Stock Trading Pipeline integration

The ERP never accepts direct writes from the trading package. Initialize the added
investment tables, then import an exported version `1.0.0` batch through the CLI:

```powershell
python scripts/init_database.py
php scripts/import_investment_batch.php C:\path\to\erp_batch.json
```

Set `ERP_HMAC_SECRET` on both sides to require HMAC verification. The secret is
not stored in either repository or database. Re-importing the same batch is safe
and reports skipped events. Open `/investments.php` for the read-only report.

The importer validates the source, schema version, timestamps, currencies, event
types, event hashes, batch hash, and optional signature before a transactional
import. Money and quantities are stored as canonical decimal text, not floats.

## CSV Statement Format

The required headers are `date`, `description`, and `amount`. The `category`
header is optional. Use positive amounts for deposits and negative amounts for
expenses.

```csv
date,description,amount,category
2026-07-01,Payroll Deposit,2500.00,Income
2026-07-02,Grocery Store,-84.37,Groceries
```

A ready-to-import example is available at `public/sample_statement.csv`.

Future versions will include:

- Bank API integrations
- Investment position aggregation and valuations derived from immutable events
- AI-powered financial insights
- Automation
- Forecasting

## Tech Stack

- PHP
- jQuery
- Bootstrap
- Python
- SQLite
- PostgreSQL

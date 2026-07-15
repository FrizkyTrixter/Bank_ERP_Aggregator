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

## Run Locally

Requirements: PHP 8.2+ with PDO SQLite and Python 3.

```powershell
python scripts/init_database.py
php -S localhost:8000 -t public
```

Open `http://localhost:8000`, create an account, and select **Import CSV**.

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
- Investment tracking
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

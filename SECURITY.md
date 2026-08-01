# Security

All database writes use prepared statements. CSV import and account mutations use session-bound CSRF tokens. Investment batches enter only through the CLI importer, are validated and transactionally stored, and are protected by batch/event uniqueness plus SHA-256 hashes. Set `ERP_HMAC_SECRET` out of band to require message authentication.

Never store broker, LLM, bank, or provider credentials in this repository, SQLite, batch metadata, logs, or prompts. Use `BANK_ERP_SQLITE_PATH` only for isolated deployments/tests and protect the database file with operating-system permissions.

If a batch, credential, or database may be compromised, stop imports, preserve audit records, rotate the secret, and reconcile the source events before resuming. Corrections are explicit new events; do not overwrite imported financial history.

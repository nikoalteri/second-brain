# MySQL concurrency tests (opt-in)

The default suite runs on in-memory SQLite, which ignores `FOR UPDATE`. These tests use **two real MySQL
connections** (the test process and short-lived worker processes, see `worker.php`) to prove that the
statement import's duplicate check and insert cannot interleave with any other writer of the same account.

They are not part of `vendor/bin/phpunit`; they skip themselves unless the default connection is MySQL.

## Run

```bash
# 1. A throwaway MySQL (any 8+/9+ server works; this is a scratch instance on port 3399).
mysqld --no-defaults --initialize-insecure --datadir=/path/to/scratch/data
mysqld --no-defaults --datadir=/path/to/scratch/data --port=3399 --bind-address=127.0.0.1 \
       --mysqlx=OFF --socket=/short/path/mysql.sock --innodb-lock-wait-timeout=20 &
mysql -uroot -h127.0.0.1 -P3399 -e 'CREATE DATABASE fluxa_concurrency'

# 2. The suite. It migrates the database fresh once and truncates between tests.
php -d memory_limit=-1 vendor/bin/phpunit -c phpunit.mysql.xml
```

Defaults (host 127.0.0.1, port 3399, database `fluxa_concurrency`, user `root`, empty password) can be
overridden with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. **The database is wiped:
point it at a scratch database only.** Socket paths on macOS must be shorter than ~100 characters.

## How an interleaving is built

A worker freezes inside its transaction (`pause_on` = `creating`: lock held and, for the import, the
re-check done, just before the insert; `end`: inserted but not committed). The test then starts the second
writer and asserts it is still **waiting**, releases the first, and checks the committed outcome.

---
name: test-installation
description: "Use when: testing a new or fresh installation of the application, installing into a throwaway test database and running the test suite against it."
---

# Test Installation

Installs the application into a new, throwaway MySQL database the same way CI does
(`.github/workflows/testing.yml`), runs the tests against it, then cleans up. `.env` is never
edited: the test database is selected per command with a `DB_DATABASE` environment override,
which Laravel's `.env` loading does not overwrite.

## Inputs

Ask both before starting, in one question each (AskUserQuestion), unless already given:

- **PHP version(s):** one or more of 8.2, 8.3, 8.4, 8.5, or just the current `php`
- **Mode:** serial (slow) or parallel (fast)

## Workflow

1. **Name the test database:** `<DB_DATABASE from .env>_inst_<yyyymmddhhmm>`. Call it `$TEST_DB`
   below. Read only `DB_DATABASE` from `.env`; never print `DB_PASSWORD`.

2. **Create it** through the application's own connection, so no password is typed or shown:

   ```bash
   php artisan config:clear
   php artisan tinker --execute 'DB::statement("CREATE DATABASE `TEST_DB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'
   ```

   Write the name in place of `TEST_DB`: it is inside single quotes, so a shell variable would
   not expand. If the database user lacks the CREATE privilege, stop and tell the user. Do not try
   another account.

3. **Check the override.** Shell state does not carry between Bash calls, so prefix **every**
   command from here on with `DB_DATABASE=$TEST_DB`. Before installing, confirm:

   ```bash
   DB_DATABASE=$TEST_DB php artisan config:show database.connections.mysql.database
   ```

   It must report `$TEST_DB`. If it reports the `.env` database, a cached config is in the
   way: run `php artisan config:clear` and check again. Never install into the `.env` database.

4. **Install,** as CI does:

   ```bash
   DB_DATABASE=$TEST_DB APP_ENV=testing php artisan app:install --bypass-prompts
   DB_DATABASE=$TEST_DB APP_ENV=testing php artisan app:install-testing
   DB_DATABASE=$TEST_DB php artisan config:clear
   ```

   `app:install-testing` takes more than 10 minutes here: run it in the background and check
   its exit code before going on. Don't pipe these commands through `tail` or `grep`: the
   pipe's exit status hides a failure (or start the line with `set -o pipefail;`).

   Report any warning the installer prints, such as a default Strong's dictionary that was
   not installed. "Could not install feature: strongs (xx)" usually means a
   `FeatureDefinitions::STRONGS_PRESETS` entry names a module with no file in
   `content/strongs/`. The log says which file it looked for.

5. **Run the tests,** once for each PHP version chosen, with CI's environment:

   - Serial: `DB_DATABASE=$TEST_DB APP_TEST_HTTP=true APP_EXPERIMENTAL=true php<ver> artisan test --compact --fail-on-deprecation --display-skipped`
   - Parallel: `DB_DATABASE=$TEST_DB APP_TEST_HTTP=true APP_EXPERIMENTAL=true php<ver> ./vendor/bin/paratest`

   Serial runs take a long time: run them in the background. Report failures verbatim.

6. **Clean up,** only after asking the user (they may want to inspect the database):

   ```bash
   php artisan tinker --execute 'DB::statement("DROP DATABASE `TEST_DB`");'
   php artisan config:clear
   ```

   Run the DROP without the `DB_DATABASE` override: it connects through the `.env` database.
   Double-check the name ends in `_inst_<timestamp>` before dropping anything.

## Safety

- Never edit `.env`, and never print secrets from it.
- Never install into, migrate, or drop the `.env` database.
- Drop only the database this run created.

## Example triggers

- "test a fresh install"
- "run the install test"
- "does a new installation pass the tests?"

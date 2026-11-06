---
name: test-upgrade
description: "Use when: testing an upgrade from one version to another (git branches or tags): installing the older version into a throwaway test database, migrating it to the newer one and running the test suite."
---

# Test Upgrade

Installs the **current** version (a git branch or tag) into a new, throwaway MySQL database,
checks out the **upgrade** version, migrates, and runs the tests against the upgraded
database. The install follows the `test-installation` skill (`.ai/skills/test-installation/SKILL.md`):
read it for the database naming, creation, `DB_DATABASE` override and install commands, which
are not repeated here.

## Inputs

- **current:** the branch or tag being upgraded from (required)
- **upgrade:** the branch or tag being upgraded to (required)
- **PHP version(s)** and **mode** (serial or parallel), as in `test-installation`
- **Test the current version too?** (yes/no) A baseline run before the upgrade: a failure
  there was already present, not caused by the upgrade. It adds one full test run per PHP
  version.

Ask for any that are missing before starting, in one AskUserQuestion call.

## Running each step

These rules come from runs that went wrong. Follow them for every command below.

- **One step per command, checked before the next.** Do not chain checkout, `composer install`
  and the install into one `&&` line run in the background: when one link fails unnoticed, the
  rest runs against the wrong code.
- **Never pipe a step through `tail`, `grep` or `head`.** The pipe's exit status is the last
  command's, so a failed `composer install | tail -3` reads as success. Let the output through,
  or start the command with `set -o pipefail;`.
- **Run long steps in the background:** `app:install-testing` takes more than 10 minutes here,
  and so does a test run. Read the output when it completes, and check the exit code before
  going on.
- **Run PHP versions one after another, never at once:** every run uses the same test
  database. Send each run's full output to its own log (`/tmp/claude-1000/upgrade-test/<stage>-php<ver>.log`)
  and summarise from the logs: a loop that only echoes the summary line is fine, as long as
  the full output is kept.

## Workflow

1. **Record where you are:** `git branch --show-current` (or the commit, if detached). Call it
   `$ORIGINAL`.

2. **Check the working tree is clean:** `git status --porcelain`. If anything is listed, stop
   and ask the user whether to stash it or abort. Never discard changes.

3. **Check both refs:**
   - `git rev-parse --verify <ref>`, one call per ref: given two refs, it fails with
     "Needed a single revision".
   - A remote-tracking ref (`github/develop`) can be used directly; checking it out leaves a
     detached HEAD, which is fine.
   - For a branch, compare the local branch with its remote (`git rev-list --left-right --count
     <branch>...<remote>/<branch>`). If they differ, tell the user which one is newer and ask
     which to use. `git fetch` may be refused in this environment, so don't depend on it.

4. **Find composer.** It is not on the PATH here; the project ships `composer.phar`, which is
   tracked in git, so checking out a ref without it would delete it mid-run. Copy it out of
   the repository first, and use that copy for every `composer install` below:

   ```bash
   command -v composer || cp composer.phar /tmp/claude-1000/composer.phar
   ```

   Call the result `$COMPOSER`: `composer` if it was found, otherwise
   `php /tmp/claude-1000/composer.phar`. If neither exists, stop and tell the user.

5. **Create the test database** (`test-installation`, steps 1-3).

6. **Install the current version:**

   ```bash
   git checkout <current>
   $COMPOSER install --no-interaction
   ```

   Then install (`test-installation`, step 4).

   If the user asked for a baseline, run the tests now (`test-installation`, step 5), with
   `current` still checked out, logging to `current-php<ver>.log`.

7. **Upgrade:**

   ```bash
   git checkout <upgrade>
   $COMPOSER install --no-interaction
   DB_DATABASE=$TEST_DB php artisan migrate --force
   DB_DATABASE=$TEST_DB php artisan config:clear
   ```

   A failed migration is the main finding: report it verbatim and ask before going on.

8. **Install the testing data again,** on the upgraded database:

   ```bash
   DB_DATABASE=$TEST_DB APP_ENV=testing php artisan app:install-testing
   DB_DATABASE=$TEST_DB php artisan config:clear
   ```

   The upgrade version's tests expect its own testing data: new testing Bibles and new
   features, which `migrate` does not install. Without this step those tests fail for want of
   data, not because the upgrade broke anything.

   **Report what it had to add** (Bibles installed, "Features enabled: n of m"), as
   information rather than a fault. An upgrade is not a new install: it is expected not to add
   content a new install gets, such as the default Strong's dictionary (en_orig), which
   `app:install-testing` installs here.

9. **Run the tests** (`test-installation`, step 5), logging to `upgrade-php<ver>.log`. With a
   baseline, compare: name the failures that are new after the upgrade, and the ones that
   were there before it.

10. **Clean up.** Ask the user first, then:

   - Drop the test database (`test-installation`, step 6)
   - `git checkout $ORIGINAL`
   - `$COMPOSER install --no-interaction`
   - Delete the copied `/tmp/claude-1000/composer.phar`, if one was made, and the
     `/tmp/claude-1000/upgrade-test` logs
   - If changes were stashed in step 2, tell the user; pop the stash only if they ask.

## Aborting

If the user aborts, or a step fails and the user says to stop:

- Stop any background task.
- Return to `$ORIGINAL` straight away. The working tree belongs to the other ref until you do,
  so files will look changed or missing.
- Re-run `$COMPOSER install` if a `composer install` ran on the other ref, so `vendor/`
  matches `$ORIGINAL` again.
- Then ask before dropping the test database, as in step 10.

## Safety

- Everything in `test-installation`'s Safety section applies.
- Never check out over uncommitted changes, and never reset or discard them.
- Always return to `$ORIGINAL`, even when a step fails, once the user has agreed to clean up.

## Example triggers

- "test upgrading from master to this branch"
- "run the upgrade test from 6.2.0 to develop"
- "does the upgrade from the last release migrate cleanly?"

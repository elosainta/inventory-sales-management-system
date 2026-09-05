# Vault automation

This vault rebuilds its factual half on every commit. Two moving parts.

## Install (once per clone)

```bash
git config core.hooksPath .githooks
```

Git hooks live in `.git/hooks`, which is not versioned — so the hook is committed to `.githooks/` and `core.hooksPath` points git at it. One command, and it survives every future pull.

Verify:

```bash
git config core.hooksPath        # → .githooks
```

## Part 1 — `.githooks/pre-commit`

```sh
command -v php >/dev/null 2>&1 || exit 0
php artisan vault:sync >/dev/null 2>&1 || exit 0
git add docs/vault/_generated 2>/dev/null
exit 0
```

### Why `pre-commit` and not `post-commit`

`post-commit` would need to make a *second* commit to record the refreshed vault, and that second commit would fire the hook again. Guarding the loop needs an environment flag and `--no-verify`.

`pre-commit` regenerates **before** the snapshot is taken and stages the result, so the vault lands **inside the same commit as the code it describes**. No follow-up commit, no recursion, nothing to guard.

> [!note] The price: git position in [[Repo snapshot]] lags by one commit
> The hook runs before the commit exists, so "Commits" and "HEAD" describe the *previous* one. Nothing can fix this from inside a pre-commit hook — the command cannot know whether it is being run by the hook or by hand, so adding 1 would just make manual runs wrong instead. The row is labelled rather than corrected, and `php artisan vault:sync` after committing shows the true HEAD.
>
> Only that one section is affected. Everything else — routes, gates, models, migrations, file counts — reads the working tree, which is exactly what is being committed.

### Why it can never fail

Every line ends in `|| exit 0`. A `pre-commit` hook returning non-zero **aborts the commit**.

> [!danger] A documentation tool must never block a commit
> No PHP on PATH, a boot error, a read-only filesystem — any of these must cost you a stale vault, never a lost commit. The hook is deliberately unable to fail.

The consequence: **a silent failure looks like success.** If `_generated/` stops updating, run `php artisan vault:sync` by hand to see the error.

## Part 2 — `php artisan vault:sync`

`app/Console/Commands/VaultSync.php`. Writes six notes into `docs/vault/_generated/`, each with a "do not edit" callout.

| Note | Derived from |
|---|---|
| [[Routes]] | `Route::getRoutes()` — the live router |
| [[Gates matrix]] | `Gate::abilities()`, evaluated per role |
| [[Models index]] | `app/Models/*.php` — source regex |
| [[Migrations index]] | migration filenames + `Schema::` calls |
| [[Domain actions index]] | `app/Domain/*/Actions/*.php` |
| [[Repo snapshot]] | `git`, file counts, `ReleaseNotes::CURRENT_VERSION` |

### Three implementation decisions worth knowing

**Gates are evaluated, not transcribed.**

```php
$user = new User(['role' => $role, 'is_demo' => false]);
Gate::forUser($user)->allows($ability);
```

Synthetic users, never saved. No gate reads a relation, so nothing fires a query — **the command must run without a database**, since a pre-commit hook fires whether or not MariaDB is up. Each check is wrapped in `try/catch` and degrades to `?`.

Until 1.10.48 this needed `$user->setRelation('permissions', collect())` to stop the five `view-*` gates hitting `user_permissions`. Deleting the viewer role deleted that lookup and the workaround with it.

**Relations are read by regex, not by calling methods.**

Reflecting over zero-argument model methods and calling them would find relations — and would also call `Recipe::recalculatePlateCost()`, which **writes to the database**. A documentation command must not have side effects. The regex is uglier and strictly safer.

**Git is shelled out to without quotes.**

```php
$git('log -15 --date=short --pretty=format:%ad%x09%h%x09%s')
```

`shell_exec` goes through `cmd.exe` on Windows and `sh` elsewhere. The original `--pretty=format:'| %ad | …'` failed on Windows — cmd does not strip single quotes and reads `|` as a pipe. Tab-separated (`%x09`) with no quoting works in both shells; PHP splits the columns afterwards.

## The contract

| | Curated | Generated |
|---|---|---|
| Where | every folder except `_generated` | `docs/vault/_generated/` |
| Written by | humans | `vault:sync` |
| Contains | why, trade-offs, formulas, traps | what, right now |
| Edit? | yes | **never — overwritten** |

Generated notes link **out** to curated ones (`see [[Authorization gates]]`). Curated notes link **in** to generated tables. Neither duplicates the other: a route table nobody maintains stays true; a rationale nobody can derive stays written.

## When it drifts

The generated half cannot drift — it is rebuilt from source. The **curated** half can, and nothing detects it.

Line references (`Sale.php:31-34`) are the most fragile part. They are always paired with a method name, so a shifted line number is still findable by searching for the method.

If a formula note and the code disagree, **the code is what runs and the note is the bug**.

## Extending it

Add a private method returning a markdown string, then one `$this->write(…)` call in `handle()`. Keep it side-effect free and keep it working without a database.

Worth adding if it earns its keep: a Blade view index, a form-request rules table, an audited-vs-not diff.

## See also

[[How to use this vault]] · [[Local development]] · [[Home]] · [[Repo snapshot]]

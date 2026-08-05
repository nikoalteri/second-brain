# Deferred Items — Phase 17

## Pre-existing failing test: FinanceReportPageTest (out of scope)

- **Test:** `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report_renders_budget_month_context_alerts_and_export_labels`
- **Failure:** `assertSee('exceeded')` fails — the rendered admin finance report page does not contain the string "exceeded".
- **Confirmed pre-existing:** Reproduced on `HEAD` before any 17-01/17-02 changes (via `git stash`), so it is unrelated to this phase's work. Flagged independently by both plan 17-01 and plan 17-02's executors.
- **Scope decision:** Not touched by phase 17 (no files in `app/Filament/**` or the finance-report budget-alert logic were modified). Being investigated separately (see spawned task `task_d774d875`).

## Pre-existing test discovery gap: SecurityChecklistTest (out of scope)

- **Test:** `tests/Unit/SecurityChecklistTest.php` uses the `#[Test]` attribute without importing `PHPUnit\Framework\Attributes\Test`, so PHPUnit does not discover its test methods (`php artisan test --filter=SecurityChecklistTest` reports "No tests found").
- **Confirmed pre-existing:** Unrelated to the ChatIntent/IntentRouter contract layer added by plan 17-02.
- **Scope decision:** Not fixed here per scope boundary rules — flagged for separate follow-up.

## Worktree-only environment gap (not a code issue)

- **Test:** `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response`
- **Failure:** `Vite manifest not found at: .../public/build/manifest.json`
- **Cause:** `public/build/` is gitignored and only exists in the main checkout (produced by `npm run build`), not in a fresh git worktree. Unrelated to any phase 17 code changes.
- **Scope decision:** Left as-is — building frontend assets is outside this phase's plans; this is worktree tooling, not an application bug.

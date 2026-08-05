# Deferred Items — Phase 17

## Pre-existing failing test (out of scope for 17-01)

- **Test:** `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report_renders_budget_month_context_alerts_and_export_labels`
- **Failure:** `assertSee('exceeded')` fails — the rendered admin finance report page does not contain the string "exceeded".
- **Confirmed pre-existing:** Reproduced on `HEAD` before any 17-01 changes (via `git stash`), so it is unrelated to the `UpcomingPaymentsService` extraction.
- **Scope decision:** Not touched by plan 17-01 (no files in `app/Filament/**` or the finance-report budget-alert logic were modified by this plan). Left as-is per the executor's scope boundary rule.

## Worktree-only environment gap (not a code issue)

- **Test:** `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response`
- **Failure:** `Vite manifest not found at: .../public/build/manifest.json`
- **Cause:** `public/build/` is gitignored and only exists in the main checkout (produced by `npm run build`), not in this fresh git worktree. Unrelated to the `UpcomingPaymentsService` extraction.
- **Scope decision:** Left as-is — building frontend assets is outside plan 17-01's file set and this is worktree tooling, not an application bug.

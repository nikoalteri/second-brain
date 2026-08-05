# Deferred Items — Phase 17 Plan 02

## Pre-existing, out of scope for this plan

- `tests/Unit/SecurityChecklistTest.php` uses `#[Test]` attribute without importing `PHPUnit\Framework\Attributes\Test`, so PHPUnit does not discover its test methods (`php artisan test --filter=SecurityChecklistTest` reports "No tests found"). Pre-existing before this plan's changes; unrelated to the ChatIntent/IntentRouter contract layer. Not fixed here per scope boundary rules.
- `tests/Feature/Filament/FinanceReportPageTest.php::admin_finance_report_renders_budget_month_context_alerts_and_export...` fails on `assertSee('exceeded')` in the full suite run. Pre-existing failure, unrelated to this plan's files.

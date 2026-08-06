# Deferred Items — Phase 18

## From Plan 18-01

- **`tests/Feature/Filament/FinanceReportPageTest.php` — `admin_finance_report_renders_budget_month_context_alerts_and_export_labels` fails** (`assertSee('exceeded')` not found in rendered HTML). Discovered while running the full test suite as part of 18-01's verification step. This test file is unrelated to 18-01's scope (`app/GraphQL/Queries/TransactionCategories.php`, `graphql/schema.graphql`, `tests/Feature/Api/GraphQLApiTest.php`) and was not modified by this plan. Out of scope per the deviation rules' scope boundary — logged here rather than fixed. Possibly caused by budget-alert copy/threshold changes from a concurrent phase-18 plan, or a pre-existing failure independent of this plan's changes.

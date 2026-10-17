# 24-03 Summary — Dashboard forecast card

- `stores/cashFlowForecast.js`: independent fetch with a session guard (reset on `clearTokens`, same pattern as notifications); a failure sets an isolated error flag and never touches charts or reminders.
- `utils/cashFlowForecast.js`: pure view model (one row per currency, coverage copy, empty-state test).
- `components/dashboard/CashFlowForecastCard.vue`, placed in `DashboardView.vue` below the budget alerts: per-currency remaining / available / counted with `formatCurrency(amount, currency)`, item list with reasons, overdue box, "Incomplete estimate" badge, coverage messages and a disclaimer (future income/spending and unreconciled bank data excluded). Empty and error states are explicit; no "safe to spend" claim. The 3-day reminders are untouched.

## Validation

- `node tests/Frontend/cash-flow-forecast.mjs` (currency separation, negative remainder, incomplete labelling, empty state, stale-session and failure behaviour) and `notifications-store-session.mjs`: pass.
- `npm run build`: pass.
- Backend: full suite green.
- **Not verified:** visual check at desktop and mobile sizes in a browser (Claude in Chrome was not available in this session). Follow-up needed.

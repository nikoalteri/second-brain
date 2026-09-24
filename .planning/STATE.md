---
gsd_state_version: 1.0
milestone: v5.1
milestone_name: — Planning Realignment ⏳
status: planning
stopped_at: Phases 22-28 context gathered; audit hardening delivered
last_updated: "2026-09-24T00:00:00.000Z"
last_activity: "2026-09-20 -- Third audit round (PRs 49-55) and ad hoc UX round (PRs 36-48) delivered. Earlier, 2026-09-19 -- Second audit hardening round delivered through PRs 22-26 and 28-34 (vault binding, 2FA atomicity, refresh rotation, GraphQL limits, MFA on hub, renewal idempotence, card locking, pagination limits, export formula safety, scheduler heartbeat and smoke test, explicit owner, audit trail); backups and trusted proxies deferred by decision. Earlier, 2026-09-18 -- Audit hardening delivered through PRs 11-20 (see ROADMAP.md, Audit hardening) and the planning docs realigned; Phases 22-28 CONTEXT.md now carry audit-derived requirements. Earlier, 2026-09-17 -- Product-enhancement brainstorm added Phases 22-28 to ROADMAP.md (proactive notifications, subscription price-hike detection, cash-flow forecast, bank-statement import, debt/subscription totals, automated statement reconciliation, automatic transaction categorization). Discuss-phase completed for all seven before planning any of them, per user request: 22 through 28 CONTEXT.md + DISCUSSION-LOG.md all written. A \"report annuale/fiscale\" idea was investigated and found already fully shipped (FinanceReportController's export already has a per-category \"Distribution\" section in every format) — no new phase created for it. Key scouting finds that reshaped scope: Notification model exists but is never written to anywhere (Phase 22); UpcomingPaymentsService (Phase 17) reused for Phase 24; maatwebsite/excel already a dependency for Phase 25; IntentRouter pattern and SubscriptionService::calculateMonthlyCost() reused for Phase 26; Phase 27 required a retroactive addendum to Phase 25 (D-06, an optional statement-ending-balance field) since imported transactions can't independently verify the balance they contributed to. Separately shipped and since merged (PR 4): feat/hub-dark-mode re-enabled the Filament admin dark-mode toggle. Next: plan phases in dependency order (22 before 23; 25 before 27/28; 24/26 together), then execute progressively."
progress:
  total_phases: 16
  completed_phases: 9
  total_plans: 24
  completed_plans: 24
  percent: 56
---

# v5.1 Project State

**Project:** Fluxa — Personal Finance Tracker  
**Milestone:** v5.1 — Planning Realignment  
**Status:** Phase 21 complete; Phase 22 context gathered, not yet planned
**Updated:** 2026-09-24

---

## Project Reference

See: `.planning/PROJECT.md` (planning realignment milestone definition)

**Core value:** Keep personal finance data and behavior consistent across every surface, with one shared source of truth for preferences, reporting, and user-facing workflows.  
**Current focus:** None committed — Phase 21 was the last committed phase; pick next focus from `.planning/codebase/CONCERNS.md` or ROADMAP's Deferred Concerns

## Current Position

Phase: 22-28 — CONTEXT GATHERED (none yet planned)
Plan: none yet — next step is `/gsd-plan-phase 22` (plan in dependency order: 22, then 23, then 25, then 24/26 together, then 27/28)
Status: Phase 21 remains the last fully-executed phase; its own follow-up (`php artisan credit-cards:balance-audit` in production/uat) is still outstanding. Phases 22-28 are newly scoped from a product-enhancement brainstorm — all discussed, none planned/executed.
Last activity: 2026-09-20 -- third audit round (PRs #49-#55: GET size limit, cycle expense cap, explicit owner, atomic refresh, scheduled-command isolation, deterministic lock-order test) and an ad hoc UX round (PRs #36-#48); before that, 2026-09-19 -- Second audit hardening round delivered (PRs #22-#26, #28-#34); before that, 2026-09-18 -- Audit hardening delivered (PRs #11-#20) and planning docs realigned; before that, 2026-09-17 -- Discuss-phase completed for Phases 22 through 28 in sequence (user chose to plan all before implementing any); a small unrelated ad-hoc fix (Filament admin dark-mode toggle) was also shipped and has since been merged (PR #4)

## Session Resume

**Stopped at:** Phases 22-28 context gathered
**Resume file:** .planning/phases/22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers/22-CONTEXT.md (plan this one first — Phase 23 depends on it)

## Accumulated Context

- Phase 13 produced an evidence ledger at `.planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md`.
- Phase 13 produced a narrative audit at `.planning/phases/13-current-state-audit/13-CURRENT-STATE-AUDIT.md`.
- Phase 14 realigned `.planning/PROJECT.md`, `.planning/REQUIREMENTS.md`, and `.planning/STATE.md` to match Phase 13 evidence only.
- The strongest validated current capabilities remain auth/settings, account CRUD/scoping, dashboard/report exports, admin finance-report rendering, and admin access control.
- Transactions, loans, credit cards, subscriptions, monthly budget mutations, and GraphQL remain structural-only, lower confidence context until later proof upgrades them.
- Prior localization planning remains concise superseded history; current repo evidence is English-only.
- Phase 15 reset `.planning/ROADMAP.md` to a conservative near-term roadmap grounded in the validated versus structural-only boundary.
- Deferred concerns now live in explicit non-committed buckets rather than active roadmap phases.
- Phase 16 is the direct next planning step and should follow a proof-first path for structural-only finance areas.
- Phase 16 discussion locked a narrow first slice, backend-first proof surface, explicit structural-only downgrade handling, and priority on credit-card plus access/scoping proof.

### Roadmap Evolution

- Phase 17 added: Custom read-only finance chatbot engine — a self-built (no BotMan dependency) intent-router/state-machine conversational engine, inspired by the Leo project's conversation-flow pattern, for read-only queries over existing finance data (account balances, upcoming payments, spending summaries, credit-card usage).
- Phase 18 added: Hardening & Security Proof — closes auth-scoping, superadmin-bypass, and credit-card lifecycle race-condition gaps flagged in the Deferred Hardening/Security/Performance Concerns bucket, with real tests, before further feature work.
- Phase 21 added: Credit card balance recompute correctness — `CreditCardCycleService::syncCardBalance()` (introduced in Phase 18 to fix a payment-status race condition) recomputes `current_balance` purely as `sum(expenses) - sum(paid principal)`, with no opening-balance term. Every `CreditCardPayment` create/update fires this via `CreditCardPaymentObserver`, silently zeroing/dropping any card balance not backed by `CreditCardExpense` rows. Root cause of the two Phase 19 test failures (`CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes`, `CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user`), confirmed still failing on 2026-09-17 full-suite run (325 tests, 323 pass, these 2 fail).
- Phases 22-28 added (2026-09-17, from a product-enhancement brainstorm, not `/gsd-new-milestone`): Phase 22 — proactive notifications (wire the existing unused `Notification` model to budget/credit-card/loan/subscription triggers, in-app only, daily digest); Phase 23 — subscription price-hike detection (builds on Phase 22's delivery); Phase 24 — cash-flow forecast aggregating known upcoming commitments against current balances; Phase 25 — bank statement file import/reconciliation (explicitly file-based, not Open Banking/live bank-feed — that remains out of scope per the Deferred Longer-Term Product Ideas bucket; D-06 added retroactively for Phase 27); Phase 26 — debt/subscription total figures on both the chatbot (5 new intents) and Dashboard, planned alongside Phase 24 since both touch the Dashboard; Phase 27 — automated reconciliation of computed balances against a Phase-25-imported statement's declared ending balance; Phase 28 — automatic transaction categorization (rules first, then historical match), silent for manual entry, suggested for Phase 25 import drafts. All seven now have CONTEXT.md + DISCUSSION-LOG.md; none planned yet. A "report annuale/fiscale" idea was found already fully shipped (existing FinanceReport export's "Distribution" section) — no phase created. Separately, `feat/hub-dark-mode` (merged via PR #4, not part of any phase) re-enabled the Filament admin dark-mode toggle that `->darkMode(false, true)` had fully disabled.
- Audit hardening (2026-09-17 to 2026-09-18, ad hoc, no phase number): a project audit found cross-user references, non-atomic writes, unsynchronised transfer legs, token/deactivation gaps, a stale-balance interest input and vulnerable dependencies. All were fixed through PRs #11-#20 with tests that fail without the fix; `php artisan data:audit` ran clean on UAT. A second round (PRs #22-#26, #28-#34) closed refresh-token rotation, MFA on `/hub`, concurrency and idempotence, pagination and export limits, the scheduler heartbeat and smoke test, and added an immutable audit trail. Details and what remains open are in ROADMAP.md ("Audit hardening"). Phases 22-28 CONTEXT.md gained an "Audit-derived requirements" section, and a currency decision is needed before planning Phases 24-27.
- Phase 19 added: Revolving Credit Card Interest Engine Correctness — the user supplied 5 real Amex statements (docs/reference/credit-card-statements/) plus docs/reference/credit-card-revolving-validation.md documenting expected interest math. Orchestrator-level analysis (2026-08-06) confirmed 4 concrete discrepancies against current code, verified line-by-line: (1) CreditCardCycleService.php:201 uses startOfMonth() instead of the real day-7-to-day-6 billing period; (2) RevolvingCreditCalculator::calculateDailyBalances() never applies payments within the day loop, only expenses; (3) calculatePaymentBreakdown() doesn't subtract stamp duty from the fixed payment when computing principal (233.93 expected vs 235.93 computed — a real 2 EUR/cycle drift), and total_due wrongly adds stamp duty on top instead of treating it as included; (4) calculateInterestDirectMonthly() applies the annual rate directly as a monthly rate (would be ~12x too high), a landmine present but not the default (daily_balance is default). Two real statements (2026-04-06, 2026-05-06) were read and cross-checked against the validation doc's table — both matched exactly (14.07 EUR / 21.98 EUR interest, 1183.30 / 1909.98 EUR average principal, 31 / 30 days).

## Decisions

- Phase 13 only marks a capability as validated when current code and current tests prove it.
- GraphQL and unproven finance surfaces stay structural-only until stronger proof exists.
- Localization planning remains preserved as superseded history rather than active roadmap scope.
- Phase 14 updated only the planned top-level docs and left roadmap reshaping for Phase 15.
- Phase 15 should promote only evidence-grounded near-term phases and keep most concern inventory outside committed scope.
- The direct handoff after Phase 15 is `/gsd-plan-phase 16`.
- [Phase 15]: Keep only Phase 16 as committed post-reset roadmap scope until proof changes the confidence boundary.
- [Phase 15]: Treat structural-only finance domains as proof-first candidates, not enhancement-ready roadmap promises.
- [Phase 15]: Keep concern inventory visible in explicit deferred buckets and end with /gsd-plan-phase 16.
- [Phase 16]: Prioritize a smaller high-risk proof slice rather than a full structural-domain sweep.
- [Phase 16]: Prefer REST/API plus permission/scoping proof, with GraphQL and SPA proof secondary unless directly needed.
- [Phase 16]: Keep weak or broken domains structural-only instead of forcing promotion.
- [Phase 16]: Treat foreign account binding as a real security boundary and reject it in request validation. — Phase 16 is backend-first proof work, so credit-card promotion could not rely on account existence checks that ignored ownership.
- [Phase 16]: Promote only the exact credit-card REST slice proven by current tests; keep broader credit-card depth structural-only. — Phase 16 proved owner-scoped REST access and one issue-to-mark-paid workflow, but it did not justify upgrading broader credit-card, SPA, or GraphQL surfaces.

## Issues / Blockers

- None blocking. Open items raised by the audit and not yet addressed (decimal money arithmetic, tokens in `localStorage`, report query optimisation after measurement, `calculatePaymentBreakdown()` exposure, a MySQL two-connection concurrency test, the currency decision) are listed in ROADMAP.md under "Audit hardening". Backups and trusted-proxy configuration are deferred by decision (2026-09-19). Phases 17-21 are complete (chatbot, hardening & security proof, revolving interest engine correctness, multi-currency display, credit-card balance recompute correctness). Phase 21's own manual follow-up (`php artisan credit-cards:balance-audit` in production/uat) is still outstanding and not tracked by any further phase. Phases 22-28 each have a CONTEXT.md ready for planning — plan in dependency order starting with `/gsd-plan-phase 22`.

## Performance Metrics

| Phase | Plan | Duration | Tasks | Files |
| --- | --- | --- | --- | --- |
| 13-current-state-audit | 01 | 2m | 3 | 3 |
| 14-planning-docs-realignment | 01 | pending summary | 3 | 3 |
| Phase 15-roadmap-reset-concern-triage P01 | 2m | 3 tasks | 3 files |
| Phase 16 P01 | 5 min | 2 tasks | 5 files |
| Phase 16 P02 | 6 min | 2 tasks | 3 files |

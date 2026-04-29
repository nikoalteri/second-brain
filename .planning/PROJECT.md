# Fluxa — Personal Finance Tracker

## What This Is

Fluxa is a Laravel application with a Vue SPA, REST API, and Filament admin panel for personal finance workflows. The current top-level description is intentionally conservative: Phase 13 validated auth and settings flows, account CRUD and scoping, dashboard/report data, finance exports, admin report rendering, and admin access control.

## Core Value

Keep personal finance data and behavior consistent across every surface, with one shared source of truth for preferences, reporting, and user-facing workflows.

## Current Milestone: v5.1 — Planning Realignment

**Goal:** Realign the active planning artifacts with Phase 13 evidence so maintainers can trust project scope before roadmap reset work begins.

**Current outcomes:**
- Rewrite top-level planning docs from validated repo evidence instead of stale milestone assumptions
- Keep structural-only product areas visible as lower-confidence context rather than validated scope
- Preserve prior localization work only as superseded history
- Leave a clean handoff into Phase 15 roadmap reset and concern triage

## Current Scope

### Validated

- `validated`: SPA users can authenticate through `/api/v1/auth/*`, refresh and revoke tokens, fetch profile data, and update frontend settings
- `validated`: Accounts support authenticated REST CRUD with filtering, sorting, ownership scoping, and intended superadmin cross-user access
- `validated`: Dashboard and finance report APIs return upcoming-payment and chart data, and finance exports download as CSV, XLSX, and PDF
- `validated`: The admin surface enforces tested access rules and renders the finance report page with budget context and export labels

### Structural-only context

- `structural-only`: Current code structure also shows routes, views, and schemas for transactions, loans, credit cards, subscriptions, monthly budget mutations, broader SPA finance pages, admin finance navigation, and GraphQL finance operations
- `lower-confidence`: Those areas remain context for maintainers, not validated shipped scope, until later proof upgrades them beyond the Phase 13 evidence boundary

## Active Planning Requirements

- [x] `PROJECT.md` describes the current product and milestone with evidence-backed wording
- [x] `REQUIREMENTS.md` keeps active scope limited to documentation trust outcomes
- [ ] `ROADMAP.md` still needs Phase 15 reset work from the new baseline
- [x] `STATE.md` hands off to the next planning step clearly

## Out of Scope

- Reintroducing prior localization planning into active scope
- Promoting structural-only areas into validated product promises without stronger proof
- Cleanup, refactor, or feature commitments disguised as documentation work

## Context

- Phase 13 is the source of truth for top-level planning confidence, especially `.planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md`
- The strict confidence split is intentional: validated claims are evidence-backed, while structural-only areas remain lower-confidence context
- The next planning move is Phase 15, which resets the roadmap and triages deferred concerns from this baseline

## Superseded History

Earlier localization planning remains preserved only as superseded history. Current repository evidence is English-only, so localization is not part of active top-level scope.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Use Phase 13 validated capabilities as the only top-level validated scope | Restores trust by grounding claims in current proof instead of inherited assumptions | Adopted |
| Keep transactions, loans, credit cards, subscriptions, budgets, and GraphQL in structural-only context unless re-proven | Prevents current planning docs from overstating confidence | Adopted |
| Preserve localization only as superseded history | Maintains project continuity without reviving inactive scope | Adopted |

## Evolution

This document should change only when milestone framing or validated scope materially changes.

**At future phase boundaries:**
1. Promote claims to `validated` only when current code and current proof support them
2. Keep `structural-only` and `lower-confidence` notes concise and separate from active commitments
3. Move superseded work into brief history notes instead of active scope
4. Update the milestone handoff when the roadmap baseline changes

---
*Last updated: 2026-04-29 after Phase 14 documentation realignment*

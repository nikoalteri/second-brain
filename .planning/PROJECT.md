# Fluxa — Personal Finance Tracker

## What This Is

Fluxa is a Laravel-based personal finance tracker with both a Filament admin surface and a Vue SPA. It helps users manage accounts, transactions, subscriptions, loans, credit cards, dashboards, budgets, and exports while keeping business rules and user preferences consistent across the product.

## Core Value

Keep personal finance data and behavior consistent across every surface, with one shared source of truth for preferences, reporting, and user-facing workflows.

## Current Milestone: v5.0 — Localization & Unified Settings

**Goal:** Make English and Italian work across the whole product in both the SPA and Filament backend, with one shared per-user language preference editable from frontend and backend settings.

**Target features:**
- Full English/Italian localization across the current SPA
- Full English/Italian localization across the Filament backend UI used by end users and admins
- Shared per-user language preference stored once and editable from both frontend and backend
- English fallback behavior for existing users who have not chosen a language

## Requirements

### Validated

- ✓ Core finance domains are implemented: accounts, transactions, subscriptions, loans, and credit cards
- ✓ Reporting, dashboards, budgets, and exports are available in both backend and SPA surfaces
- ✓ SPA authentication and per-user settings already exist and are used by the frontend
- ✓ User-owned data remains scoped consistently across admin and API surfaces

### Active

- [ ] Users can use the SPA in either English or Italian
- [ ] Users can use the Filament backend in either English or Italian
- [ ] Users can change their language preference from both frontend and backend settings
- [ ] The same saved language preference is applied consistently across frontend and backend sessions
- [ ] Existing users default safely to English until they explicitly choose another language

### Out of Scope

- Additional languages beyond English and Italian — not needed for this milestone
- Runtime translation management or in-app translation editors — unnecessary complexity for the current product size
- Bank-feed integrations — already deferred from the previous roadmap and not part of this milestone

## Context

- The product is built on Laravel 12, Filament 4, Tailwind CSS, and a Vue 3 SPA inside the same repository.
- User settings already exist and affect SPA behavior, but backend settings do not yet provide a useful language control that applies product-wide.
- Recent work delivered budgets, exports, and frontend settings, but the planning metadata is stale and must be realigned before new execution phases start.
- The user explicitly wants Italian available in both frontend and backend, with the backend settings surface also exposing language selection.

## Constraints

- **Tech stack**: Reuse Laravel/Filament/Vue localization primitives already compatible with the codebase — avoid introducing parallel settings or translation systems.
- **Compatibility**: Existing user settings records must remain compatible with current production data — language must continue to resolve safely for old users.
- **UX**: The same saved language preference must drive both SPA and backend behavior — users should not have to configure language twice.
- **Default behavior**: English remains the default language until a user selects Italian — safer rollout for existing data and copy.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Use one shared per-user language preference across frontend and backend | Prevent duplicated settings and inconsistent language behavior between surfaces | — Pending |
| Support only English and Italian in this milestone | Matches the current product need without expanding translation scope unnecessarily | — Pending |
| Keep English as the default fallback | Minimizes rollout risk for existing users and missing-copy edge cases | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-23 after starting milestone v5.0*

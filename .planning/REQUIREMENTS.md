# v5.0 Requirements — Localization & Unified Settings

**Milestone:** v5.0 — Localization & Unified Settings  
**Phase Range:** Phases 10-12  
**Status:** Planned  
**Date Created:** 2026-04-23

---

## Phase 10-12: Localization & Unified Settings — 16 Requirements

**Goal:** Deliver complete English/Italian support across the SPA and Filament backend, using one shared per-user language preference editable from both frontend and backend settings.

### Localization Infrastructure (I18N-01 to I18N-04)

- [ ] **I18N-01:** The application supports exactly two user-selectable languages for this milestone: English and Italian
- [ ] **I18N-02:** Existing users without a saved preference default safely to English in both frontend and backend
- [ ] **I18N-03:** One shared per-user language setting is persisted and reused by both frontend and backend surfaces
- [ ] **I18N-04:** Missing translations fall back safely without breaking page rendering or core workflows

### Frontend Localization (I18N-05 to I18N-09)

- [ ] **I18N-05:** Main SPA navigation, page titles, actions, empty states, and settings labels are available in English and Italian
- [ ] **I18N-06:** SPA finance workflows (accounts, transactions, subscriptions, loans, credit cards, dashboard, reports) render user-facing copy in the selected language
- [ ] **I18N-07:** Frontend validation messages, toasts, and confirmation copy are available in the selected language
- [ ] **I18N-08:** SPA formatting that depends on locale (labels, month names, dates, currency context copy) follows the selected language consistently
- [ ] **I18N-09:** Changing language from the frontend settings screen updates the shared preference and applies it without requiring duplicate configuration elsewhere

### Backend Localization & Settings (I18N-10 to I18N-14)

- [ ] **I18N-10:** Filament backend navigation, resource labels, page titles, actions, and core user-facing UI copy are available in English and Italian
- [ ] **I18N-11:** Backend forms, tables, validation feedback, and empty states use the selected language consistently
- [ ] **I18N-12:** The backend exposes language selection in a useful settings surface so users can change it there directly
- [ ] **I18N-13:** Changing language from the backend updates the same shared preference used by the SPA
- [ ] **I18N-14:** Backend sessions pick up the saved language preference consistently for the current user

### Verification & Regression Safety (I18N-15 to I18N-16)

- [ ] **I18N-15:** Automated coverage verifies the shared language preference behavior across frontend/backend-facing code paths
- [ ] **I18N-16:** Regression coverage ensures English fallback and Italian selection do not break existing settings, auth, or finance workflows

## Future Requirements

### Additional Languages

- **I18N-FUTURE-01:** Support more than two languages
- **I18N-FUTURE-02:** Let admins manage translations without code changes

### Translation Operations

- **I18N-FUTURE-03:** Add translation completeness tooling or dashboards
- **I18N-FUTURE-04:** Add runtime translation overrides

## Out of Scope

| Feature | Reason |
|---------|--------|
| Languages beyond English and Italian | Not required for this milestone |
| Runtime translation editor | Adds operational complexity without current value |
| Bank feed integrations | Deferred from previous roadmap and unrelated to localization |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| I18N-01 | Phase 10 | Pending |
| I18N-02 | Phase 10 | Pending |
| I18N-03 | Phase 10 | Pending |
| I18N-04 | Phase 10 | Pending |
| I18N-05 | Phase 11 | Pending |
| I18N-06 | Phase 11 | Pending |
| I18N-07 | Phase 11 | Pending |
| I18N-08 | Phase 11 | Pending |
| I18N-09 | Phase 11 | Pending |
| I18N-10 | Phase 12 | Pending |
| I18N-11 | Phase 12 | Pending |
| I18N-12 | Phase 10 | Pending |
| I18N-13 | Phase 10 | Pending |
| I18N-14 | Phase 12 | Pending |
| I18N-15 | Phase 12 | Pending |
| I18N-16 | Phase 12 | Pending |

**Coverage:**
- v5.0 requirements: 16 total
- Mapped to phases: 16
- Unmapped: 0 ✓

---
*Requirements defined: 2026-04-23*
*Last updated: 2026-04-23 after milestone definition*

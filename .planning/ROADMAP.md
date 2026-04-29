# Fluxa — Roadmap

**Project:** Fluxa Personal Finance Tracker  
**Last Updated:** 2026-04-29

---

## Roadmap Confidence Boundary

This roadmap follows the stricter Phase 13 evidence split:

- **validated:** auth/settings flows, account CRUD and scoping, dashboard/report APIs and exports, admin finance-report rendering, and admin access control
- **structural-only:** transactions, loans, credit cards, subscriptions, monthly budgets, GraphQL, and broader finance navigation remain visible in code but lower-confidence until current proof upgrades them
- **planning rule:** committed phases may extend validated surfaces or add proof for structural-only areas, but they must not treat structural-only finance domains as enhancement-ready shipped scope

Reference: `.planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md`

---

## Delivered History

### v1.0 — Finance Backend ✅

- **Phases 0-5:** Laravel finance backend, reporting, exports, and admin foundations shipped

### v2.0 — API Layer ✅

- **Phase 6:** REST + GraphQL API surface shipped in code, with Phase 13 validating only the auth, accounts, dashboard, and export boundary above

### v3.0 — Frontend ✅

- **Phase 7:** Vue SPA shipped in code, with Phase 13 validating the currently proven auth/settings, account, dashboard, and reporting behavior only

---

## Removed from Active Committed Scope

Earlier speculative roadmap items are intentionally no longer committed phases. This reset removes revived scope such as broad analytics expansion, budget enhancement planning, and bank-feed integrations until future evidence and prioritization justify new planning.

---

## v5.1 — Planning Realignment ⏳

### Phase 13: Current-State Audit ✅

**Goal:** Convert the refreshed codebase map into a trusted statement of what the shipped product actually does today.

**Requirements:** [ALIGN-05]

**Plans:** 1/1 plans complete ✅

Plans:
- [x] 13-01-capability-audit-PLAN.md — Build the evidence-backed current-state audit and superseded-scope note

### Phase 14: Planning Docs Realignment ✅

**Goal:** Rewrite the active project artifacts so maintainers can trust `PROJECT.md`, `REQUIREMENTS.md`, and `STATE.md` again.

**Requirements:** [ALIGN-01, ALIGN-02, ALIGN-04]

**Plans:** 1/1 plans complete ✅

Plans:
- [x] 14-01-docs-realignment-PLAN.md — Rewrite `PROJECT.md`, `REQUIREMENTS.md`, and `STATE.md` from validated Phase 13 evidence only

### Phase 15: Roadmap Reset & Concern Triage ✅

**Goal:** Rebuild the near-term roadmap from current reality and separate deferred concerns from committed work.

**Requirements:** [ALIGN-03, ALIGN-06]

**Plans:** 1/1 plans complete

Plans:
- [x] 15-01-roadmap-reset-triage-PLAN.md — Reset `ROADMAP.md` to conservative near-term phases grounded in the validated boundary, keep deferred concerns outside committed phases, and leave the direct Phase 16 planning command

**Success Criteria:**
1. `ROADMAP.md` maps ALIGN-03 and ALIGN-06 to a conservative, evidence-grounded roadmap reset
2. Deferred concerns stay visible without being mistaken for committed scope
3. The reset leaves a direct next planning command for the first post-reset phase

---

## Committed Near-Term Roadmap

### Phase 16: Proof-First Validation of Structural Finance Surfaces

**Goal:** Verify or downgrade the structural-only finance areas before any enhancement roadmap is allowed to depend on them.

**Why this is next:**
- It is the smallest committed follow-up that respects the validated versus structural-only boundary
- It keeps transactions, loans, credit cards, subscriptions, monthly budgets, and GraphQL in a proof-first path instead of an enhancement path
- It gives later planning a trustworthy basis for deciding what, if anything, deserves promotion into future committed work

**Scope guardrails:**
- Add or refresh proof for structural-only finance surfaces, or document why a surface stays structural-only
- Preserve the validated auth, account, dashboard/report, and admin boundary as the current shipped baseline
- Do **not** reintroduce bank-feed expansion, external integration scope, or feature-enhancement commitments for unproven finance domains

**Expected outputs from planning Phase 16:**
- Targeted proof strategy for structural-only finance areas
- Updated confidence notes for whichever domains gain current evidence
- Clear distinction between newly validated work and still-deferred concerns

No additional committed phases are listed beyond Phase 16 until that proof-first work reshapes the confidence boundary.

---

## Deferred Concerns — Visible but Not Committed Phases

These buckets stay outside committed phases on purpose. They remain visible for planning context, but they are **not committed roadmap work** until later proof or prioritization promotes them explicitly.

### Deferred Proof-First Candidates

These areas are present in current code structure but remain lower-confidence and **not committed as enhancement work**:

- Transactions REST behavior and SPA transaction flows
- Loans CRUD and schedule behavior
- Credit card CRUD, cycle, payment, expense, and balance workflows
- Subscription CRUD and auto-posting behavior
- Monthly budget mutation endpoints
- GraphQL schema queries and mutations for finance domains

### Deferred Hardening, Security, and Performance Concerns

These concerns stay visible but outside committed phases unless a future plan chooses to pull one in deliberately:

- Auth-context scoping and superadmin bypass proof gaps
- API permission and cross-user access test expansion
- Dashboard query-volume and indexing concerns
- Credit-card lifecycle race conditions and observer-chain fragility
- Service-locator cleanup, deprecated finance-calculation paths, and related maintenance risks

### Deferred Longer-Term Product Ideas

These ideas are outside committed phases and must not be mistaken for active roadmap promises:

- Bank-feed or Open Banking expansion
- Broader analytics or reporting enhancements beyond the currently validated export/report boundary
- External webhook/integration features
- Backup/recovery UX and broader audit-trail initiatives

---

## Direct Next Command

`/gsd-plan-phase 16`

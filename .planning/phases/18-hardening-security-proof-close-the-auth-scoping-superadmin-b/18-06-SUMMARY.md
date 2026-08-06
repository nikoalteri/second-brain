---
phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
plan: 06
subsystem: docs
tags: [deferred-items, concerns, documentation, phase-close]

# Dependency graph
requires:
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    plan: 01
    provides: TransactionCategories leak fix and regression tests
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    plan: 02
    provides: ScopingSecurityTest/AdminPanelScopingTest/DashboardApiTest/FinanceReportApiTest sweep results
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    plan: 03
    provides: ConsoleScopingTest proof and the ambient-authentication finding
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    plan: 04
    provides: Credit-card balance race-condition fix and the handleDeletedPayment carry-forward note
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    plan: 05
    provides: Expense validation fix and ObserverStaticStateTest evidence
provides:
  - "Phase 18 deferred-items register (D-02 documentation half)"
  - "Re-grounded CONCERNS.md Security Considerations and Known Bugs sections (2026-08-06)"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Phase-close documentation discharge: read every plan SUMMARY in the phase, split findings into fixed-with-test vs documented-not-fixed, mirror the Phase 17 deferred-items.md bullet structure"

key-files:
  created:
    - .planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md
  modified:
    - .planning/codebase/CONCERNS.md

key-decisions:
  - "CONCERNS.md did not exist in this worktree (deleted in an earlier commit, 82f7ad3, before /.planning/ was added to .gitignore); recreated it from the main checkout's untracked working copy, which matched the content the plan's <interfaces> table was written against verbatim, then applied the Task 2 edits and force-added it (git add -f) since .planning/ is gitignored but this file predates that rule for tracking purposes"
  - "Attributed the TransactionObserver and refreshCycleStatuses findings to '18-RESEARCH.md (input to 18-01/18-04)' rather than to a specific execution plan, since neither was in any of 18-01..18-05's actual files_modified scope — both were carried from phase research directly into this plan's deferred register"
  - "Did not re-litigate the resurfaced FinanceReportPageTest failure per this plan's own <interfaces> instruction ('do not re-litigate those, but note if they resurfaced'); instead identified and documented its root cause (FinanceReport.php::mount() defaults selectedBudgetMonth to now()->month, but the test hardcodes April 2026 fixtures with no Carbon::setTestNow() freeze) for a future plan to fix"

requirements-completed: [D-02, D-05]

# Metrics
duration: 40min
completed: 2026-08-06
---

# Phase 18 Plan 06: Close Phase 18 — deferred-items register and re-grounded CONCERNS.md Summary

**Wrote the Phase 18 deferred-items register (4 lower-severity findings + 1 resurfaced pre-existing test failure, each with a D-02 severity rationale, plus a table of the 4 high-severity findings actually fixed this phase) and re-grounded `CONCERNS.md`'s Security Considerations and Known Bugs sections against the current 2026-08-06 codebase, closing the documentation half of D-02.**

## Performance

- **Duration:** ~40 min
- **Tasks:** 2 completed
- **Files modified:** 2 (1 created, 1 recreated-and-modified)

## Accomplishments

- Read all five plan SUMMARY files (18-01 through 18-05) and wrote `deferred-items.md`, documenting four lower-severity findings left unfixed (`TransactionObserver` transfer-pair deletion, `handleDeletedPayment()` delta pattern, `refreshCycleStatuses()` non-transactional loop, observer static-state residue), each with a D-02 severity rationale and a concrete promotion condition
- Correctly excluded the 18-03 ambient-authentication finding from the deferred register — per the important context correction, it was fixed in-phase by the orchestrator (commit `3247037`) and is listed in the "Not deferred — fixed in this phase" table instead
- Recreated `.planning/codebase/CONCERNS.md` (missing from this worktree — deleted upstream before `.gitignore` started excluding `.planning/`) from the main checkout's working copy, which matched the plan's `<interfaces>` verification table exactly, then re-grounded its Security Considerations and Known Bugs sections with 2026-08-06 status lines naming the closing test for every claim
- Ran the full test suite as the phase-close gate: 187 passed, 1 pre-existing failure (documented, not fixed, per this plan's own scope guidance)

## Task Commits

1. **Task 1: Write the Phase 18 deferred-items register** - `d67ff0e` (docs)
2. **Task 2: Re-ground CONCERNS.md against verified 2026-08-06 code and run the phase gate** - `9624909` (docs)

**Plan metadata:** committed alongside this SUMMARY.md (worktree mode — orchestrator handles STATE.md/ROADMAP.md separately)

## Files Created/Modified

- `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md` - Rewritten from a partial one-item leftover (from a prior interrupted run) into the full Phase 18 register: four lower-severity findings with D-02 rationale, a note on the two proof-only plans (18-02 found no leaks; 18-03's finding was fixed in-phase, not deferred), a note on the resurfaced `FinanceReportPageTest` failure with its newly-identified root cause, and a "Not deferred — fixed in this phase" table naming all four high-severity closures and their regression tests
- `.planning/codebase/CONCERNS.md` - Recreated (did not exist in this worktree) and edited: header now carries a "Partially re-verified: 2026-08-06" line; Security Considerations' three entries (`withoutGlobalScopes()`, `HasUserScoping` auth-context, `hasRole()` reliance) each got a `Status 2026-08-06` line naming CLOSED/OPEN/CORRECTED and the covering test; Known Bugs' three entries (cycle race condition, transaction-observer recursion, expense-move validation) got corrected line numbers and closure status, plus a new fourth entry for the console ambient-authentication finding; the Fragile Areas "Observer Chain Dependency" entry got a corrected two-instance static-state status line; a closing "Phase 18 verification note" points to `deferred-items.md` and flags all other line numbers in the document as unverified 2024-12-19 data

## Decisions Made

- `CONCERNS.md` was absent from this worktree's git history (deleted in commit `82f7ad3`, "feat: removed admin widgets", well before this phase). The main repository's working tree had an untracked copy whose content matched the plan's `<interfaces>` corrections table verbatim (exact `DashboardController.php` line numbers, `TotalByCategory.php` line 19 comment, the 2024-12-19 race-condition citation), confirming it was the intended base version for this task. Copied it into this worktree and force-added it with `git add -f` (the `.planning/` directory is gitignored globally, but files already tracked before that rule — like `deferred-items.md` — continue to track normally; this newly-created file needed the explicit force-add).
- Findings from `18-RESEARCH.md` that were never assigned to any of the five execution plans' actual file scope (`TransactionObserver` transfer-pair deletion, `refreshCycleStatuses()` non-transactional loop) are attributed in `deferred-items.md` to "18-RESEARCH.md (input to 18-01/18-04)" rather than to a specific plan's Task work, since no SUMMARY records them as having been tested or touched.
- Followed this plan's own `<interfaces>` guidance to not re-litigate the `FinanceReportPageTest` failure (previously logged in Phase 17 and reconfirmed pre-existing by 18-01 through 18-04), but went further than prior plans by identifying its root cause: `FinanceReport.php::mount()` defaults `selectedBudgetMonth` to `now()->month`, and the test's hardcoded April 2026 fixtures only render correctly if the ambient system clock is also in April 2026 — no `Carbon::setTestNow()` freeze exists in the test. This is now documented for a future plan to fix.

## Deviations from Plan

None beyond the two decisions above (both Rule 3 — blocking: recreating a missing input file, and root-cause investigation needed to write an honest, non-hand-wavy deferred-items entry for the resurfaced test failure). No code files were modified; this plan's scope was documentation-only as specified.

## Full Suite Result

```
Tests:    1 failed, 187 passed (812 assertions)
Duration: 10.08s
```

The one failure is `Tests\Feature\Filament\FinanceReportPageTest::test_admin_finance_report_renders_budget_month_context_alerts_and_export_labels`, confirmed pre-existing (first logged in Phase 17's `deferred-items.md`, reconfirmed by 18-01 through 18-04's SUMMARY files via `git stash`/`git log` against unmodified base commits) and out of this plan's and this phase's `files_modified` scope. Per this plan's own `<interfaces>` instruction ("do not re-litigate those, but note if they resurfaced"), it is documented — with its now-identified root cause — in `deferred-items.md` rather than fixed. `CONCERNS.md` does **not** claim the suite is fully green; this SUMMARY and `deferred-items.md` state the count honestly per the plan's "do NOT paper over it" instruction.

## Issues Encountered

- `deferred-items.md` already existed in this worktree at the start of this plan's execution, but contained only a single leftover item ("From Plan 18-01" — the `FinanceReportPageTest` note) rather than the full Task 1 output this plan specifies. This was a partial artifact from a prior interrupted run (visible in the worktree's git history: HEAD had to be reset from an earlier position, `56f3ba4`, forward to the expected base `e8f380b` before this plan's tasks began). Rewrote it in full per this plan's Task 1 specification; no data was lost since the one prior item is preserved (now as the "Resurfaced pre-existing failure" section, expanded with the newly-identified root cause).
- `CONCERNS.md` was missing entirely from this worktree (see Decisions Made above) — recreated from the main checkout's matching working copy before editing.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- D-02's documentation half is discharged: every lower-severity Phase 18 finding is written down with a severity rationale and a promotion condition; every high-severity finding closed this phase is listed with its regression test.
- `CONCERNS.md`'s Security Considerations and Known Bugs sections are accurate as of 2026-08-06 and cross-reference `deferred-items.md`; the rest of the document (Tech Debt, Performance, Fragile Areas beyond the one corrected entry, Scaling Limits, etc.) remains 2024-12-19 data and is explicitly flagged as such for the next phase to re-verify before relying on it.
- The two documents do not contradict each other: nothing is marked CLOSED in one and deferred in the other.
- Full suite: 187/188 passing; the one failure is pre-existing, out of scope, and now has a documented root cause and fix direction for whichever future plan picks up `FinanceReportPageTest`.
- Phase 18 is ready to close from this plan's perspective — all five prior plans' loose ends are accounted for.

## Threat Flags

None — this plan touches only planning documents (`deferred-items.md`, `CONCERNS.md`); no application code or runtime surface was modified, matching the threat model's T-18-06-03 disposition (accept, no surface).

## Known Stubs

None.

---
*Phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b*
*Completed: 2026-08-06*

## Self-Check: PASSED

- FOUND: .planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md
- FOUND: .planning/codebase/CONCERNS.md
- FOUND: .planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/18-06-SUMMARY.md
- FOUND commit: d67ff0e (Task 1)
- FOUND commit: 9624909 (Task 2)
- FOUND commit: 591544c (SUMMARY)

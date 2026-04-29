---
phase: 15-roadmap-reset-concern-triage
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - .planning/ROADMAP.md
  - .planning/STATE.md
autonomous: true
requirements:
  - ALIGN-03
  - ALIGN-06
must_haves:
  truths:
    - Maintainers can read `.planning/ROADMAP.md` and see only conservative near-term committed phases grounded in the Phase 13 validated boundary.
    - Maintainers can distinguish committed roadmap phases from explicitly deferred concern buckets without mistaking deferred items for active scope.
    - Maintainers can see a direct next command to plan the first post-reset phase.
  artifacts:
    - path: .planning/ROADMAP.md
      provides: Reset roadmap with committed near-term phases, explicit deferred buckets, and a direct next command
      contains: Phase 15 reset, Phase 16 proof-first follow-up, deferred concern sections
    - path: .planning/STATE.md
      provides: Minimal handoff aligned to the reset roadmap
      contains: direct Phase 16 planning command if ROADMAP changes the next step
  key_links:
    - from: .planning/ROADMAP.md
      to: .planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md
      via: roadmap confidence boundary
      pattern: validated|structural-only|proof|deferred
    - from: .planning/ROADMAP.md
      to: .planning/codebase/CONCERNS.md
      via: deferred concern buckets
      pattern: deferred|not committed|proof-first
    - from: .planning/STATE.md
      to: .planning/ROADMAP.md
      via: direct handoff command
      pattern: /gsd-plan-phase 16
---

<objective>
Reset the roadmap so it reflects the stricter Phase 13/14 evidence boundary, keeps deferred concerns separate from committed work, and hands off directly to the first post-reset phase.

Purpose: Satisfy ALIGN-03 and ALIGN-06 by making the roadmap trustworthy again without turning deferred concern inventory into active commitments.
Output: Updated `.planning/ROADMAP.md`, plus a minimal `.planning/STATE.md` handoff update only if the reset changes the next-step command.
</objective>

<execution_context>
@~/.copilot/get-shit-done/workflows/execute-plan.md
@~/.copilot/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/REQUIREMENTS.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/15-roadmap-reset-concern-triage/15-CONTEXT.md
@.planning/phases/15-roadmap-reset-concern-triage/15-DISCUSSION-LOG.md
@.planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md
@.planning/phases/13-current-state-audit/13-CURRENT-STATE-AUDIT.md
@.planning/phases/13-current-state-audit/13-current-state-audit-01-SUMMARY.md
@.planning/phases/14-planning-docs-realignment/14-CONTEXT.md
@.planning/phases/14-planning-docs-realignment/14-planning-docs-realignment-01-SUMMARY.md
@.planning/codebase/CONCERNS.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Reset ROADMAP.md to a conservative evidence-grounded near-term sequence</name>
  <files>.planning/ROADMAP.md</files>
  <action>Rewrite `.planning/ROADMAP.md` so committed active phases stop at a conservative near-term horizon per D-01 and D-02. Use the Phase 13 validated versus structural-only split as the roadmap confidence boundary: validated auth/settings, account CRUD/scoping, dashboard/report exports, and admin finance-report/access behavior may inform current-state framing, but structural-only finance areas must not be presented as shipped enhancement targets. Keep historical completed phases concise, then replace speculative future commitments with a tight post-reset section that includes Phase 15 as the roadmap reset and a first post-reset committed phase named around proof-first validation of structural-only finance areas per D-05 and D-06. Do not restore broad aspirational milestones, bank-feed expansion, or enhancement phases that assume structural-only domains are already validated. Ensure the Phase 15 section still maps ALIGN-03 and ALIGN-06 explicitly.</action>
  <verify>
    <automated>grep -n "ALIGN-03\\|ALIGN-06" .planning/ROADMAP.md && grep -n "Phase 16" .planning/ROADMAP.md && grep -nE "proof|validation" .planning/ROADMAP.md && ! grep -n "### Phase 9: Bank Feed Integrations" .planning/ROADMAP.md</automated>
  </verify>
  <done>`ROADMAP.md` shows only conservative near-term committed work beyond Phase 15, uses the Phase 13 confidence boundary, and avoids reviving speculative roadmap scope.</done>
</task>

<task type="auto">
  <name>Task 2: Separate deferred concerns into explicit non-committed buckets</name>
  <files>.planning/ROADMAP.md</files>
  <action>Add a clearly labeled deferred-concerns section outside committed phases per D-03 and D-04. Bucket the Phase 13 and `CONCERNS.md` inventory so readers can tell what is visible but not committed: keep structural-only finance domains as proof-first candidates, keep hardening/performance/security risks deferred unless they directly justify the next proof phase, and keep longer-term product ideas outside active roadmap scope. Make the labels explicit enough that deferred work cannot be mistaken for planned phases. Do not map every concern to a phase, and do not promote structural-only domains into enhancement commitments before proof.</action>
  <verify>
    <automated>grep -n "Deferred" .planning/ROADMAP.md && grep -n "proof-first" .planning/ROADMAP.md && grep -n "not committed\\|outside committed phases\\|deferred concerns" .planning/ROADMAP.md</automated>
  </verify>
  <done>`ROADMAP.md` contains explicit deferred buckets that preserve visibility for proof gaps and concerns without converting them into committed roadmap phases.</done>
</task>

<task type="auto">
  <name>Task 3: Leave the direct post-reset planning command and align STATE.md only if needed</name>
  <files>.planning/ROADMAP.md, .planning/STATE.md</files>
  <action>End the roadmap with a direct next command per D-07: `/gsd-plan-phase 16`. In the same pass, update `.planning/STATE.md` only if the roadmap reset changes the active handoff wording or next-step command; keep that update minimal and documentation-only. The state handoff should reference the reset roadmap, preserve the conservative confidence boundary, and avoid adding implementation scope or extra checkpoints.</action>
  <verify>
    <automated>grep -n "/gsd-plan-phase 16" .planning/ROADMAP.md && (grep -n "/gsd-plan-phase 16" .planning/STATE.md || grep -n "Phase 16" .planning/STATE.md)</automated>
  </verify>
  <done>The roadmap ends with a concrete next planning command, and `STATE.md` stays aligned if a handoff update was needed.</done>
</task>

</tasks>

<verification>
Run all task-level checks, then review the final roadmap structure end-to-end: completed history stays concise, active future scope is conservative, deferred concerns are visibly outside committed phases, structural-only areas are treated as proof-first candidates, and the document ends with `/gsd-plan-phase 16`.
</verification>

<success_criteria>
- `ROADMAP.md` maps ALIGN-03 and ALIGN-06 to Phase 15 and limits committed future scope to conservative near-term phases.
- Deferred concerns are visible in explicit buckets outside committed phases and do not read like silent commitments.
- Structural-only finance areas are framed as proof-first candidates, not enhancement-ready roadmap promises.
- The final handoff includes the direct command `/gsd-plan-phase 16`.
</success_criteria>

<output>
After completion, create `.planning/phases/15-roadmap-reset-concern-triage/15-roadmap-reset-concern-triage-01-SUMMARY.md`
</output>

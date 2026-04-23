---
phase: 10-localization-foundation-shared-settings
plan: 05
type: execute
wave: 4
depends_on: [10-03, 10-04]
files_modified: []
autonomous: false
requirements: [I18N-03, I18N-12, I18N-13]
must_haves:
  truths:
    - Backend language changes are visible after save/reload for the current user
    - The SPA picks up a backend-side language change without a second preference store
    - Frontend and backend both keep using the same saved preference across refreshes
  artifacts:
    - path: app/Filament/Pages/Auth/EditProfile.php
      provides: backend verification target
    - path: resources/js/views/SettingsView.vue
      provides: SPA verification target
    - path: app/Services/UserSettingsService.php
      provides: shared persistence path verification target
  key_links:
    - from: backend profile page
      to: user_settings.language
      via: shared save path
      pattern: language
    - from: SPA boot/auth store
      to: saved user settings
      via: auth bootstrap refresh
      pattern: settings.language
---

<objective>
Perform the manual cross-surface verification required for the shared language setting.

Purpose: confirm the backend profile page, SPA settings page, and shared persistence behave correctly together before rollout work continues in Phases 11 and 12.
Output: explicit approval or concrete defects tied to the failing surface.
</objective>

<execution_context>
@~/.copilot/get-shit-done/workflows/execute-plan.md
@~/.copilot/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
@app/Filament/Pages/Auth/EditProfile.php
@resources/js/views/SettingsView.vue
@resources/js/app.js
@app/Services/UserSettingsService.php
</context>

<tasks>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 1: Verify shared language behavior across backend and SPA</name>
  <read_first>
    - .planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
    - app/Filament/Pages/Auth/EditProfile.php
    - resources/js/views/SettingsView.vue
    - resources/js/app.js
  </read_first>
  <what-built>Canonical locale contract, backend locale middleware, SPA i18n foundation, and a backend profile language selector using the same saved preference.</what-built>
  <action>Before asking for approval, run the automated checks below and start the local app if needed. Then hand off the exact manual steps without asking the user to do setup the agent can do automatically.</action>
  <how-to-verify>
    1. Sign in to Filament and open the backend profile/settings page.
    2. Change language to Italian, save, reload the page, and navigate at least one additional backend page that previously hardcoded Italian formatting (for example Accounts, Loans, or a shared dashboard widget) to confirm the request locale now controls the visible formatting path.
    3. Open the SPA settings page for the same user, refresh the page, and confirm the language select already shows Italian without reconfiguring it.
    4. Change the SPA language back to English, save, refresh the SPA, then refresh the backend profile page and confirm English is now selected there too.
    5. Confirm there is only one shared preference flow: no second backend-only language field, no duplicate browser-only toggle, and no lost selection after refresh.
  </how-to-verify>
  <acceptance_criteria>
    - User explicitly confirms backend-to-SPA sync works
    - User explicitly confirms SPA-to-backend sync works
    - If rejected, feedback names the exact surface and step that failed
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure && php artisan test tests/Feature/Localization/LocaleMiddlewareTest.php --stop-on-failure && php artisan test tests/Feature/Filament/ProfileLocaleSettingsTest.php --stop-on-failure && npm run build</automated>
  </verify>
  <resume-signal>Type "approved" or describe the failing step(s) and surface.</resume-signal>
  <done>Manual cross-surface verification is approved or concrete defects are captured for follow-up.</done>
</task>

</tasks>

<verification>
Human approval is required for this plan.
</verification>

<success_criteria>
- Backend and SPA both show the same saved language after refresh
- Backend profile editing works without duplicate preference storage
- Manual verification feedback is captured explicitly
</success_criteria>

<output>
After completion, create `.planning/phases/10-localization-foundation-shared-settings/10-05-SUMMARY.md`
</output>

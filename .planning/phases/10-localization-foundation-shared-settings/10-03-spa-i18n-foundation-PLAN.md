---
phase: 10-localization-foundation-shared-settings
plan: 03
type: execute
wave: 1
depends_on: []
files_modified:
  - package.json
  - package-lock.json
  - resources/js/i18n/supportedLocales.js
  - resources/js/i18n/index.js
  - resources/js/i18n/messages/en.json
  - resources/js/i18n/messages/it.json
  - resources/js/app.js
  - resources/js/stores/auth.js
  - resources/js/composables/useUserPreferences.js
  - resources/js/views/SettingsView.vue
autonomous: true
requirements: [I18N-01, I18N-02, I18N-03, I18N-04]
must_haves:
  truths:
    - The SPA boots in English when no saved language is available
    - The same `auth.user.settings.language` value drives Vue i18n, `<html lang>`, and browser-locale formatting
    - Missing SPA translation keys fall back to English instead of breaking rendering
  artifacts:
    - path: resources/js/i18n/index.js
      provides: one Vue i18n instance with English fallback
    - path: resources/js/i18n/messages/en.json
      provides: baseline Phase 10 English message keys
    - path: resources/js/i18n/messages/it.json
      provides: baseline Phase 10 Italian message keys
  key_links:
    - from: resources/js/app.js
      to: resources/js/i18n/index.js
      via: app plugin registration and locale synchronization
      pattern: app.use|i18n
    - from: resources/js/composables/useUserPreferences.js
      to: resources/js/i18n/supportedLocales.js
      via: browser locale mapping
      pattern: browserLocale|appLocale
    - from: resources/js/views/SettingsView.vue
      to: /api/v1/auth/settings
      via: existing shared settings update call
      pattern: /api/v1/auth/settings
---

<objective>
Bootstrap the SPA localization foundation without starting the full copy-translation rollout.

Purpose: give the Vue app one safe i18n instance, English fallback behavior, and synchronization with the already-shared auth/settings state.
Output: vue-i18n setup, locale helpers, starter message files, and settings-page wiring.
</objective>

<execution_context>
@~/.copilot/get-shit-done/workflows/execute-plan.md
@~/.copilot/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/REQUIREMENTS.md
@.planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
@.planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
@.planning/phases/07-frontend-spa/07-01-SUMMARY.md
@resources/js/app.js
@resources/js/stores/auth.js
@resources/js/composables/useUserPreferences.js
@resources/js/views/SettingsView.vue
@package.json

<interfaces>
From resources/js/stores/auth.js:
- user
- setUser(value)
- updateUserSettings(settings)
- fetchCurrentUser()

From resources/js/composables/useUserPreferences.js:
- settings
- locale

From resources/js/views/SettingsView.vue:
- settingsForm
- saveSettings()
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create the SPA locale helper and Vue i18n bootstrap</name>
  <files>package.json, package-lock.json, resources/js/i18n/supportedLocales.js, resources/js/i18n/index.js, resources/js/i18n/messages/en.json, resources/js/i18n/messages/it.json</files>
  <read_first>
    - package.json
    - resources/js/app.js
    - resources/js/composables/useUserPreferences.js
    - .planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
  </read_first>
  <action>Install `vue-i18n` and add a dedicated `resources/js/i18n/` foundation. Create `supportedLocales.js` with `appLocale(value)` and `browserLocale(value)` helpers so the SPA also constrains itself to `en|it` while mapping to `en-US|it-IT` only for browser formatting. Initialize `createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en' })` and add only the Phase 10 shell/settings keys needed now. Do not start translating dashboard, finance workflows, or validation copy yet.</action>
  <acceptance_criteria>
    - `grep -q "vue-i18n" package.json`
    - `grep -q "createI18n" resources/js/i18n/index.js`
    - `grep -q "fallbackLocale" resources/js/i18n/index.js`
    - `grep -q "\"settings.title\"" resources/js/i18n/messages/en.json`
    - `grep -q "\"settings.title\"" resources/js/i18n/messages/it.json`
  </acceptance_criteria>
  <verify>
    <automated>npm run build</automated>
  </verify>
  <done>The SPA has one i18n instance with starter English/Italian message files and English fallback behavior.</done>
</task>

<task type="auto">
  <name>Task 2: Synchronize SPA boot, formatting, and settings with the shared language preference</name>
  <files>resources/js/app.js, resources/js/stores/auth.js, resources/js/composables/useUserPreferences.js, resources/js/views/SettingsView.vue</files>
  <read_first>
    - resources/js/app.js
    - resources/js/stores/auth.js
    - resources/js/composables/useUserPreferences.js
    - resources/js/views/SettingsView.vue
    - .planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
  </read_first>
  <action>Register the i18n plugin in `app.js`, watch the auth-backed user settings so Vue i18n locale and `<html lang>` stay in sync, and keep browser formatting derived from the same `auth.user.settings.language` value via `supportedLocales.js`. Replace the hardcoded Settings view copy with `$t()` keys created in Task 1, but keep the existing `/api/v1/auth/settings` request as the only write path. Do not add a separate Pinia locale store or any duplicate localStorage preference key.</action>
  <acceptance_criteria>
    - `grep -q "app.use(i18n)" resources/js/app.js`
    - `grep -q "document.documentElement.lang" resources/js/app.js`
    - `grep -q "\\$t(" resources/js/views/SettingsView.vue`
    - `grep -q "browserLocale" resources/js/composables/useUserPreferences.js`
  </acceptance_criteria>
  <verify>
    <automated>npm run build && php artisan test tests/Feature/Api/AuthApiTest.php --filter=language --stop-on-failure</automated>
  </verify>
  <done>The SPA boot path, formatting helpers, and settings page all derive locale from the shared auth/settings payload with English fallback.</done>
</task>

</tasks>

<verification>
Run `npm run build` and `php artisan test tests/Feature/Api/AuthApiTest.php --filter=language --stop-on-failure`.
</verification>

<success_criteria>
- Vue i18n is installed and mounted once
- SPA locale state stays derived from `auth.user.settings.language`
- Missing client-side translation keys fall back to English
</success_criteria>

<output>
After completion, create `.planning/phases/10-localization-foundation-shared-settings/10-03-SUMMARY.md`
</output>

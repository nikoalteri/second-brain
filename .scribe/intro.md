# Introduction

REST API for the Fluxa personal finance tracker. All endpoints require Bearer token authentication obtained via POST /api/v1/auth/login.

The current API is used by the Vue SPA for the finance-critical flows: accounts, transactions, loans, credit cards, subscriptions, dashboard reminders, dashboard charts, and finance reports.

Recent finance additions include:

- credit-card cycles, expenses, and payments through REST
- dashboard upcoming-payment reminders with a default 3-day window
- dashboard chart payloads for SPA cashflow, spending, and net-worth widgets
- subscription frequencies managed as first-class backend data
- subscription renewals that can post either to transactions or credit-card expenses

<aside>
    <strong>Base URL</strong>: <code>https://second-brain.test</code>
</aside>

    This documentation aims to provide all the information you need to work with our API.

    <aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
    You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>

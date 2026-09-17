<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cookie policy — Fluxa</title>
    <style>
        body { margin: 0; background: #f9fafb; color: #1f2937; font: 1rem/1.65 system-ui, sans-serif; }
        main { max-width: 56rem; margin: 2rem auto; padding: 1.5rem; }
        h1, h2 { line-height: 1.3; color: #111827; }
        h2 { margin-top: 2rem; font-size: 1.3rem; }
        a { color: #92400e; text-underline-offset: .2em; }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: .8rem; border: 1px solid #d1d5db; text-align: left; vertical-align: top; }
        caption { text-align: left; padding-bottom: .6rem; font-weight: 600; }
        code { overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <nav aria-label="Navigation"><a href="/home">Return to Fluxa</a></nav>
    <h1>Cookie policy</h1>
    <p>Last updated: <time datetime="2026-09-17">17 September 2026</time>.</p>
    <p>This policy explains how Fluxa uses cookies and browser storage to provide sign-in, security and account features.</p>

    <h2>Who operates this service</h2>
    @if (config('legal.operator_name'))
        <p>Service operator: {{ config('legal.operator_name') }}.</p>
    @else
        <p>This Fluxa instance is managed by the operator who provided you with access to the service.</p>
    @endif
    @if (config('legal.contact_email'))
        <p>For questions about cookies and browser storage, contact <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
    @else
        <p>For questions about cookies and browser storage, contact your instance operator through the channel used to provide access.</p>
    @endif

    <h2>Cookies and similar storage</h2>
    <p>Cookies are small values stored by your browser and sent to the service with requests. Local storage is a separate browser mechanism that can retain values across browser restarts. Fluxa uses both for the purposes described below.</p>
    <p>Fluxa does not include advertising, profiling or analytics trackers. Its cookies support session management and security. Strictly necessary technical cookies do not require prior consent, but their use must be explained. See the <a href="https://www.garanteprivacy.it/faq/cookie" rel="noreferrer">Italian Data Protection Authority’s cookie guidance</a>.</p>

    <h2>First-party cookies</h2>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Cookies table">
        <table>
            <caption>Cookies used by this instance</caption>
            <thead><tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Duration</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>{{ config('session.cookie') }}</code></td>
                    <td>Identifies the server session, including sign-in to the administration panel.</td>
                    <td>
                        @if (config('session.expire_on_close'))
                            Until the browser session ends. The server session has an inactivity limit of {{ config('session.lifetime') }} minutes.
                        @else
                            {{ config('session.lifetime') }} minutes, renewed during use.
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><code>XSRF-TOKEN</code></td>
                    <td>Supports protection against cross-site request forgery on web forms.</td>
                    <td>{{ config('session.lifetime') }} minutes, renewed during use.</td>
                </tr>
                <tr>
                    <td><code>remember_…</code> (when “Remember me” is selected)</td>
                    <td>Keeps you signed in to the administration panel when you request persistent sign-in.</td>
                    <td>Up to 400 days; removed when you sign out of the panel.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>Local storage</h2>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Local storage table">
        <table>
            <caption>Browser values used for frontend sign-in</caption>
            <thead><tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Retention</th></tr></thead>
            <tbody>
                <tr><td><code>fluxa_access_token</code></td><td>Authenticates frontend API requests.</td><td>Removed on frontend sign-out or when the frontend clears the session. Access tokens are issued with a 30-minute validity and may be replaced during session renewal.</td></tr>
                <tr><td><code>fluxa_refresh_token</code></td><td>Renews frontend API access.</td><td>Removed on frontend sign-out or when the frontend clears the session. Refresh tokens are issued with a seven-day validity.</td></tr>
                <tr><td><code>fluxa_user</code></td><td>Stores your profile and preferences for the signed-in interface. This can include your name, email, phone, date of birth and tax code, if provided.</td><td>Updated as your profile is refreshed and removed on frontend sign-out.</td></tr>
            </tbody>
        </table>
    </div>
    <p>Local storage has no automatic expiry. A token’s server-side validity does not automatically delete its stored browser value. Values may remain until the application removes them or you clear site data.</p>
    <p>Vault unlock state and displayed financial information are also held temporarily in application memory. The vault unlock token is not saved in local storage.</p>

    <h2>Your controls</h2>
    <p>You can block or delete cookies and clear local storage in your browser’s site-data settings. Blocking necessary cookies may prevent administration-panel sign-in or web forms from working. Clearing frontend storage signs you out of the frontend. If you use both interfaces on a shared device, sign out of both the frontend and the administration panel.</p>
    <p>Clearing browser data does not delete financial records held by the service or files you have downloaded.</p>

    <h2>Changes to this policy</h2>
    <p>This policy will be updated when browser storage or its purposes change. If optional tracking is introduced, the service must provide the relevant information and obtain any required consent before activating it.</p>
</main>
</body>
</html>

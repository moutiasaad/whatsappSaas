@extends('layouts.legal')

@section('title', $dbPage?->title ?? __('landing.footer_cookies'))
@section('subtitle', __('landing.legal_updated', ['date' => $dbPage?->updated_at?->format('F j, Y') ?? 'June 1, 2025']))

@section('toc')
<a href="#what-are-cookies">1. What Are Cookies</a>
<a href="#essential">2. Essential Cookies</a>
<a href="#functional">3. Functional Cookies</a>
<a href="#analytics">4. Analytics Cookies</a>
<a href="#third-party">5. Third-Party Cookies</a>
<a href="#manage">6. Managing Cookies</a>
<a href="#contact-cookies">7. Contact</a>
@endsection

@section('content')
@if($dbPage?->content)
{!! $dbPage->content !!}
@else
<h2 id="what-are-cookies">1. What Are Cookies?</h2>
<p>Cookies are small text files that a website stores on your device when you visit. They allow the site to remember information about your visit — such as your login session or language preference — making the experience more consistent and useful.</p>
<p>We also use similar technologies such as local storage and session storage for the same purposes.</p>

<h2 id="essential">2. Essential Cookies</h2>
<p>These cookies are strictly necessary for the platform to function. They cannot be disabled.</p>

<div style="overflow-x:auto;margin:16px 0 24px">
    <table style="width:100%;border-collapse:collapse;font-size:14px">
        <thead>
            <tr style="background:var(--bg);border-bottom:2px solid var(--border)">
                <th style="text-align:left;padding:10px 14px;font-weight:700;color:var(--text)">Name</th>
                <th style="text-align:left;padding:10px 14px;font-weight:700;color:var(--text)">Purpose</th>
                <th style="text-align:left;padding:10px 14px;font-weight:700;color:var(--text)">Duration</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:10px 14px;color:var(--text);font-family:monospace;font-size:13px">{{ config('session.cookie', 'wavadesk_session') }}</td>
                <td style="padding:10px 14px;color:var(--muted)">Maintains your authenticated session across page requests</td>
                <td style="padding:10px 14px;color:var(--muted)">Session / {{ config('session.lifetime', 120) }} min</td>
            </tr>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:10px 14px;color:var(--text);font-family:monospace;font-size:13px">XSRF-TOKEN</td>
                <td style="padding:10px 14px;color:var(--muted)">Protects against Cross-Site Request Forgery (CSRF) attacks</td>
                <td style="padding:10px 14px;color:var(--muted)">Session</td>
            </tr>
        </tbody>
    </table>
</div>

<h2 id="functional">3. Functional Cookies</h2>
<p>These cookies remember your preferences to give you a better experience. They are not strictly required but are enabled by default.</p>

<div style="overflow-x:auto;margin:16px 0 24px">
    <table style="width:100%;border-collapse:collapse;font-size:14px">
        <thead>
            <tr style="background:var(--bg);border-bottom:2px solid var(--border)">
                <th style="text-align:left;padding:10px 14px;font-weight:700;color:var(--text)">Name</th>
                <th style="text-align:left;padding:10px 14px;font-weight:700;color:var(--text)">Purpose</th>
                <th style="text-align:left;padding:10px 14px;font-weight:700;color:var(--text)">Duration</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:10px 14px;color:var(--text);font-family:monospace;font-size:13px">locale</td>
                <td style="padding:10px 14px;color:var(--muted)">Remembers your selected language (en / fr / ar)</td>
                <td style="padding:10px 14px;color:var(--muted)">1 year</td>
            </tr>
        </tbody>
    </table>
</div>

<h2 id="analytics">4. Analytics Cookies</h2>
<p>We do not currently use third-party analytics cookies (such as Google Analytics). Any future addition of analytics tools will be disclosed in an update to this policy, and we will seek consent where required by law.</p>

<h2 id="third-party">5. Third-Party Cookies</h2>
<p>Certain features may load content from third parties who may set their own cookies:</p>
<ul>
    <li><strong>Stripe</strong> — when you proceed to payment, Stripe's checkout interface may set cookies for fraud prevention and session management. These are governed by <a href="https://stripe.com/privacy" target="_blank" rel="noopener">Stripe's Privacy Policy</a>.</li>
    <li><strong>Google Fonts</strong> — we load the Outfit typeface from Google Fonts. Google may log the request. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a>.</li>
</ul>
<p>We do not control these third-party cookies and recommend reviewing their respective privacy policies.</p>

<h2 id="manage">6. Managing & Disabling Cookies</h2>
<p>You can control cookies through your browser settings. Most browsers allow you to:</p>
<ul>
    <li>View and delete existing cookies</li>
    <li>Block all or certain cookies</li>
    <li>Set preferences per website</li>
</ul>
<p>Note that disabling essential cookies will prevent you from logging in and using the platform. Disabling functional cookies means your language preference will not be remembered between sessions.</p>
<p>Browser-specific guides:</p>
<ul>
    <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
    <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener">Mozilla Firefox</a></li>
    <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471" target="_blank" rel="noopener">Apple Safari</a></li>
    <li><a href="https://support.microsoft.com/en-us/windows/manage-cookies-in-microsoft-edge" target="_blank" rel="noopener">Microsoft Edge</a></li>
</ul>

<h2 id="contact-cookies">7. Contact</h2>
<p>If you have questions about our use of cookies, please contact us at <a href="mailto:privacy@wavadesk.com">privacy@wavadesk.com</a>.</p>
@endif
@endsection

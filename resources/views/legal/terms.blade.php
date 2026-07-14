@extends('layouts.legal')

@section('title', $dbPage?->title ?? __('landing.footer_terms'))
@section('subtitle', __('landing.legal_updated', ['date' => $dbPage?->updated_at?->format('F j, Y') ?? 'June 1, 2025']))

@section('toc')
<a href="#acceptance">1. Acceptance</a>
<a href="#service">2. Service Description</a>
<a href="#accounts">3. Accounts</a>
<a href="#payment">4. Payment & Billing</a>
<a href="#acceptable-use">5. Acceptable Use</a>
<a href="#whatsapp">6. WhatsApp Policy</a>
<a href="#ip">7. Intellectual Property</a>
<a href="#liability">8. Liability</a>
<a href="#termination">9. Termination</a>
<a href="#changes">10. Changes</a>
<a href="#contact-terms">11. Contact</a>
@endsection

@section('content')
@if($dbPage?->content)
{!! $dbPage->content !!}
@else
<h2 id="acceptance">1. Acceptance of Terms</h2>
<p>By accessing or using TshlBot ("the Service"), you agree to be bound by these Terms of Service. If you do not agree to these terms, you may not use the Service. These terms apply to all users, including tenants, administrators, supervisors, and agents.</p>

<h2 id="service">2. Service Description</h2>
<p>TshlBot is a multi-tenant SaaS platform that enables businesses ("Tenants") to manage WhatsApp customer support conversations. The Service includes:</p>
<ul>
    <li>WhatsApp Business API integration and conversation management</li>
    <li>AI-powered auto-reply using Anthropic Claude</li>
    <li>Multi-team routing, assignment, and escalation workflows</li>
    <li>Knowledge base management and saved replies</li>
    <li>Analytics, audit logs, and reporting</li>
</ul>
<p>We reserve the right to modify or discontinue any part of the Service at any time with reasonable notice.</p>

<h2 id="accounts">3. Accounts & Registration</h2>
<p>To access the Service you must create a Tenant account. You are responsible for:</p>
<ul>
    <li>Providing accurate and complete registration information</li>
    <li>Maintaining the confidentiality of your credentials</li>
    <li>All activity that occurs under your account</li>
    <li>Immediately notifying us of any unauthorized access</li>
</ul>
<p>Each Tenant is responsible for managing the roles (Admin, Supervisor, Agent) assigned within their workspace. TshlBot is not liable for actions performed by users you invite.</p>

<h2 id="payment">4. Payment & Billing</h2>
<p>Access to paid plans requires a valid payment method. By subscribing you authorize us to charge your payment method for the selected plan on a recurring basis.</p>
<ul>
    <li><strong>Billing cycle:</strong> Monthly or annual, depending on your selected plan</li>
    <li><strong>Currency:</strong> All charges are in US dollars (USD)</li>
    <li><strong>Refunds:</strong> Payments are non-refundable except as required by applicable law</li>
    <li><strong>Plan upgrades:</strong> Upgrading takes effect immediately; you are billed for the new plan at the next cycle</li>
    <li><strong>Failed payments:</strong> Access may be suspended if a payment fails and is not resolved within 7 days</li>
</ul>
<p>Prices may change with 30 days' notice. Continued use after a price change constitutes acceptance.</p>

<h2 id="acceptable-use">5. Acceptable Use</h2>
<p>You agree not to use the Service to:</p>
<ul>
    <li>Send spam, unsolicited messages, or bulk promotional content</li>
    <li>Harass, abuse, or threaten any person</li>
    <li>Violate any applicable law or regulation</li>
    <li>Transmit malware, viruses, or other harmful code</li>
    <li>Attempt to gain unauthorized access to the Service or its infrastructure</li>
    <li>Resell or sublicense access to the Service without written permission</li>
</ul>
<p>Violation of this policy may result in immediate suspension or termination of your account without refund.</p>

<h2 id="whatsapp">6. WhatsApp Policy Compliance</h2>
<p>The Service uses the WhatsApp Business API. By using the Service, you agree to comply with:</p>
<ul>
    <li><a href="https://www.whatsapp.com/legal/business-policy" target="_blank" rel="noopener">WhatsApp Business Policy</a></li>
    <li><a href="https://www.whatsapp.com/legal/terms-of-service" target="_blank" rel="noopener">WhatsApp Terms of Service</a></li>
    <li>All applicable Meta Platforms policies</li>
</ul>
<p>You are solely responsible for the content of messages sent through your instances. TshlBot does not review message content and accepts no liability for policy violations arising from your use.</p>

<h2 id="ip">7. Intellectual Property</h2>
<p>All rights in the Service — including software, design, trademarks, and content — remain the exclusive property of TshlBot or its licensors. You receive a limited, non-exclusive, non-transferable license to use the Service solely as permitted by these Terms.</p>
<p>You retain ownership of the data and content you upload. You grant TshlBot a limited license to process that data solely to provide the Service.</p>

<h2 id="liability">8. Disclaimers & Limitation of Liability</h2>
<p>The Service is provided "as is" without warranties of any kind. We do not warrant that the Service will be uninterrupted, error-free, or meet your specific requirements.</p>
<p>To the fullest extent permitted by law, TshlBot's total liability for any claim arising out of or relating to these Terms or the Service shall not exceed the amount you paid in the three months preceding the claim.</p>
<p>In no event will TshlBot be liable for indirect, incidental, special, consequential, or punitive damages, including loss of revenue, profits, or data.</p>

<h2 id="termination">9. Termination</h2>
<p>You may cancel your subscription at any time from your billing settings. Access continues until the end of the current billing period.</p>
<p>We may suspend or terminate your account if you breach these Terms, fail to pay, or if required by law. Upon termination, your data may be deleted after a 30-day grace period.</p>

<h2 id="changes">10. Changes to These Terms</h2>
<p>We may update these Terms from time to time. We will notify you of material changes by email or by a prominent notice in the platform. Continued use of the Service after changes take effect constitutes your acceptance of the revised Terms.</p>

<h2 id="contact-terms">11. Contact</h2>
<p>For questions about these Terms, please contact us at <a href="mailto:legal@tshlbot.online">legal@tshlbot.online</a>.</p>
@endif
@endsection

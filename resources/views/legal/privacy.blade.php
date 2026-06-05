@extends('layouts.legal')

@section('title', __('landing.footer_privacy'))
@section('subtitle', __('landing.legal_updated', ['date' => 'June 1, 2025']))

@section('toc')
<a href="#controller">1. Data Controller</a>
<a href="#data-collected">2. Data We Collect</a>
<a href="#legal-basis">3. Legal Basis</a>
<a href="#how-we-use">4. How We Use Data</a>
<a href="#sharing">5. Data Sharing</a>
<a href="#retention">6. Retention</a>
<a href="#security">7. Security</a>
<a href="#rights">8. Your Rights</a>
<a href="#cookies-ref">9. Cookies</a>
<a href="#transfers">10. International Transfers</a>
<a href="#changes-pp">11. Changes</a>
<a href="#contact-pp">12. Contact</a>
@endsection

@section('content')
<h2 id="controller">1. Data Controller</h2>
<p>wavadesk operates the platform available at <strong>wavadesk.com</strong>. For the purposes of applicable data protection law (including the GDPR), wavadesk acts as the data controller for account and usage data, and as a data processor for the customer conversation data that Tenants process through the platform.</p>

<h2 id="data-collected">2. Data We Collect</h2>

<h3>Account & Registration Data</h3>
<p>When you register, we collect your name, email address, company name, and payment details (processed securely by Stripe — we do not store full card numbers).</p>

<h3>Usage & Log Data</h3>
<p>We automatically collect data about how you use the Service, including IP addresses, browser type, pages visited, timestamps, and feature interactions. This helps us maintain security and improve the platform.</p>

<h3>Conversation & Message Data</h3>
<p>Messages sent and received through your WhatsApp instances are stored to provide the core conversation management features. This includes message content, timestamps, sender/recipient identifiers, and media attachments.</p>

<h3>WhatsApp Contact Data</h3>
<p>Customer phone numbers and profile metadata received from the WhatsApp Business API are stored to link incoming messages to customer records. Numbers using WhatsApp's LID privacy system are masked and cannot be resolved to a real phone number.</p>

<h2 id="legal-basis">3. Legal Basis for Processing</h2>
<p>We process your personal data on the following bases:</p>
<ul>
    <li><strong>Contractual necessity</strong> — to provide and operate the Service you subscribed to</li>
    <li><strong>Legitimate interests</strong> — to improve the platform, prevent fraud, and ensure security</li>
    <li><strong>Legal obligation</strong> — to comply with applicable laws and regulations</li>
    <li><strong>Consent</strong> — for optional features such as marketing communications</li>
</ul>

<h2 id="how-we-use">4. How We Use Your Data</h2>
<ul>
    <li>Providing, maintaining, and improving the Service</li>
    <li>Processing payments and managing subscriptions</li>
    <li>Sending transactional emails (account creation, password reset, billing receipts)</li>
    <li>Operating the AI auto-reply feature (message content is sent to the Anthropic API)</li>
    <li>Detecting and preventing fraud, abuse, or security incidents</li>
    <li>Complying with legal obligations and responding to lawful requests</li>
</ul>

<h2 id="sharing">5. Data Sharing</h2>
<p>We do not sell your personal data. We share data only with:</p>
<ul>
    <li><strong>Stripe</strong> — payment processing. <a href="https://stripe.com/privacy" target="_blank" rel="noopener">Stripe Privacy Policy</a></li>
    <li><strong>Anthropic</strong> — AI processing for auto-reply features. <a href="https://www.anthropic.com/privacy" target="_blank" rel="noopener">Anthropic Privacy Policy</a></li>
    <li><strong>WhatsApp / Meta</strong> — message delivery via the WhatsApp Business API</li>
    <li><strong>Infrastructure providers</strong> — hosting, database, and CDN services operating under data processing agreements</li>
    <li><strong>Legal authorities</strong> — when required by law or to protect the rights of wavadesk or others</li>
</ul>

<h2 id="retention">6. Data Retention</h2>
<p>We retain your data for as long as your account is active. After account termination:</p>
<ul>
    <li>Conversation and message data is deleted within 30 days</li>
    <li>Billing records are retained for 7 years as required by financial regulations</li>
    <li>Audit logs are retained for 2 years</li>
</ul>
<p>You may request earlier deletion of your data by contacting us (see Section 12).</p>

<h2 id="security">7. Security</h2>
<p>We implement industry-standard security measures including TLS encryption in transit, encrypted storage for sensitive fields, access controls, and regular security reviews. However, no system is completely secure — please use strong passwords and report any suspected breaches immediately.</p>

<h2 id="rights">8. Your Rights</h2>
<p>Depending on your location, you may have the following rights regarding your personal data:</p>
<ul>
    <li><strong>Access</strong> — request a copy of the data we hold about you</li>
    <li><strong>Rectification</strong> — correct inaccurate or incomplete data</li>
    <li><strong>Erasure</strong> — request deletion of your data ("right to be forgotten")</li>
    <li><strong>Portability</strong> — receive your data in a structured, machine-readable format</li>
    <li><strong>Restriction</strong> — request that we limit processing of your data</li>
    <li><strong>Objection</strong> — object to processing based on legitimate interests</li>
</ul>
<p>To exercise any of these rights, contact us at <a href="mailto:privacy@wavadesk.com">privacy@wavadesk.com</a>. We will respond within 30 days.</p>

<h2 id="cookies-ref">9. Cookies</h2>
<p>We use cookies and similar technologies to operate the platform. For full details, please read our <a href="{{ route('legal.cookies') }}">Cookie Policy</a>.</p>

<h2 id="transfers">10. International Data Transfers</h2>
<p>Your data may be processed in countries outside your own. When transferring data outside the EEA, we rely on appropriate safeguards such as Standard Contractual Clauses or adequacy decisions by the European Commission.</p>

<h2 id="changes-pp">11. Changes to This Policy</h2>
<p>We may update this Privacy Policy periodically. We will notify you of significant changes by email or by a notice on the platform. The "last updated" date at the top of this page indicates when the policy was last revised.</p>

<h2 id="contact-pp">12. Contact</h2>
<p>For privacy-related questions or to exercise your rights, contact our Data Protection team at <a href="mailto:privacy@wavadesk.com">privacy@wavadesk.com</a>.</p>
@endsection

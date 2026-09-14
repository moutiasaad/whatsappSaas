<?php

/*
 * Copy for the /features/<slug> pages.
 *
 * These strings feed the <head>, the hero, the table of contents and the FAQ —
 * and the FAQ entries here are the SAME source the FAQPage JSON-LD is built
 * from, so translating one always translates the other.
 *
 * The article bodies live in resources/views/features/content/<slug>.blade.php
 * and are English only; see the note in FeatureController.
 */
return [
    // Shared chrome
    'breadcrumb_home'     => 'Home',
    'breadcrumb_features' => 'Features',
    'on_this_page'        => 'On this page',
    'faq_title'           => 'Frequently asked questions',
    'related_title'       => 'Keep reading',
    'related_sub'         => 'Other parts of the platform that work with :topic.',
    'see_pricing'         => 'See pricing',
    'micro_trial'         => ':days-day free trial',
    'micro_nocard'        => 'No card at signup',
    'micro_from'          => 'From :price a month',
    'cta_title'           => 'Put your WhatsApp support on rails',
    'cta_sub'             => 'One shared inbox, claim locking so nobody double-replies, and an AI agent that answers from your own knowledge base.',

    'index_title'       => 'Features | wavadesk',
    'index_description' => 'Everything wavadesk does: a WhatsApp shared inbox with claim locking, an AI agent grounded in your knowledge base, live chat, teams and routing, OTP, reservations, reports and an API.',
    'index_h1'          => 'Everything wavadesk does',
    'index_lede'        => 'A shared inbox for WhatsApp and website chat, an AI agent that answers from your own documentation, and the modules that sit on top.',

    'whatsapp-shared-inbox' => [
        'title'       => 'WhatsApp Shared Inbox for Support Teams | wavadesk',
        'description' => 'Turn one WhatsApp Business number into a shared team inbox. Agents claim conversations, claims lock at the database level, and every action is logged.',
        'h1'          => 'A WhatsApp shared inbox your <em>whole team</em> can work from',
        'lede'        => 'Most support teams start by passing one phone around. wavadesk turns that number into a real queue: conversations land in a shared pool, an agent claims one to work it, and nobody ever answers the same customer twice.',
        'kicker'      => '',
        'nav_title'   => 'WhatsApp Shared Inbox',
        'nav_sub'     => 'One queue, claim locking, no double replies',
        'toc' => [
            0 => 'The one-phone problem',
            1 => 'How the shared inbox works',
            2 => 'Claim locking',
            3 => 'Customer history',
            4 => 'Audit trail',
            5 => 'Getting connected',
            6 => 'Who it is for',
            7 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Do I need a new phone number?',
                'a' => [
                    0 => 'No. You connect the WhatsApp Business number you already use by scanning a QR code, exactly as you would connect WhatsApp Web. There is no migration and no porting.',
                    1 => 'Your customers keep messaging the same number, so nothing changes on their side.',
                ],
            ],
            1 => [
                'q' => 'Can two agents reply to the same customer by accident?',
                'a' => [
                    0 => 'No. Claiming a conversation writes a lock, and a second agent cannot claim or send into a conversation that is already claimed. The lock is enforced in the database rather than only in the interface, so it holds even when several people click at the same time.',
                ],
            ],
            2 => [
                'q' => 'What happens to our history when an agent leaves?',
                'a' => [
                    0 => 'It stays with the workspace. Conversation history, notes and tags belong to the business, not to a device or a personal login, so removing an agent does not remove the record of what they handled.',
                ],
            ],
            3 => [
                'q' => 'Does it work alongside the WhatsApp app on my phone?',
                'a' => [
                    0 => 'We recommend running the number through wavadesk so the queue is the single source of truth. Replying directly from the handset bypasses claiming and the audit trail, which is exactly the behaviour a shared inbox exists to remove.',
                ],
            ],
            4 => [
                'q' => 'Is there a limit on conversations?',
                'a' => [
                    0 => 'No. Every plan includes unlimited WhatsApp conversations with no per-message fee and no Meta conversation fee. Plans differ on user seats, AI message allowance and which modules are switched on.',
                ],
            ],
            5 => [
                'q' => 'Can I see how long customers are waiting?',
                'a' => [
                    0 => 'Yes. The dashboard reports median first reply, resolution time, volume by channel and per-agent workload, and the queue itself surfaces the oldest unclaimed conversation so nothing quietly ages.',
                ],
            ],
        ],
    ],

    'whatsapp-multi-agent' => [
        'title'       => 'Multiple Agents on One WhatsApp Number | wavadesk',
        'description' => 'How to let a whole support team answer from a single WhatsApp Business number, with ownership, routing and no duplicate replies.',
        'h1'          => 'Let <em>many agents</em> answer one WhatsApp number',
        'lede'        => 'WhatsApp Business was built for one person holding one phone. Support teams are not one person. Here is how multi-agent access actually works, what breaks without it, and what to look for.',
        'kicker'      => '',
        'nav_title'   => 'Multiple Agents, One Number',
        'nav_sub'     => 'Many agents on one WhatsApp Business number',
        'toc' => [
            0 => 'The limitation',
            1 => 'The model',
            2 => 'Claiming',
            3 => 'Routing',
            4 => 'Seats',
            5 => 'Making the switch',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'How many agents can use one WhatsApp number?',
                'a' => [
                    0 => 'As many as your plan has seats for. Starter includes 3 users, Growth 5 and Scale 25, and you can add extra seats to any plan without upgrading.',
                    1 => 'All of them work from the same connected number.',
                ],
            ],
            1 => [
                'q' => 'Do agents need the WhatsApp app installed?',
                'a' => [
                    0 => 'No. Agents work entirely in wavadesk from a browser. Only the initial QR connection needs the phone that holds the WhatsApp Business account.',
                ],
            ],
            2 => [
                'q' => 'What stops two agents replying at once?',
                'a' => [
                    0 => 'Claiming. Taking a conversation writes a lock that prevents anyone else from sending into it, enforced in the database rather than only in the interface.',
                ],
            ],
            3 => [
                'q' => 'Can I limit what an agent sees?',
                'a' => [
                    0 => 'Yes. Routing by team means each agent\'s queue contains the conversations their team owns, rather than everything arriving on the number.',
                ],
            ],
            4 => [
                'q' => 'Is this the official WhatsApp Business API?',
                'a' => [
                    0 => 'wavadesk connects your existing WhatsApp Business number by QR, the same mechanism as WhatsApp Web, so there is no separate API application, no template approval process and no per-conversation Meta fee.',
                ],
            ],
            5 => [
                'q' => 'What happens if an agent goes offline mid-conversation?',
                'a' => [
                    0 => 'The conversation stays claimed to them and visible to the team. A lead can reassign it, and the reassignment is recorded so the handover is not invisible.',
                ],
            ],
        ],
    ],

    'ai-agent' => [
        'title'       => 'AI Agent for WhatsApp Customer Support | wavadesk',
        'description' => 'An AI agent that answers from your own knowledge base, in suggest, autonomous or hybrid mode, with escalation keywords that hand over to a human.',
        'h1'          => 'An AI agent that answers from <em>your</em> knowledge, not the internet',
        'lede'        => 'Most of what your team types every day, they have typed before. The wavadesk AI agent drafts those replies from your own FAQ, in your own wording — and steps back the moment a conversation needs a person.',
        'kicker'      => '',
        'nav_title'   => 'AI Agent',
        'nav_sub'     => 'Replies drafted from your own knowledge base',
        'toc' => [
            0 => 'What it does',
            1 => 'The three modes',
            2 => 'Staying accurate',
            3 => 'Escalation',
            4 => 'Measuring it',
            5 => 'Pricing',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Will the AI reply to my customers without me approving it?',
                'a' => [
                    0 => 'Only if you choose autonomous or hybrid mode. In suggest mode — the default we recommend starting with — the AI drafts and a human sends. You can change mode at any time.',
                ],
            ],
            1 => [
                'q' => 'Where does the AI get its answers?',
                'a' => [
                    0 => 'From the knowledge base you upload: your FAQ, policies, delivery terms and opening hours. It is grounded in your own material rather than general web knowledge, so it answers in your wording and within your policies.',
                ],
            ],
            2 => [
                'q' => 'What if it does not know the answer?',
                'a' => [
                    0 => 'It escalates to a human instead of guessing. A message that falls outside your knowledge, or that matches an escalation keyword, is handed to the relevant team queue for an agent to claim.',
                ],
            ],
            3 => [
                'q' => 'Can customers tell they are talking to AI?',
                'a' => [
                    0 => 'AI replies are clearly labelled inside your inbox so your team and any auditor can tell. What you disclose to customers is your choice, and many teams do say so in their welcome message.',
                ],
            ],
            4 => [
                'q' => 'What happens when I hit my AI message allowance?',
                'a' => [
                    0 => 'AI replies pause and conversations continue to your human agents as normal — nothing breaks and no customer is left unanswered. You can add a message pack mid-cycle to resume, or upgrade to Scale for unlimited.',
                ],
            ],
            5 => [
                'q' => 'How long does it take to set up?',
                'a' => [
                    0 => 'Pasting an existing FAQ takes minutes and is enough to start in suggest mode. Most teams then spend a week refining based on what the drafts reveal about gaps in their documentation.',
                ],
            ],
        ],
    ],

    'live-chat-widget' => [
        'title'       => 'Live Chat Widget for Your Website | wavadesk',
        'description' => 'Add website live chat that lands in the same shared inbox as your WhatsApp conversations, with visitor context and the same AI agent.',
        'h1'          => 'Website live chat in the <em>same inbox</em> as WhatsApp',
        'lede'        => 'A second channel should not mean a second queue. The wavadesk widget puts website conversations into the same shared pool your team already works, with the same claiming, the same AI and the same history.',
        'kicker'      => '',
        'nav_title'   => 'Live Chat Widget',
        'nav_sub'     => 'Website chat in the same inbox',
        'toc' => [
            0 => 'Why one inbox',
            1 => 'Installation',
            2 => 'Branding',
            3 => 'Visitor context',
            4 => 'AI on chat',
            5 => 'Performance',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Do website chats and WhatsApp chats go to the same place?',
                'a' => [
                    0 => 'Yes. Both land in the same shared inbox, in one queue, with a badge showing which channel each came from. Agents claim and reply the same way regardless of channel.',
                ],
            ],
            1 => [
                'q' => 'How do I install the widget?',
                'a' => [
                    0 => 'Paste one script tag before the closing body tag of your site. It works on any platform that serves HTML — static sites, WordPress, Shopify, Laravel and so on.',
                ],
            ],
            2 => [
                'q' => 'Can I change how the widget looks?',
                'a' => [
                    0 => 'Yes. Accent colour, position, greeting text, pre-chat fields and availability hours are all configurable, with a live preview beside the controls.',
                ],
            ],
            3 => [
                'q' => 'What do agents see about an anonymous visitor?',
                'a' => [
                    0 => 'The session: current page, page journey, referrer, browser, device and time on site. If the visitor identifies themselves, their history from other channels is linked in.',
                ],
            ],
            4 => [
                'q' => 'Can the AI answer website chats automatically?',
                'a' => [
                    0 => 'Yes, and you can set the mode per channel — for example autonomous on the widget for instant answers, suggest-only on WhatsApp. Escalation keywords apply on both.',
                ],
            ],
            5 => [
                'q' => 'Will the widget slow my site down?',
                'a' => [
                    0 => 'It loads asynchronously and does not block rendering. The launcher appears after your page is interactive.',
                ],
            ],
        ],
    ],

    'otp-service' => [
        'title'       => 'WhatsApp OTP & Verification Service | wavadesk',
        'description' => 'Send one-time passcodes over WhatsApp instead of SMS. Higher delivery rates, lower cost per message and full delivery reporting.',
        'h1'          => 'Send one-time passcodes over <em>WhatsApp</em>, not SMS',
        'lede'        => 'SMS verification is expensive, slow in some networks and easy for customers to miss. The wavadesk OTP service delivers passcodes through the WhatsApp number your customers already talk to you on.',
        'kicker'      => '',
        'nav_title'   => 'OTP & Verification',
        'nav_sub'     => 'Verification codes over WhatsApp',
        'toc' => [
            0 => 'What it does',
            1 => 'Why not SMS',
            2 => 'How it works',
            3 => 'Security',
            4 => 'Reporting',
            5 => 'Use cases',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Is sending OTPs over WhatsApp secure?',
                'a' => [
                    0 => 'The transport is end-to-end encrypted, codes are stored hashed and never returned by the send endpoint, and every code is single-use with a short expiry and an attempt limit. Rate limits per number and per API key are on by default.',
                ],
            ],
            1 => [
                'q' => 'What if a customer does not use WhatsApp?',
                'a' => [
                    0 => 'The module reports non-delivery, so your backend can fall back to SMS or email. Most teams send over WhatsApp first and keep an SMS fallback for coverage.',
                ],
            ],
            2 => [
                'q' => 'How much does each OTP cost?',
                'a' => [
                    0 => 'Nothing per message. WhatsApp conversations are unlimited on every plan, so additional verifications do not add cost. The OTP module itself is included on Scale and available as an add-on to other plans.',
                ],
            ],
            3 => [
                'q' => 'How long is a code valid for?',
                'a' => [
                    0 => 'You configure the expiry window, and codes fail closed once it passes. A short window is the safer default; widen it only if you see legitimate users timing out.',
                ],
            ],
            4 => [
                'q' => 'Can I see whether the code was delivered?',
                'a' => [
                    0 => 'Yes. WhatsApp provides delivery and read status, and the reporting surface shows sent, delivered and verified counts so you can find drop-off in the verification funnel.',
                ],
            ],
            5 => [
                'q' => 'Do I need a separate WhatsApp number for OTPs?',
                'a' => [
                    0 => 'No. Codes are sent from the same connected business number your customers already message, which is part of why they are trusted more than an unknown shortcode.',
                ],
            ],
        ],
    ],

    'reservations' => [
        'title'       => 'Reservations & Appointment Booking on WhatsApp | wavadesk',
        'description' => 'Take bookings in the conversation your customer already started, and cut no-shows with automatic WhatsApp reminders.',
        'h1'          => 'Take bookings <em>in the conversation</em>, not on a form',
        'lede'        => 'Most booking systems ask a customer to leave the conversation and fill in a form. The reservations module keeps it where they already are: the appointment is agreed in the chat, held in your calendar and confirmed by an automatic reminder.',
        'kicker'      => '',
        'nav_title'   => 'Reservations & Booking',
        'nav_sub'     => 'Bookings inside the conversation',
        'toc' => [
            0 => 'Why WhatsApp',
            1 => 'The booking flow',
            2 => 'No-shows',
            3 => 'Calendar',
            4 => 'Verification',
            5 => 'Who it is for',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Can customers book without leaving WhatsApp?',
                'a' => [
                    0 => 'Yes. That is the point of the module — availability is offered and the slot is confirmed inside the conversation, with no redirect to a booking page.',
                ],
            ],
            1 => [
                'q' => 'Does it prevent double-booking?',
                'a' => [
                    0 => 'Yes. Availability comes from your configured hours, service durations, resources and existing bookings, so a slot that is taken is never offered again — whether the offer comes from the AI or from a staff member.',
                ],
            ],
            2 => [
                'q' => 'Can the AI handle bookings on its own?',
                'a' => [
                    0 => 'It can handle the common path: offering availability, taking a pick and confirming. Anything unusual — a specific practitioner, a special case — escalates to an agent who books it manually.',
                ],
            ],
            3 => [
                'q' => 'How do reminders work?',
                'a' => [
                    0 => 'An immediate confirmation, then reminders on the schedule you set, typically 24 hours and 2 hours before. They arrive in the existing WhatsApp thread and offer confirm or reschedule as a direct reply.',
                ],
            ],
            4 => [
                'q' => 'Can I require phone verification before holding a slot?',
                'a' => [
                    0 => 'Yes, by pairing reservations with the OTP module. The customer confirms their number with a one-time code before the booking is held, which removes fake and mistyped-number bookings.',
                ],
            ],
            5 => [
                'q' => 'Does it work with multiple staff or rooms?',
                'a' => [
                    0 => 'Yes. Each resource — practitioner, chair, bay or table — has its own schedule and capacity, and bookings are allocated against them.',
                ],
            ],
        ],
    ],

    'knowledge-base' => [
        'title'       => 'Knowledge Base & Saved Replies for Support | wavadesk',
        'description' => 'The single source your AI answers from and your agents reuse. Write an answer once and it serves automated replies, agent snippets and self-serve customers.',
        'h1'          => 'Write the answer <em>once</em>. Use it everywhere.',
        'lede'        => 'Your team already knows the answers — they are just scattered across people\'s heads, old chat logs and a document nobody updated. The knowledge base makes that the one source your AI answers from and your agents reuse.',
        'kicker'      => '',
        'nav_title'   => 'Knowledge Base',
        'nav_sub'     => 'Write the answer once, use it everywhere',
        'toc' => [
            0 => 'One source',
            1 => 'Structure',
            2 => 'Powering the AI',
            3 => 'Saved replies',
            4 => 'Maintenance',
            5 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'What format should I upload?',
                'a' => [
                    0 => 'Plain text or pasted FAQ content is enough to start. Short, single-topic entries written the way you would message a customer work better than long policy documents.',
                ],
            ],
            1 => [
                'q' => 'Do the AI and my agents use the same source?',
                'a' => [
                    0 => 'Yes. That is the design — one store feeds AI replies and agent saved replies, so an automated answer and a human answer cannot contradict each other.',
                ],
            ],
            2 => [
                'q' => 'How much do I need before switching the AI on?',
                'a' => [
                    0 => 'Your top twenty questions is a practical starting point and covers most volume. Run in suggest mode from there and let the drafts show you what is missing.',
                ],
            ],
            3 => [
                'q' => 'What happens when the AI cannot find an answer?',
                'a' => [
                    0 => 'It escalates to a human rather than guessing. Every recurring escalation is a signal about which entry to write next.',
                ],
            ],
            4 => [
                'q' => 'Can different teams have their own entries?',
                'a' => [
                    0 => 'Yes. Entries and saved replies can be scoped by team, so each queue sees the material relevant to it.',
                ],
            ],
            5 => [
                'q' => 'Can customers see the knowledge base directly?',
                'a' => [
                    0 => 'Self-serve content is on the roadmap. Today the knowledge base powers AI replies and agent saved replies inside your workspace.',
                ],
            ],
        ],
    ],

    'teams-routing' => [
        'title'       => 'Teams, Routing & Assignment for Support | wavadesk',
        'description' => 'Send each conversation to the team that owns it. Route by team, keyword and hours, with escalation when a conversation ages.',
        'h1'          => 'Route each conversation to <em>the team that owns it</em>',
        'lede'        => 'A single shared queue is right for three agents and wrong for fifteen. Routing gives each team the subset of conversations they can actually act on, so nobody scrolls past thirty tickets that are not theirs.',
        'kicker'      => '',
        'nav_title'   => 'Teams & Routing',
        'nav_sub'     => 'Send work to the team that owns it',
        'toc' => [
            0 => 'Why route',
            1 => 'Teams',
            2 => 'Routing rules',
            3 => 'Assignment',
            4 => 'Escalation',
            5 => 'Measuring it',
            6 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Do I need routing straight away?',
                'a' => [
                    0 => 'No. Start with one shared pool and claiming. Routing earns its keep somewhere around five or six agents, or sooner if your teams handle genuinely different subject matter.',
                ],
            ],
            1 => [
                'q' => 'Can an agent be in more than one team?',
                'a' => [
                    0 => 'Yes. Small teams often put everyone in every team at first and narrow the boundaries as traffic shows where they actually are.',
                ],
            ],
            2 => [
                'q' => 'What happens to a conversation that matches no rule?',
                'a' => [
                    0 => 'It lands in your default queue. Every routing setup should have one, otherwise unmatched conversations have nowhere to go.',
                ],
            ],
            3 => [
                'q' => 'Should I use auto-assignment or claiming?',
                'a' => [
                    0 => 'Claiming, in most cases. Round-robin distributes work to agents who may be mid-conversation and removes their ability to pick up what they are best placed to handle. Auto-assignment works where availability status is reliable.',
                ],
            ],
            4 => [
                'q' => 'How do I stop conversations sitting unclaimed?',
                'a' => [
                    0 => 'Set an age threshold and escalate on breach — to a lead\'s queue or flagged at the top of the team queue. The inbox also surfaces the longest-waiting conversation so the problem is visible early.',
                ],
            ],
            5 => [
                'q' => 'Can I route by language?',
                'a' => [
                    0 => 'Yes. Teams can be organised by language and routed accordingly, which matters when not every agent covers every language you serve.',
                ],
            ],
        ],
    ],

    'reports-analytics' => [
        'title'       => 'Support Reports & Analytics | wavadesk',
        'description' => 'Median first reply, resolution time, volume by channel, agent workload and AI deflection — the numbers you can actually manage against.',
        'h1'          => 'The support numbers you can <em>actually manage</em> against',
        'lede'        => 'Most teams running WhatsApp support have no figures at all — they have impressions. Reporting turns response time, volume and workload into something you can set a target on and see move.',
        'kicker'      => '',
        'nav_title'   => 'Reports & Analytics',
        'nav_sub'     => 'Response time, volume, workload',
        'toc' => [
            0 => 'The blind spot',
            1 => 'Core metrics',
            2 => 'Volume',
            3 => 'Response time',
            4 => 'Workload',
            5 => 'AI performance',
            6 => 'Weekly review',
            7 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Which metrics are included?',
                'a' => [
                    0 => 'Conversation volume by channel and day, median first reply, resolution time, conversations waiting in the pool with the oldest wait, per-agent workload against capacity, busiest hours by day and hour, and AI deflection split into resolved, assisted and escalated.',
                ],
            ],
            1 => [
                'q' => 'Is reporting available on every plan?',
                'a' => [
                    0 => 'Core volume, response-time and AI reporting are available on all plans. Per-team breakdowns and the fuller reporting set come with Growth and above.',
                ],
            ],
            2 => [
                'q' => 'Why median first reply and not average?',
                'a' => [
                    0 => 'A small number of conversations that arrive overnight and are answered in the morning will drag an average into meaninglessness. The median reflects what a typical customer actually experiences.',
                ],
            ],
            3 => [
                'q' => 'Can I export the data?',
                'a' => [
                    0 => 'Yes. Reports can be exported for the period you select, so you can combine them with other business data or keep a longer history outside the workspace.',
                ],
            ],
            4 => [
                'q' => 'Does the AI count as an agent in workload reporting?',
                'a' => [
                    0 => 'No. AI activity is reported separately as deflection, so human workload figures are not flattered by automated replies.',
                ],
            ],
            5 => [
                'q' => 'How far back does history go?',
                'a' => [
                    0 => 'Your full workspace history is retained and reportable. The dashboard defaults to the last 14 days, with 7 and 30-day views selectable.',
                ],
            ],
        ],
    ],

    'api-integrations' => [
        'title'       => 'API & Integrations | wavadesk',
        'description' => 'Push conversations, contacts and events between wavadesk and your stack. REST endpoints, webhooks and the OTP API.',
        'h1'          => 'Connect wavadesk to <em>the rest of your stack</em>',
        'lede'        => 'Support does not live in isolation. The API and webhooks let your own systems read conversations, create contacts, send verification codes and react to events as they happen.',
        'kicker'      => '',
        'nav_title'   => 'API & Integrations',
        'nav_sub'     => 'REST, webhooks and the OTP API',
        'toc' => [
            0 => 'Why integrate',
            1 => 'Authentication',
            2 => 'REST API',
            3 => 'Webhooks',
            4 => 'OTP API',
            5 => 'Embedding',
            6 => 'Limits',
            7 => 'FAQ',
        ],
        'faq' => [
            0 => [
                'q' => 'Which plan includes API access?',
                'a' => [
                    0 => 'API access, webhooks and the OTP endpoints are included on the Scale plan. Other plans can add the OTP module separately.',
                ],
            ],
            1 => [
                'q' => 'How do I authenticate?',
                'a' => [
                    0 => 'With a workspace-scoped bearer token created in settings. Tokens are revocable, scoped to a single workspace, and should be kept server-side — never in front-end code.',
                ],
            ],
            2 => [
                'q' => 'Can I push my own customer data into wavadesk?',
                'a' => [
                    0 => 'Yes, and it is usually the first integration worth building. Writing custom fields onto the contact record means agents see your order history, plan or account status in the context panel while replying.',
                ],
            ],
            3 => [
                'q' => 'What events can webhooks send?',
                'a' => [
                    0 => 'Conversation created, claimed, resolved and escalated; message received and sent; contact created and updated; and OTP verified. Payloads are signed so you can verify them.',
                ],
            ],
            4 => [
                'q' => 'What happens if my webhook endpoint is down?',
                'a' => [
                    0 => 'Deliveries are retried with backoff. Respond 2xx quickly and process asynchronously — slow handling inside the request will time out and cause duplicate deliveries.',
                ],
            ],
            5 => [
                'q' => 'Will the API change without warning?',
                'a' => [
                    0 => 'Breaking changes ship behind a version and existing versions stay supported. Additive changes such as new fields or event types can arrive without a version bump, so parse payloads defensively.',
                ],
            ],
        ],
    ],

];

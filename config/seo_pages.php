<?php

/*
 * The ten feature pages served at /features/<slug>.
 *
 * Copy comes from the design handoff and is final. Each page's article body
 * lives in resources/views/features/content/<slug>.blade.php; everything the
 * <head>, the sticky table of contents, the FAQ accordions and the JSON-LD
 * need lives here.
 *
 * `faq` feeds BOTH the visible accordions and the FAQPage structured data, so
 * the two can never drift apart — which is exactly what Google penalises.
 * `toc` ids must match the section ids in the matching content partial.
 */
return [
    'whatsapp-shared-inbox' => [
        'og_image' => 'inbox-full.png',
            'title' => 'WhatsApp Shared Inbox for Support Teams | wavadesk',
            'description' => 'Turn one WhatsApp Business number into a shared team inbox. Agents claim conversations, claims lock at the database level, and every action is logged.',
            'h1' => 'A WhatsApp shared inbox your <em>whole team</em> can work from',
            'kicker' => '',
            'lede' => 'Most support teams start by passing one phone around. wavadesk turns that number into a real queue: conversations land in a shared pool, an agent claims one to work it, and nobody ever answers the same customer twice.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'problem',
                    'label' => 'The one-phone problem',
                ],
                [
                    'id' => 'how',
                    'label' => 'How the shared inbox works',
                ],
                [
                    'id' => 'claim',
                    'label' => 'Claim locking',
                ],
                [
                    'id' => 'context',
                    'label' => 'Customer history',
                ],
                [
                    'id' => 'audit',
                    'label' => 'Audit trail',
                ],
                [
                    'id' => 'setup',
                    'label' => 'Getting connected',
                ],
                [
                    'id' => 'who',
                    'label' => 'Who it is for',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Do I need a new phone number?',
                    'a' => [
                        'No. You connect the WhatsApp Business number you already use by scanning a QR code, exactly as you would connect WhatsApp Web. There is no migration and no porting.',
                        'Your customers keep messaging the same number, so nothing changes on their side.',
                    ],
                ],
                [
                    'q' => 'Can two agents reply to the same customer by accident?',
                    'a' => [
                        'No. Claiming a conversation writes a lock, and a second agent cannot claim or send into a conversation that is already claimed. The lock is enforced in the database rather than only in the interface, so it holds even when several people click at the same time.',
                    ],
                ],
                [
                    'q' => 'What happens to our history when an agent leaves?',
                    'a' => [
                        'It stays with the workspace. Conversation history, notes and tags belong to the business, not to a device or a personal login, so removing an agent does not remove the record of what they handled.',
                    ],
                ],
                [
                    'q' => 'Does it work alongside the WhatsApp app on my phone?',
                    'a' => [
                        'We recommend running the number through wavadesk so the queue is the single source of truth. Replying directly from the handset bypasses claiming and the audit trail, which is exactly the behaviour a shared inbox exists to remove.',
                    ],
                ],
                [
                    'q' => 'Is there a limit on conversations?',
                    'a' => [
                        'No. Every plan includes unlimited WhatsApp conversations with no per-message fee and no Meta conversation fee. Plans differ on user seats, AI message allowance and which modules are switched on.',
                    ],
                ],
                [
                    'q' => 'Can I see how long customers are waiting?',
                    'a' => [
                        'Yes. The dashboard reports median first reply, resolution time, volume by channel and per-agent workload, and the queue itself surfaces the oldest unclaimed conversation so nothing quietly ages.',
                    ],
                ],
            ],
            'related' => [
                'whatsapp-multi-agent',
                'teams-routing',
                'ai-agent',
            ],
        ],

    'whatsapp-multi-agent' => [
        'og_image' => 'inbox-full.png',
            'title' => 'Multiple Agents on One WhatsApp Number | wavadesk',
            'description' => 'How to let a whole support team answer from a single WhatsApp Business number, with ownership, routing and no duplicate replies.',
            'h1' => 'Let <em>many agents</em> answer one WhatsApp number',
            'kicker' => '',
            'lede' => 'WhatsApp Business was built for one person holding one phone. Support teams are not one person. Here is how multi-agent access actually works, what breaks without it, and what to look for.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'The limitation',
                ],
                [
                    'id' => 'model',
                    'label' => 'The model',
                ],
                [
                    'id' => 'claiming',
                    'label' => 'Claiming',
                ],
                [
                    'id' => 'routing',
                    'label' => 'Routing',
                ],
                [
                    'id' => 'seats',
                    'label' => 'Seats',
                ],
                [
                    'id' => 'migrate',
                    'label' => 'Making the switch',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'How many agents can use one WhatsApp number?',
                    'a' => [
                        'As many as your plan has seats for. Starter includes 3 users, Growth 5 and Scale 25, and you can add extra seats to any plan without upgrading.',
                        'All of them work from the same connected number.',
                    ],
                ],
                [
                    'q' => 'Do agents need the WhatsApp app installed?',
                    'a' => [
                        'No. Agents work entirely in wavadesk from a browser. Only the initial QR connection needs the phone that holds the WhatsApp Business account.',
                    ],
                ],
                [
                    'q' => 'What stops two agents replying at once?',
                    'a' => [
                        'Claiming. Taking a conversation writes a lock that prevents anyone else from sending into it, enforced in the database rather than only in the interface.',
                    ],
                ],
                [
                    'q' => 'Can I limit what an agent sees?',
                    'a' => [
                        'Yes. Routing by team means each agent\'s queue contains the conversations their team owns, rather than everything arriving on the number.',
                    ],
                ],
                [
                    'q' => 'Is this the official WhatsApp Business API?',
                    'a' => [
                        'wavadesk connects your existing WhatsApp Business number by QR, the same mechanism as WhatsApp Web, so there is no separate API application, no template approval process and no per-conversation Meta fee.',
                    ],
                ],
                [
                    'q' => 'What happens if an agent goes offline mid-conversation?',
                    'a' => [
                        'The conversation stays claimed to them and visible to the team. A lead can reassign it, and the reassignment is recorded so the handover is not invisible.',
                    ],
                ],
            ],
            'related' => [
                'whatsapp-shared-inbox',
                'teams-routing',
                'reports-analytics',
            ],
        ],

    'ai-agent' => [
        'og_image' => 'ai-performance.png',
            'title' => 'AI Agent for WhatsApp Customer Support | wavadesk',
            'description' => 'An AI agent that answers from your own knowledge base, in suggest, autonomous or hybrid mode, with escalation keywords that hand over to a human.',
            'h1' => 'An AI agent that answers from <em>your</em> knowledge, not the internet',
            'kicker' => '',
            'lede' => 'Most of what your team types every day, they have typed before. The wavadesk AI agent drafts those replies from your own FAQ, in your own wording — and steps back the moment a conversation needs a person.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'what',
                    'label' => 'What it does',
                ],
                [
                    'id' => 'modes',
                    'label' => 'The three modes',
                ],
                [
                    'id' => 'grounding',
                    'label' => 'Staying accurate',
                ],
                [
                    'id' => 'escalation',
                    'label' => 'Escalation',
                ],
                [
                    'id' => 'measure',
                    'label' => 'Measuring it',
                ],
                [
                    'id' => 'cost',
                    'label' => 'Pricing',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Will the AI reply to my customers without me approving it?',
                    'a' => [
                        'Only if you choose autonomous or hybrid mode. In suggest mode — the default we recommend starting with — the AI drafts and a human sends. You can change mode at any time.',
                    ],
                ],
                [
                    'q' => 'Where does the AI get its answers?',
                    'a' => [
                        'From the knowledge base you upload: your FAQ, policies, delivery terms and opening hours. It is grounded in your own material rather than general web knowledge, so it answers in your wording and within your policies.',
                    ],
                ],
                [
                    'q' => 'What if it does not know the answer?',
                    'a' => [
                        'It escalates to a human instead of guessing. A message that falls outside your knowledge, or that matches an escalation keyword, is handed to the relevant team queue for an agent to claim.',
                    ],
                ],
                [
                    'q' => 'Can customers tell they are talking to AI?',
                    'a' => [
                        'AI replies are clearly labelled inside your inbox so your team and any auditor can tell. What you disclose to customers is your choice, and many teams do say so in their welcome message.',
                    ],
                ],
                [
                    'q' => 'What happens when I hit my AI message allowance?',
                    'a' => [
                        'AI replies pause and conversations continue to your human agents as normal — nothing breaks and no customer is left unanswered. You can add a message pack mid-cycle to resume, or upgrade to Scale for unlimited.',
                    ],
                ],
                [
                    'q' => 'How long does it take to set up?',
                    'a' => [
                        'Pasting an existing FAQ takes minutes and is enough to start in suggest mode. Most teams then spend a week refining based on what the drafts reveal about gaps in their documentation.',
                    ],
                ],
            ],
            'related' => [
                'knowledge-base',
                'whatsapp-shared-inbox',
                'reports-analytics',
            ],
        ],

    'live-chat-widget' => [
        'og_image' => 'inbox-full.png',
            'title' => 'Live Chat Widget for Your Website | wavadesk',
            'description' => 'Add website live chat that lands in the same shared inbox as your WhatsApp conversations, with visitor context and the same AI agent.',
            'h1' => 'Website live chat in the <em>same inbox</em> as WhatsApp',
            'kicker' => '',
            'lede' => 'A second channel should not mean a second queue. The wavadesk widget puts website conversations into the same shared pool your team already works, with the same claiming, the same AI and the same history.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'Why one inbox',
                ],
                [
                    'id' => 'install',
                    'label' => 'Installation',
                ],
                [
                    'id' => 'customise',
                    'label' => 'Branding',
                ],
                [
                    'id' => 'context',
                    'label' => 'Visitor context',
                ],
                [
                    'id' => 'ai',
                    'label' => 'AI on chat',
                ],
                [
                    'id' => 'perf',
                    'label' => 'Performance',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Do website chats and WhatsApp chats go to the same place?',
                    'a' => [
                        'Yes. Both land in the same shared inbox, in one queue, with a badge showing which channel each came from. Agents claim and reply the same way regardless of channel.',
                    ],
                ],
                [
                    'q' => 'How do I install the widget?',
                    'a' => [
                        'Paste one script tag before the closing body tag of your site. It works on any platform that serves HTML — static sites, WordPress, Shopify, Laravel and so on.',
                    ],
                ],
                [
                    'q' => 'Can I change how the widget looks?',
                    'a' => [
                        'Yes. Accent colour, position, greeting text, pre-chat fields and availability hours are all configurable, with a live preview beside the controls.',
                    ],
                ],
                [
                    'q' => 'What do agents see about an anonymous visitor?',
                    'a' => [
                        'The session: current page, page journey, referrer, browser, device and time on site. If the visitor identifies themselves, their history from other channels is linked in.',
                    ],
                ],
                [
                    'q' => 'Can the AI answer website chats automatically?',
                    'a' => [
                        'Yes, and you can set the mode per channel — for example autonomous on the widget for instant answers, suggest-only on WhatsApp. Escalation keywords apply on both.',
                    ],
                ],
                [
                    'q' => 'Will the widget slow my site down?',
                    'a' => [
                        'It loads asynchronously and does not block rendering. The launcher appears after your page is interactive.',
                    ],
                ],
            ],
            'related' => [
                'whatsapp-shared-inbox',
                'ai-agent',
                'api-integrations',
            ],
        ],

    'otp-service' => [
        'og_image' => 'kpis.png',
            'title' => 'WhatsApp OTP & Verification Service | wavadesk',
            'description' => 'Send one-time passcodes over WhatsApp instead of SMS. Higher delivery rates, lower cost per message and full delivery reporting.',
            'h1' => 'Send one-time passcodes over <em>WhatsApp</em>, not SMS',
            'kicker' => '',
            'lede' => 'SMS verification is expensive, slow in some networks and easy for customers to miss. The wavadesk OTP service delivers passcodes through the WhatsApp number your customers already talk to you on.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'what',
                    'label' => 'What it does',
                ],
                [
                    'id' => 'why',
                    'label' => 'Why not SMS',
                ],
                [
                    'id' => 'how',
                    'label' => 'How it works',
                ],
                [
                    'id' => 'security',
                    'label' => 'Security',
                ],
                [
                    'id' => 'reporting',
                    'label' => 'Reporting',
                ],
                [
                    'id' => 'cases',
                    'label' => 'Use cases',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Is sending OTPs over WhatsApp secure?',
                    'a' => [
                        'The transport is end-to-end encrypted, codes are stored hashed and never returned by the send endpoint, and every code is single-use with a short expiry and an attempt limit. Rate limits per number and per API key are on by default.',
                    ],
                ],
                [
                    'q' => 'What if a customer does not use WhatsApp?',
                    'a' => [
                        'The module reports non-delivery, so your backend can fall back to SMS or email. Most teams send over WhatsApp first and keep an SMS fallback for coverage.',
                    ],
                ],
                [
                    'q' => 'How much does each OTP cost?',
                    'a' => [
                        'Nothing per message. WhatsApp conversations are unlimited on every plan, so additional verifications do not add cost. The OTP module itself is included on Scale and available as an add-on to other plans.',
                    ],
                ],
                [
                    'q' => 'How long is a code valid for?',
                    'a' => [
                        'You configure the expiry window, and codes fail closed once it passes. A short window is the safer default; widen it only if you see legitimate users timing out.',
                    ],
                ],
                [
                    'q' => 'Can I see whether the code was delivered?',
                    'a' => [
                        'Yes. WhatsApp provides delivery and read status, and the reporting surface shows sent, delivered and verified counts so you can find drop-off in the verification funnel.',
                    ],
                ],
                [
                    'q' => 'Do I need a separate WhatsApp number for OTPs?',
                    'a' => [
                        'No. Codes are sent from the same connected business number your customers already message, which is part of why they are trusted more than an unknown shortcode.',
                    ],
                ],
            ],
            'related' => [
                'api-integrations',
                'whatsapp-shared-inbox',
                'reports-analytics',
            ],
        ],

    'reservations' => [
        'og_image' => 'inbox-full.png',
            'title' => 'Reservations & Appointment Booking on WhatsApp | wavadesk',
            'description' => 'Take bookings in the conversation your customer already started, and cut no-shows with automatic WhatsApp reminders.',
            'h1' => 'Take bookings <em>in the conversation</em>, not on a form',
            'kicker' => '',
            'lede' => 'Most booking systems ask a customer to leave the conversation and fill in a form. The reservations module keeps it where they already are: the appointment is agreed in the chat, held in your calendar and confirmed by an automatic reminder.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'Why WhatsApp',
                ],
                [
                    'id' => 'flow',
                    'label' => 'The booking flow',
                ],
                [
                    'id' => 'noshows',
                    'label' => 'No-shows',
                ],
                [
                    'id' => 'calendar',
                    'label' => 'Calendar',
                ],
                [
                    'id' => 'verify',
                    'label' => 'Verification',
                ],
                [
                    'id' => 'who',
                    'label' => 'Who it is for',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Can customers book without leaving WhatsApp?',
                    'a' => [
                        'Yes. That is the point of the module — availability is offered and the slot is confirmed inside the conversation, with no redirect to a booking page.',
                    ],
                ],
                [
                    'q' => 'Does it prevent double-booking?',
                    'a' => [
                        'Yes. Availability comes from your configured hours, service durations, resources and existing bookings, so a slot that is taken is never offered again — whether the offer comes from the AI or from a staff member.',
                    ],
                ],
                [
                    'q' => 'Can the AI handle bookings on its own?',
                    'a' => [
                        'It can handle the common path: offering availability, taking a pick and confirming. Anything unusual — a specific practitioner, a special case — escalates to an agent who books it manually.',
                    ],
                ],
                [
                    'q' => 'How do reminders work?',
                    'a' => [
                        'An immediate confirmation, then reminders on the schedule you set, typically 24 hours and 2 hours before. They arrive in the existing WhatsApp thread and offer confirm or reschedule as a direct reply.',
                    ],
                ],
                [
                    'q' => 'Can I require phone verification before holding a slot?',
                    'a' => [
                        'Yes, by pairing reservations with the OTP module. The customer confirms their number with a one-time code before the booking is held, which removes fake and mistyped-number bookings.',
                    ],
                ],
                [
                    'q' => 'Does it work with multiple staff or rooms?',
                    'a' => [
                        'Yes. Each resource — practitioner, chair, bay or table — has its own schedule and capacity, and bookings are allocated against them.',
                    ],
                ],
            ],
            'related' => [
                'whatsapp-shared-inbox',
                'ai-agent',
                'otp-service',
            ],
        ],

    'knowledge-base' => [
        'og_image' => 'ai-performance.png',
            'title' => 'Knowledge Base & Saved Replies for Support | wavadesk',
            'description' => 'The single source your AI answers from and your agents reuse. Write an answer once and it serves automated replies, agent snippets and self-serve customers.',
            'h1' => 'Write the answer <em>once</em>. Use it everywhere.',
            'kicker' => '',
            'lede' => 'Your team already knows the answers — they are just scattered across people\'s heads, old chat logs and a document nobody updated. The knowledge base makes that the one source your AI answers from and your agents reuse.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'One source',
                ],
                [
                    'id' => 'structure',
                    'label' => 'Structure',
                ],
                [
                    'id' => 'ai',
                    'label' => 'Powering the AI',
                ],
                [
                    'id' => 'saved',
                    'label' => 'Saved replies',
                ],
                [
                    'id' => 'maintain',
                    'label' => 'Maintenance',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'What format should I upload?',
                    'a' => [
                        'Plain text or pasted FAQ content is enough to start. Short, single-topic entries written the way you would message a customer work better than long policy documents.',
                    ],
                ],
                [
                    'q' => 'Do the AI and my agents use the same source?',
                    'a' => [
                        'Yes. That is the design — one store feeds AI replies and agent saved replies, so an automated answer and a human answer cannot contradict each other.',
                    ],
                ],
                [
                    'q' => 'How much do I need before switching the AI on?',
                    'a' => [
                        'Your top twenty questions is a practical starting point and covers most volume. Run in suggest mode from there and let the drafts show you what is missing.',
                    ],
                ],
                [
                    'q' => 'What happens when the AI cannot find an answer?',
                    'a' => [
                        'It escalates to a human rather than guessing. Every recurring escalation is a signal about which entry to write next.',
                    ],
                ],
                [
                    'q' => 'Can different teams have their own entries?',
                    'a' => [
                        'Yes. Entries and saved replies can be scoped by team, so each queue sees the material relevant to it.',
                    ],
                ],
                [
                    'q' => 'Can customers see the knowledge base directly?',
                    'a' => [
                        'Self-serve content is on the roadmap. Today the knowledge base powers AI replies and agent saved replies inside your workspace.',
                    ],
                ],
            ],
            'related' => [
                'ai-agent',
                'whatsapp-shared-inbox',
                'reports-analytics',
            ],
        ],

    'teams-routing' => [
        'og_image' => 'agents-heatmap.png',
            'title' => 'Teams, Routing & Assignment for Support | wavadesk',
            'description' => 'Send each conversation to the team that owns it. Route by team, keyword and hours, with escalation when a conversation ages.',
            'h1' => 'Route each conversation to <em>the team that owns it</em>',
            'kicker' => '',
            'lede' => 'A single shared queue is right for three agents and wrong for fifteen. Routing gives each team the subset of conversations they can actually act on, so nobody scrolls past thirty tickets that are not theirs.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'Why route',
                ],
                [
                    'id' => 'teams',
                    'label' => 'Teams',
                ],
                [
                    'id' => 'rules',
                    'label' => 'Routing rules',
                ],
                [
                    'id' => 'assignment',
                    'label' => 'Assignment',
                ],
                [
                    'id' => 'escalation',
                    'label' => 'Escalation',
                ],
                [
                    'id' => 'measure',
                    'label' => 'Measuring it',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Do I need routing straight away?',
                    'a' => [
                        'No. Start with one shared pool and claiming. Routing earns its keep somewhere around five or six agents, or sooner if your teams handle genuinely different subject matter.',
                    ],
                ],
                [
                    'q' => 'Can an agent be in more than one team?',
                    'a' => [
                        'Yes. Small teams often put everyone in every team at first and narrow the boundaries as traffic shows where they actually are.',
                    ],
                ],
                [
                    'q' => 'What happens to a conversation that matches no rule?',
                    'a' => [
                        'It lands in your default queue. Every routing setup should have one, otherwise unmatched conversations have nowhere to go.',
                    ],
                ],
                [
                    'q' => 'Should I use auto-assignment or claiming?',
                    'a' => [
                        'Claiming, in most cases. Round-robin distributes work to agents who may be mid-conversation and removes their ability to pick up what they are best placed to handle. Auto-assignment works where availability status is reliable.',
                    ],
                ],
                [
                    'q' => 'How do I stop conversations sitting unclaimed?',
                    'a' => [
                        'Set an age threshold and escalate on breach — to a lead\'s queue or flagged at the top of the team queue. The inbox also surfaces the longest-waiting conversation so the problem is visible early.',
                    ],
                ],
                [
                    'q' => 'Can I route by language?',
                    'a' => [
                        'Yes. Teams can be organised by language and routed accordingly, which matters when not every agent covers every language you serve.',
                    ],
                ],
            ],
            'related' => [
                'whatsapp-shared-inbox',
                'whatsapp-multi-agent',
                'reports-analytics',
            ],
        ],

    'reports-analytics' => [
        'og_image' => 'dashboard-overview.png',
            'title' => 'Support Reports & Analytics | wavadesk',
            'description' => 'Median first reply, resolution time, volume by channel, agent workload and AI deflection — the numbers you can actually manage against.',
            'h1' => 'The support numbers you can <em>actually manage</em> against',
            'kicker' => '',
            'lede' => 'Most teams running WhatsApp support have no figures at all — they have impressions. Reporting turns response time, volume and workload into something you can set a target on and see move.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'The blind spot',
                ],
                [
                    'id' => 'kpis',
                    'label' => 'Core metrics',
                ],
                [
                    'id' => 'volume',
                    'label' => 'Volume',
                ],
                [
                    'id' => 'response',
                    'label' => 'Response time',
                ],
                [
                    'id' => 'workload',
                    'label' => 'Workload',
                ],
                [
                    'id' => 'ai',
                    'label' => 'AI performance',
                ],
                [
                    'id' => 'use',
                    'label' => 'Weekly review',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Which metrics are included?',
                    'a' => [
                        'Conversation volume by channel and day, median first reply, resolution time, conversations waiting in the pool with the oldest wait, per-agent workload against capacity, busiest hours by day and hour, and AI deflection split into resolved, assisted and escalated.',
                    ],
                ],
                [
                    'q' => 'Is reporting available on every plan?',
                    'a' => [
                        'Core volume, response-time and AI reporting are available on all plans. Per-team breakdowns and the fuller reporting set come with Growth and above.',
                    ],
                ],
                [
                    'q' => 'Why median first reply and not average?',
                    'a' => [
                        'A small number of conversations that arrive overnight and are answered in the morning will drag an average into meaninglessness. The median reflects what a typical customer actually experiences.',
                    ],
                ],
                [
                    'q' => 'Can I export the data?',
                    'a' => [
                        'Yes. Reports can be exported for the period you select, so you can combine them with other business data or keep a longer history outside the workspace.',
                    ],
                ],
                [
                    'q' => 'Does the AI count as an agent in workload reporting?',
                    'a' => [
                        'No. AI activity is reported separately as deflection, so human workload figures are not flattered by automated replies.',
                    ],
                ],
                [
                    'q' => 'How far back does history go?',
                    'a' => [
                        'Your full workspace history is retained and reportable. The dashboard defaults to the last 14 days, with 7 and 30-day views selectable.',
                    ],
                ],
            ],
            'related' => [
                'whatsapp-shared-inbox',
                'teams-routing',
                'ai-agent',
            ],
        ],

    'api-integrations' => [
        'og_image' => 'livechat-settings.png',
            'title' => 'API & Integrations | wavadesk',
            'description' => 'Push conversations, contacts and events between wavadesk and your stack. REST endpoints, webhooks and the OTP API.',
            'h1' => 'Connect wavadesk to <em>the rest of your stack</em>',
            'kicker' => '',
            'lede' => 'Support does not live in isolation. The API and webhooks let your own systems read conversations, create contacts, send verification codes and react to events as they happen.',
            'micro' => 'No card required     Keep your own number     Live in under a minute',
            'toc' => [
                [
                    'id' => 'why',
                    'label' => 'Why integrate',
                ],
                [
                    'id' => 'auth',
                    'label' => 'Authentication',
                ],
                [
                    'id' => 'rest',
                    'label' => 'REST API',
                ],
                [
                    'id' => 'webhooks',
                    'label' => 'Webhooks',
                ],
                [
                    'id' => 'otp',
                    'label' => 'OTP API',
                ],
                [
                    'id' => 'widget',
                    'label' => 'Embedding',
                ],
                [
                    'id' => 'limits',
                    'label' => 'Limits',
                ],
                [
                    'id' => 'faq',
                    'label' => 'FAQ',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Which plan includes API access?',
                    'a' => [
                        'API access, webhooks and the OTP endpoints are included on the Scale plan. Other plans can add the OTP module separately.',
                    ],
                ],
                [
                    'q' => 'How do I authenticate?',
                    'a' => [
                        'With a workspace-scoped bearer token created in settings. Tokens are revocable, scoped to a single workspace, and should be kept server-side — never in front-end code.',
                    ],
                ],
                [
                    'q' => 'Can I push my own customer data into wavadesk?',
                    'a' => [
                        'Yes, and it is usually the first integration worth building. Writing custom fields onto the contact record means agents see your order history, plan or account status in the context panel while replying.',
                    ],
                ],
                [
                    'q' => 'What events can webhooks send?',
                    'a' => [
                        'Conversation created, claimed, resolved and escalated; message received and sent; contact created and updated; and OTP verified. Payloads are signed so you can verify them.',
                    ],
                ],
                [
                    'q' => 'What happens if my webhook endpoint is down?',
                    'a' => [
                        'Deliveries are retried with backoff. Respond 2xx quickly and process asynchronously — slow handling inside the request will time out and cause duplicate deliveries.',
                    ],
                ],
                [
                    'q' => 'Will the API change without warning?',
                    'a' => [
                        'Breaking changes ship behind a version and existing versions stay supported. Additive changes such as new fields or event types can arrive without a version bump, so parse payloads defensively.',
                    ],
                ],
            ],
            'related' => [
                'otp-service',
                'whatsapp-shared-inbox',
                'reports-analytics',
            ],
        ],
];

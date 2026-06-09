@extends('layouts.admin')

@section('title', __('ui.conversations_page.title'))

@section('breadcrumb')
    <span>{{ __('ui.conversations_page.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $panelPrefix = auth()->user()->routeNamePrefix();
    $tenantsJs = ($tenants ?? collect())->map(function ($t) {
        return ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug];
    })->values();
    $instancesJs = ($instances ?? collect())->map(function ($i) {
        return ['id' => $i->id, 'name' => $i->name, 'tenant_id' => $i->tenant_id];
    })->values();
    $teamsJs = ($teams ?? collect())->map(function ($t) {
        return ['id' => $t->id, 'name' => $t->name, 'tenant_id' => $t->tenant_id];
    })->values();
    $agentsJs = ($agents ?? collect())->map(function ($a) {
        return ['id' => $a->id, 'name' => $a->name, 'role' => $a->role, 'tenant_id' => $a->tenant_id];
    })->values();
@endphp
<div x-data="conversationsPage()" x-init="init()" x-cloak>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">{{ __('ui.conversations_page.title') }}</div>
            <div class="page-subtitle">
                @if(auth()->user()->isSuperAdmin())
                    {{ __('ui.conversations_page.subtitle_superadmin') }}
                @else
                    {{ __('ui.conversations_page.subtitle_default') }}
                @endif
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
        <div class="tab-nav">
            <button class="tab-btn" :class="{'active': tab==='pool'}" @click="switchTab('pool')">
                <i class="ri-inbox-line" style="font-size:14px;"></i>
                {{ __('ui.conversations_page.pool') }}
                <span x-show="counts.pool > 0" x-text="counts.pool"
                      style="background:var(--brand);color:#fff;font-size:.6875rem;font-weight:700;min-width:1.125rem;height:1.125rem;padding:0 .25rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;margin-left:.25rem;"></span>
            </button>
            <button class="tab-btn" :class="{'active': tab==='mine'}" @click="switchTab('mine')">
                <i class="ri-user-line" style="font-size:14px;"></i>
                {{ __('ui.conversations_page.my_conversations') }}
                <span x-show="counts.mine > 0" x-text="counts.mine"
                      style="background:var(--brand);color:#fff;font-size:.6875rem;font-weight:700;min-width:1.125rem;height:1.125rem;padding:0 .25rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;margin-left:.25rem;"></span>
            </button>
            <button class="tab-btn" :class="{'active': tab==='closed'}" @click="switchTab('closed')">
                <i class="ri-check-double-line" style="font-size:14px;"></i>
                {{ __('ui.conversations_page.closed') }}
            </button>
            <template x-if="isSuperAdmin">
                <button class="tab-btn" :class="{'active': tab==='claimed'}" @click="switchTab('claimed')">
                    <i class="ri-user-star-line" style="font-size:14px;"></i>
                    {{ __('ui.conversations_page.claimed') }}
                </button>
            </template>
            <template x-if="isSuperAdmin">
                <button class="tab-btn" :class="{'active': tab==='all'}" @click="switchTab('all')">
                    <i class="ri-earth-line" style="font-size:14px;"></i>
                    {{ __('ui.conversations_page.all') }}
                </button>
            </template>
        </div>

        <div x-show="tab === 'pool' && !loading && conversations.length > 0"
             style="font-size:.8125rem;color:var(--text-muted);display:flex;align-items:center;gap:.375rem;">
            <i class="ri-information-line"></i>
            {{ __('ui.conversations_page.hover_claim') }}
        </div>
    </div>

    {{-- Customer filter banner --}}
    <div x-show="customerFilter"
         style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.625rem 1rem;background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:.75rem;margin-bottom:.875rem;">
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;color:var(--text-primary);">
            <i class="ri-user-line" style="color:#3b82f6;"></i>
            <span>{{ __('ui.conversations_page.filtered_by_customer') }}:</span>
            <strong x-text="customerFilter"></strong>
        </div>
        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm" style="color:#3b82f6;">
            <i class="ri-close-line"></i> {{ __('ui.conversations_page.clear_filter') }}
        </button>
    </div>

    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem;">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.350ms="reload()"
                   placeholder="{{ __('ui.conversations_page.search_placeholder') }}" class="filter-input">
        </div>

        <template x-if="isSuperAdmin">
            <select x-model="filters.tenant_id" @change="onTenantFilterChange()" class="toolbar-select">
                <option value="">{{ __('ui.conversations_page.all_tenants') }}</option>
                <template x-for="tenant in tenants" :key="tenant.id">
                    <option :value="String(tenant.id)" x-text="tenant.name"></option>
                </template>
            </select>
        </template>

        <select x-model="filters.instance_id" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.conversations_page.all_instances') }}</option>
            <template x-for="instance in filteredInstances()" :key="instance.id">
                <option :value="String(instance.id)" x-text="instance.name"></option>
            </template>
        </select>

        <select x-model="filters.team_id" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.conversations_page.all_teams') }}</option>
            <template x-for="team in filteredTeams()" :key="team.id">
                <option :value="String(team.id)" x-text="team.name"></option>
            </template>
        </select>

        <select x-model="filters.agent_id" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.conversations_page.all_agents') }}</option>
            <template x-for="agent in filteredAgents()" :key="agent.id">
                <option :value="String(agent.id)" x-text="`${agent.name} (${agent.role})`"></option>
            </template>
        </select>

        <select x-model="filters.state" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.conversations_page.all_states') }}</option>
            <option value="pool">{{ __('ui.conversations_page.pool') }}</option>
            <option value="claimed">{{ __('ui.conversations_page.claimed') }}</option>
            <option value="closed">{{ __('ui.conversations_page.closed') }}</option>
        </select>

        <select x-model="filters.has_unread" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.conversations_page.unread_read') }}</option>
            <option value="1">{{ __('ui.conversations_page.has_unread') }}</option>
            <option value="0">{{ __('ui.conversations_page.read_only') }}</option>
        </select>

        <select x-model="filters.ai_suspended" @change="reload()" class="toolbar-select">
            <option value="">{{ __('ui.conversations_page.ai_any_state') }}</option>
            <option value="1">{{ __('ui.conversations_page.ai_suspended') }}</option>
            <option value="0">{{ __('ui.conversations_page.ai_active') }}</option>
        </select>

        <input type="text" x-model="filters.date_from" @change="reload()" class="toolbar-select" placeholder="{{ __('ui.date_placeholder_from') }}" aria-label="{{ __('ui.date_from') }}" title="{{ __('ui.date_from') }}">
        <input type="text" x-model="filters.date_to" @change="reload()" class="toolbar-select" placeholder="{{ __('ui.date_placeholder_to') }}" aria-label="{{ __('ui.date_to') }}" title="{{ __('ui.date_to') }}">

        <select x-model="filters.sort" @change="reload()" class="toolbar-select">
            <option value="last_message_desc">{{ __('ui.conversations_page.latest_activity') }}</option>
            <option value="last_message_asc">{{ __('ui.conversations_page.oldest_activity') }}</option>
            <option value="created_desc">{{ __('ui.conversations_page.newest_created') }}</option>
            <option value="created_asc">{{ __('ui.conversations_page.oldest_created') }}</option>
        </select>

        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm">{{ __('ui.conversations_page.clear') }}</button>
    </div>

    <div class="table-wrap">
        <div x-show="loading" class="spinner-wrap">
            <div>
                <div class="spinner" style="margin:0 auto 1rem;"></div>
                <div style="color:var(--text-muted);font-size:.875rem;text-align:center;">{{ __('ui.conversations_page.loading') }}</div>
            </div>
        </div>

        <div x-show="!loading && conversations.length === 0" class="empty-state">
            <div class="empty-state-icon"><i class="ri-message-3-line"></i></div>
            <h4 x-text="emptyTitle()"></h4>
            <p x-text="emptyDesc()"></p>
            <div x-show="tab === 'pool'">
                @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                    <a href="{{ route($panelPrefix . '.instances.index') }}" class="btn btn-outline btn-sm">
                        <i class="ri-smartphone-line"></i> {{ __('ui.conversations_page.check_instances') }}
                    </a>
                @endif
            </div>
        </div>

        <div x-show="!loading && conversations.length > 0">
            <template x-for="conv in conversations" :key="conv.id">
                <div class="conv-row" @mouseenter="hovered = conv.id" @mouseleave="hovered = null">
                    <div style="width:8px;padding-left:16px;flex-shrink:0;">
                        <div :style="conv.unread_count > 0 ? 'width:7px;height:7px;border-radius:50%;background:var(--brand);box-shadow:0 0 0 2px rgba(16,185,129,.2)' : 'width:7px;height:7px;'"></div>
                    </div>

                    <a :href="conversationUrl(conv.id)"
                       style="display:flex;align-items:center;gap:.875rem;padding:.875rem 1rem .875rem .75rem;text-decoration:none;color:inherit;flex:1;min-width:0;">
                        <div class="conv-avatar" x-text="initials(conv.customer?.display_name || conv.customer?.phone_e164)"></div>

                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.25rem;">
                                <span class="conv-name" :style="conv.unread_count > 0 ? 'font-weight:700' : ''"
                                      x-text="conv.customer?.display_name || conv.customer?.phone_e164 || '-'"></span>
                                <span class="conv-time" x-text="timeAgo(conv.last_message_at)"></span>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                                <div style="min-width:0;flex:1;">
                                    <span class="conv-preview" x-text="conv.last_message_preview || '-'"></span>
                                    <div x-show="conv.state === 'claimed' && conv.owner_agent?.name"
                                         style="font-size:.6875rem;color:#3b82f6;margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <i class="ri-user-line" style="font-size:.625rem;"></i>
                                        <span x-text="conv.owner_agent.name"></span>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:.375rem;flex-shrink:0;">
                                    <span x-show="conv.unread_count > 0" class="unread-badge" x-text="conv.unread_count"></span>
                                    <span x-show="conv.state === 'claimed'" class="badge badge-blue" style="font-size:.6875rem;">
                                        <i class="ri-user-line" style="font-size:.625rem;"></i>
                                        <span x-text="conv.owner_agent?.name || @js(__('ui.conversations_page.claimed'))"></span>
                                    </span>
                                    <span x-show="conv.ai_suspended" class="badge badge-gray" style="font-size:.6875rem;">{{ __('ui.conversations_page.ai_off') }}</span>
                                    <span x-show="conv.instance" class="badge badge-teal" style="font-size:.6875rem;" x-text="conv.instance?.name"></span>
                                    <span x-show="isSuperAdmin && conv.tenant" class="badge badge-purple" style="font-size:.6875rem;" x-text="conv.tenant?.name"></span>
                                </div>
                            </div>
                        </div>
                    </a>

                    <div x-show="canClaimPool && tab === 'pool' && hovered === conv.id" style="padding-right:1rem;flex-shrink:0;">
                        <button @click.prevent="claimConversation(conv)" :disabled="claiming === conv.id"
                                class="btn btn-primary btn-sm" style="white-space:nowrap;min-width:72px;">
                            <template x-if="claiming !== conv.id"><span><i class="ri-hand-coin-line"></i> {{ __('ui.conversations_page.take') }}</span></template>
                            <template x-if="claiming === conv.id"><span class="btn-spinner"></span></template>
                        </button>
                    </div>

                    <div x-show="tab !== 'pool' || hovered !== conv.id" style="padding-right:1.25rem;flex-shrink:0;color:var(--text-muted);">
                        <i class="ri-arrow-right-s-line" style="font-size:1.125rem;"></i>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="lastPage > 1" style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--card-border);flex-wrap:wrap;gap:8px;">
            <div style="font-size:12px;color:var(--text-muted);" x-text="total + ' {{ __('ui.total_records') }}'"></div>
            <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                <button @click="goToPage(page - 1)" :disabled="page <= 1 || loading" class="btn btn-outline btn-sm" style="padding:4px 10px;">‹</button>
                <template x-for="n in pageRange()" :key="n">
                    <button x-text="n === '...' ? '…' : n"
                            @click="n !== '...' && goToPage(n)"
                            :disabled="loading"
                            :class="n === page ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'"
                            style="padding:4px 10px;min-width:34px;"></button>
                </template>
                <button @click="goToPage(page + 1)" :disabled="page >= lastPage || loading" class="btn btn-outline btn-sm" style="padding:4px 10px;">›</button>
            </div>
        </div>
    </div>
</div>

<style>
.conv-row {
    display: flex;
    align-items: center;
    border-bottom: 1px solid var(--card-border);
    transition: background .12s;
    cursor: pointer;
}
.conv-row:last-child { border-bottom: none; }
.conv-row:hover { background: var(--page-bg); }
.conv-avatar {
    width: 2.75rem; height: 2.75rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    font-size: .8125rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
}
.conv-name {
    font-weight: 600;
    font-size: .9375rem;
    color: var(--text-primary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.conv-time {
    font-size: .75rem;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-left: .5rem;
}
.conv-preview {
    font-size: .8125rem;
    color: var(--text-secondary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.unread-badge {
    background: var(--brand); color: #fff;
    font-size: .6875rem; font-weight: 700;
    min-width: 1.25rem; height: 1.25rem;
    padding: 0 .3rem; border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
}
</style>

<script>
@php
    $conversationI18n = [
        'conversation_claimed' => __('ui.conversations_page.conversation_claimed'),
        'opening_now' => __('ui.conversations_page.opening_now'),
        'already_taken' => __('ui.conversations_page.already_taken'),
        'another_just_claimed' => __('ui.conversations_page.another_just_claimed'),
        'could_not_claim' => __('ui.conversations_page.could_not_claim'),
        'please_try_again' => __('ui.conversations_page.please_try_again'),
        'network_error' => __('ui.conversations_page.network_error'),
        'could_not_reach_server' => __('ui.conversations_page.could_not_reach_server'),
        'just_now' => __('ui.conversations_page.just_now'),
        'minutes_ago' => __('ui.conversations_page.minutes_ago'),
        'hours_ago' => __('ui.conversations_page.hours_ago'),
        'days_ago' => __('ui.conversations_page.days_ago'),
        'no_conversations_waiting' => __('ui.conversations_page.no_conversations_waiting'),
        'no_active_conversations' => __('ui.conversations_page.no_active_conversations'),
        'no_claimed_conversations' => __('ui.conversations_page.no_claimed_conversations'),
        'no_conversations_found' => __('ui.conversations_page.no_conversations_found'),
        'no_closed_conversations' => __('ui.conversations_page.no_closed_conversations'),
        'desc_pool' => __('ui.conversations_page.desc_pool'),
        'desc_mine' => __('ui.conversations_page.desc_mine'),
        'desc_claimed' => __('ui.conversations_page.desc_claimed'),
        'desc_all' => __('ui.conversations_page.desc_all'),
        'desc_closed' => __('ui.conversations_page.desc_closed'),
    ];
@endphp
function conversationsPage() {
    return {
        i18n: @json($conversationI18n),
        tab: '{{ request("tab", "pool") }}',
        customerFilter: null,
        isSuperAdmin: @json(auth()->user()->isSuperAdmin()),
        showUrlTpl: @json(route($panelPrefix . '.conversations.show', ['conversation' => '__ID__'])),
        indexUrl: @json(route($panelPrefix . '.conversations.index')),
        search: '',
        tenants: @json($tenantsJs),
        instances: @json($instancesJs),
        teams: @json($teamsJs),
        agents: @json($agentsJs),
        filters: {
            tenant_id: '',
            instance_id: '',
            team_id: '',
            agent_id: '',
            customer_id: '',
            state: '',
            has_unread: '',
            ai_suspended: '',
            date_from: '',
            date_to: '',
            sort: 'last_message_desc'
        },
        conversations: [],
        counts: { pool: 0, mine: 0 },
        loading: true,
        page: 1,
        lastPage: 1,
        total: 0,
        hovered: null,
        claiming: null,
        canClaimPool: @json(auth()->user()->isAgent() || auth()->user()->isSupervisor() || auth()->user()->isSuperAdmin()),

        init() {
            const urlParams = new URLSearchParams(window.location.search);
            const customerId = urlParams.get('customer_id');
            if (customerId) {
                this.filters.customer_id = customerId;
                this.tab = urlParams.get('tab') || 'all';
                // Resolve customer name for the banner
                fetch(`{{ route($panelPrefix . '.customers.index') }}?id=${customerId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    const c = data?.data?.[0];
                    this.customerFilter = c ? (c.display_name || c.phone_e164 || '#' + customerId) : '#' + customerId;
                })
                .catch(() => { this.customerFilter = '#' + customerId; });
            }
            this.loadData();
            this.fetchCounts();
            this.subscribeRealtime();
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) this.loadData();
            });
        },

        switchTab(t) {
            this.tab = t;
            this.reload();
        },

        onTenantFilterChange() {
            this.filters.instance_id = '';
            this.filters.team_id = '';
            this.filters.agent_id = '';
            this.reload();
        },

        reload() {
            this.page = 1;
            this.loadData();
            this.fetchCounts();
        },

        buildParams(tabOverride = null) {
            const params = new URLSearchParams();
            params.set('tab', tabOverride || this.tab);
            params.set('per_page', '20');
            params.set('page', String(this.page));
            if (this.search.trim()) params.set('search', this.search.trim());

            Object.entries(this.filters).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    params.set(key, value);
                }
            });

            return params;
        },

        async loadData() {
            this.loading = true;

            try {
                const params = this.buildParams(this.tab);

                const res = await fetch(`${this.indexUrl}?${params.toString()}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();

                this.conversations = data.data || [];
                this.page     = data.current_page ?? 1;
                this.lastPage = data.last_page ?? 1;
                this.total    = data.total ?? 0;
            } catch (e) {
                console.error('Conversations fetch failed:', e);
                window.showToast?.('error', 'Failed to load conversations');
                this.conversations = [];
            } finally {
                this.loading = false;
            }
        },

        async fetchCounts() {
            try {
                const [poolRes, mineRes] = await Promise.all(
                    ['pool', 'mine'].map((tab) =>
                        fetch(`${this.indexUrl}?${this.buildParams(tab).toString()}`, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' }
                        })
                    )
                );

                const pool = await poolRes.json();
                const mine = await mineRes.json();
                this.counts.pool = pool.total ?? 0;
                this.counts.mine = mine.total ?? 0;
            } catch {}
        },

        goToPage(n) {
            if (n < 1 || n > this.lastPage) return;
            this.page = n;
            this.loadData();
        },

        pageRange() {
            const pages = [];
            const delta = 2;
            const left = this.page - delta;
            const right = this.page + delta;
            let last = 0;
            for (let i = 1; i <= this.lastPage; i++) {
                if (i === 1 || i === this.lastPage || (i >= left && i <= right)) {
                    if (last && i - last > 1) pages.push('...');
                    pages.push(i);
                    last = i;
                }
            }
            return pages;
        },

        async claimConversation(conv) {
            this.claiming = conv.id;
            try {
                const res = await fetch(`/api/conversations/${conv.id}/claim`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (res.ok) {
                    window.showToast?.('success', this.i18n.conversation_claimed, this.i18n.opening_now);
                    window.location.href = this.conversationUrl(conv.id);
                } else if (res.status === 409) {
                    window.showToast?.('warning', this.i18n.already_taken, this.i18n.another_just_claimed);
                    this.conversations = this.conversations.filter(c => c.id !== conv.id);
                    this.fetchCounts();
                } else {
                    window.showToast?.('error', this.i18n.could_not_claim, this.i18n.please_try_again);
                }
            } catch {
                window.showToast?.('error', this.i18n.network_error, this.i18n.could_not_reach_server);
            } finally {
                this.claiming = null;
            }
        },

        subscribeRealtime() {
            if (!window.Echo) return;
            const tenantId = {{ auth()->user()->tenant_id ?? 0 }};
            const role = '{{ auth()->user()->role }}';
            const teamIds = @json(auth()->user()->teams->pluck('id')->values());

            if (!tenantId) return;

            const refresh = () => { this.reload(); };

            if (role === 'admin' || role === 'super_admin') {
                window.Echo.private(`tenant.${tenantId}.pool`)
                    .listen('.conversation.claimed', refresh)
                    .listen('.conversation.released', refresh)
                    .listen('.conversation.reopened', refresh);
            }

            teamIds.forEach((teamId) => {
                window.Echo.private(`tenant.${tenantId}.team.${teamId}.pool`)
                    .listen('.conversation.claimed', refresh)
                    .listen('.conversation.released', refresh)
                    .listen('.conversation.reopened', refresh);
            });
        },

        conversationUrl(id) {
            return this.showUrlTpl.replace('__ID__', String(id));
        },

        filteredInstances() {
            if (!this.isSuperAdmin || !this.filters.tenant_id) return this.instances;
            return this.instances.filter(i => String(i.tenant_id) === String(this.filters.tenant_id));
        },

        filteredTeams() {
            if (!this.isSuperAdmin || !this.filters.tenant_id) return this.teams;
            return this.teams.filter(t => String(t.tenant_id) === String(this.filters.tenant_id));
        },

        filteredAgents() {
            if (!this.isSuperAdmin || !this.filters.tenant_id) return this.agents;
            return this.agents.filter(a => String(a.tenant_id) === String(this.filters.tenant_id));
        },

        clearFilters() {
            this.search = '';
            this.customerFilter = null;
            this.filters = {
                tenant_id: '',
                instance_id: '',
                team_id: '',
                agent_id: '',
                customer_id: '',
                state: '',
                has_unread: '',
                ai_suspended: '',
                date_from: '',
                date_to: '',
                sort: 'last_message_desc'
            };
            // Remove customer_id from URL so a page refresh doesn't re-apply it
            const url = new URL(window.location.href);
            url.searchParams.delete('customer_id');
            history.replaceState(null, '', url.toString());
            this.reload();
        },

        initials(str) {
            if (!str) return '?';
            const parts = str.trim().split(/\s+/);
            if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
            return str.slice(0, 2).toUpperCase();
        },

        timeAgo(ts) {
            if (!ts) return '';
            const diff = (Date.now() - new Date(ts)) / 1000;
            if (diff < 60) return this.i18n.just_now;
            if (diff < 3600) return `${Math.floor(diff / 60)}${this.i18n.minutes_ago}`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}${this.i18n.hours_ago}`;
            return `${Math.floor(diff / 86400)}${this.i18n.days_ago}`;
        },

        emptyTitle() {
            if (this.tab === 'pool') return this.i18n.no_conversations_waiting;
            if (this.tab === 'mine') return this.i18n.no_active_conversations;
            if (this.tab === 'claimed') return this.i18n.no_claimed_conversations;
            if (this.tab === 'all') return this.i18n.no_conversations_found;
            return this.i18n.no_closed_conversations;
        },

        emptyDesc() {
            if (this.tab === 'pool') return this.i18n.desc_pool;
            if (this.tab === 'mine') return this.i18n.desc_mine;
            if (this.tab === 'claimed') return this.i18n.desc_claimed;
            if (this.tab === 'all') return this.i18n.desc_all;
            return this.i18n.desc_closed;
        }
    };
}
</script>
@endsection

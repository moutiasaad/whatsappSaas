@extends('layouts.admin')

@section('title', 'Conversations')

@section('breadcrumb')
    <span>Conversations</span>
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
            <div class="page-title">Conversations</div>
            <div class="page-subtitle">
                @if(auth()->user()->isSuperAdmin())
                    Full cross-tenant conversation control center
                @else
                    Manage and respond to customer conversations
                @endif
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
        <div class="tab-nav">
            <button class="tab-btn" :class="{'active': tab==='pool'}" @click="switchTab('pool')">
                <i class="ri-inbox-line" style="font-size:14px;"></i>
                Pool
                <span x-show="counts.pool > 0" x-text="counts.pool"
                      style="background:var(--brand);color:#fff;font-size:.6875rem;font-weight:700;min-width:1.125rem;height:1.125rem;padding:0 .25rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;margin-left:.25rem;"></span>
            </button>
            <button class="tab-btn" :class="{'active': tab==='mine'}" @click="switchTab('mine')">
                <i class="ri-user-line" style="font-size:14px;"></i>
                My Conversations
                <span x-show="counts.mine > 0" x-text="counts.mine"
                      style="background:var(--brand);color:#fff;font-size:.6875rem;font-weight:700;min-width:1.125rem;height:1.125rem;padding:0 .25rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;margin-left:.25rem;"></span>
            </button>
            <button class="tab-btn" :class="{'active': tab==='closed'}" @click="switchTab('closed')">
                <i class="ri-check-double-line" style="font-size:14px;"></i>
                Closed
            </button>
            <template x-if="isSuperAdmin">
                <button class="tab-btn" :class="{'active': tab==='claimed'}" @click="switchTab('claimed')">
                    <i class="ri-user-star-line" style="font-size:14px;"></i>
                    Claimed
                </button>
            </template>
            <template x-if="isSuperAdmin">
                <button class="tab-btn" :class="{'active': tab==='all'}" @click="switchTab('all')">
                    <i class="ri-earth-line" style="font-size:14px;"></i>
                    All
                </button>
            </template>
        </div>

        <div x-show="tab === 'pool' && !loading && conversations.length > 0"
             style="font-size:.8125rem;color:var(--text-muted);display:flex;align-items:center;gap:.375rem;">
            <i class="ri-information-line"></i>
            Hover a row to claim
        </div>
    </div>

    <div class="table-toolbar" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-lg);margin-bottom:1rem;">
        <div class="filter-input-wrap">
            <i class="ri-search-line"></i>
            <input type="text" x-model="search" @input.debounce.350ms="reload()"
                   placeholder="Search contact, message, agent, team, instance..." class="filter-input">
        </div>

        <template x-if="isSuperAdmin">
            <select x-model="filters.tenant_id" @change="onTenantFilterChange()" class="toolbar-select">
                <option value="">All Tenants</option>
                <template x-for="tenant in tenants" :key="tenant.id">
                    <option :value="String(tenant.id)" x-text="tenant.name"></option>
                </template>
            </select>
        </template>

        <select x-model="filters.instance_id" @change="reload()" class="toolbar-select">
            <option value="">All Instances</option>
            <template x-for="instance in filteredInstances()" :key="instance.id">
                <option :value="String(instance.id)" x-text="instance.name"></option>
            </template>
        </select>

        <select x-model="filters.team_id" @change="reload()" class="toolbar-select">
            <option value="">All Teams</option>
            <template x-for="team in filteredTeams()" :key="team.id">
                <option :value="String(team.id)" x-text="team.name"></option>
            </template>
        </select>

        <select x-model="filters.agent_id" @change="reload()" class="toolbar-select">
            <option value="">All Agents</option>
            <template x-for="agent in filteredAgents()" :key="agent.id">
                <option :value="String(agent.id)" x-text="`${agent.name} (${agent.role})`"></option>
            </template>
        </select>

        <select x-model="filters.state" @change="reload()" class="toolbar-select">
            <option value="">All States</option>
            <option value="pool">Pool</option>
            <option value="claimed">Claimed</option>
            <option value="closed">Closed</option>
        </select>

        <select x-model="filters.has_unread" @change="reload()" class="toolbar-select">
            <option value="">Unread + Read</option>
            <option value="1">Has unread</option>
            <option value="0">Read only</option>
        </select>

        <select x-model="filters.ai_suspended" @change="reload()" class="toolbar-select">
            <option value="">AI any state</option>
            <option value="1">AI suspended</option>
            <option value="0">AI active</option>
        </select>

        <input type="date" x-model="filters.date_from" @change="reload()" class="toolbar-select">
        <input type="date" x-model="filters.date_to" @change="reload()" class="toolbar-select">

        <select x-model="filters.sort" @change="reload()" class="toolbar-select">
            <option value="last_message_desc">Latest activity</option>
            <option value="last_message_asc">Oldest activity</option>
            <option value="created_desc">Newest created</option>
            <option value="created_asc">Oldest created</option>
        </select>

        <button type="button" @click="clearFilters()" class="btn btn-ghost btn-sm">Clear</button>
    </div>

    <div class="table-wrap">
        <div x-show="loading" class="spinner-wrap">
            <div>
                <div class="spinner" style="margin:0 auto 1rem;"></div>
                <div style="color:var(--text-muted);font-size:.875rem;text-align:center;">Loading conversations...</div>
            </div>
        </div>

        <div x-show="!loading && conversations.length === 0" class="empty-state">
            <div class="empty-state-icon"><i class="ri-message-3-line"></i></div>
            <h4 x-text="emptyTitle()"></h4>
            <p x-text="emptyDesc()"></p>
            <div x-show="tab === 'pool'">
                @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                    <a href="{{ route($panelPrefix . '.instances.index') }}" class="btn btn-outline btn-sm">
                        <i class="ri-smartphone-line"></i> Check Instances
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
                                <span class="conv-preview" x-text="conv.last_message_preview || '-'"></span>
                                <div style="display:flex;align-items:center;gap:.375rem;flex-shrink:0;">
                                    <span x-show="conv.unread_count > 0" class="unread-badge" x-text="conv.unread_count"></span>
                                    <span x-show="conv.state === 'claimed'" class="badge badge-blue" style="font-size:.6875rem;">
                                        <i class="ri-user-line" style="font-size:.625rem;"></i>
                                        <span x-text="conv.owner_agent?.name || 'Claimed'"></span>
                                    </span>
                                    <span x-show="conv.ai_suspended" class="badge badge-gray" style="font-size:.6875rem;">AI Off</span>
                                    <span x-show="conv.instance" class="badge badge-teal" style="font-size:.6875rem;" x-text="conv.instance?.name"></span>
                                    <span x-show="isSuperAdmin && conv.tenant" class="badge badge-purple" style="font-size:.6875rem;" x-text="conv.tenant?.name"></span>
                                </div>
                            </div>
                        </div>
                    </a>

                    <div x-show="canClaimPool && tab === 'pool' && hovered === conv.id" style="padding-right:1rem;flex-shrink:0;">
                        <button @click.prevent="claimConversation(conv)" :disabled="claiming === conv.id"
                                class="btn btn-primary btn-sm" style="white-space:nowrap;min-width:72px;">
                            <template x-if="claiming !== conv.id"><span><i class="ri-hand-coin-line"></i> Take</span></template>
                            <template x-if="claiming === conv.id"><span class="btn-spinner"></span></template>
                        </button>
                    </div>

                    <div x-show="tab !== 'pool' || hovered !== conv.id" style="padding-right:1.25rem;flex-shrink:0;color:var(--text-muted);">
                        <i class="ri-arrow-right-s-line" style="font-size:1.125rem;"></i>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="!loading && hasMore" style="padding:1rem;text-align:center;border-top:1px solid var(--card-border);">
            <button @click="loadMore()" :disabled="loadingMore" class="btn btn-outline btn-sm">
                <template x-if="!loadingMore"><span><i class="ri-arrow-down-line"></i> Load more</span></template>
                <template x-if="loadingMore"><span><span class="btn-spinner"></span> Loading...</span></template>
            </button>
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
function conversationsPage() {
    return {
        tab: '{{ request("tab", "pool") }}',
        isSuperAdmin: @json(auth()->user()->isSuperAdmin()),
        showUrlTpl: @json(route($panelPrefix . '.conversations.show', ['conversation' => '__ID__'])),
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
        loadingMore: false,
        hasMore: false,
        page: 1,
        hovered: null,
        claiming: null,
        canClaimPool: @json(auth()->user()->isAgent() || auth()->user()->isSupervisor() || auth()->user()->isSuperAdmin()),

        init() {
            this.fetchConversations();
            this.fetchCounts();
            this.subscribeRealtime();
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
            this.conversations = [];
            this.fetchConversations();
            this.fetchCounts();
        },

        buildParams(tabOverride = null) {
            const params = new URLSearchParams();
            params.set('tab', tabOverride || this.tab);
            params.set('per_page', '20');
            if (this.search.trim()) params.set('search', this.search.trim());

            Object.entries(this.filters).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    params.set(key, value);
                }
            });

            return params;
        },

        async fetchConversations(append = false) {
            if (!append) {
                this.loading = true;
                this.page = 1;
            } else {
                this.loadingMore = true;
                this.page++;
            }

            try {
                const params = this.buildParams(this.tab);
                params.set('page', String(this.page));

                const res = await fetch(`/api/conversations?${params.toString()}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();

                this.conversations = append
                    ? [...this.conversations, ...(data.data || [])]
                    : (data.data || []);

                this.hasMore = (data.current_page ?? 1) < (data.last_page ?? 1);
            } catch (e) {
                console.error('Conversations fetch failed:', e);
                window.showToast?.('error', 'Failed to load conversations');
                if (!append) this.conversations = [];
            } finally {
                this.loading = false;
                this.loadingMore = false;
            }
        },

        async fetchCounts() {
            try {
                const [poolRes, mineRes] = await Promise.all(
                    ['pool', 'mine'].map((tab) =>
                        fetch(`/api/conversations?${this.buildParams(tab).toString()}`, {
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

        loadMore() {
            this.fetchConversations(true);
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
                    window.showToast?.('success', 'Conversation claimed', 'Opening now...');
                    window.location.href = this.conversationUrl(conv.id);
                } else if (res.status === 409) {
                    window.showToast?.('warning', 'Already taken', 'Another agent just claimed this');
                    this.conversations = this.conversations.filter(c => c.id !== conv.id);
                    this.fetchCounts();
                } else {
                    window.showToast?.('error', 'Could not claim', 'Please try again');
                }
            } catch {
                window.showToast?.('error', 'Network error', 'Could not reach server');
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
            this.filters = {
                tenant_id: '',
                instance_id: '',
                team_id: '',
                agent_id: '',
                state: '',
                has_unread: '',
                ai_suspended: '',
                date_from: '',
                date_to: '',
                sort: 'last_message_desc'
            };
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
            if (diff < 60) return 'just now';
            if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
            return `${Math.floor(diff / 86400)}d ago`;
        },

        emptyTitle() {
            if (this.tab === 'pool') return 'No conversations waiting';
            if (this.tab === 'mine') return 'No active conversations';
            if (this.tab === 'claimed') return 'No claimed conversations';
            if (this.tab === 'all') return 'No conversations found';
            return 'No closed conversations';
        },

        emptyDesc() {
            if (this.tab === 'pool') return 'New inbound messages will appear here for your team to claim';
            if (this.tab === 'mine') return 'Claim conversations from the Pool to start handling them';
            if (this.tab === 'claimed') return 'Claimed conversations will appear here';
            if (this.tab === 'all') return 'Try widening your filters';
            return 'Conversations you close will appear here';
        }
    };
}
</script>
@endsection

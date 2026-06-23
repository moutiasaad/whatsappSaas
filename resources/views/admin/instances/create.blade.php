@extends('layouts.admin')

@section('title', __('ui.instance_create_page.title'))

@section('breadcrumb')
    <a href="{{ route('admin.instances.index') }}" style="color:var(--text-secondary);text-decoration:none">{{ __('ui.instances_page.instances') }}</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>{{ __('ui.instance_create_page.title') }}</span>
@endsection

@section('content')
<div x-data="instanceQuickCreate()" @keydown.escape.window="escHandler($event)">

<form action="{{ route('admin.instances.store') }}" method="POST" data-loading>
    @csrf

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-bottom:1.5rem">
        <div class="card" style="overflow:visible">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ __('ui.instance_create_page.card_title') }}</div>
                    <div class="card-subtitle">{{ __('ui.instance_create_page.card_subtitle') }}</div>
                </div>
            </div>
            <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:1.25rem">
                <div class="form-group">
                    <label class="form-label" for="name">{{ __('ui.instance_create_page.instance_name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           placeholder="{{ __('ui.instance_create_page.instance_name_placeholder') }}"
                           class="form-control @error('name') error @enderror">
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">{{ __('ui.instance_create_page.instance_name_hint') }}</div>
                </div>

                <div class="form-group" style="overflow:visible">
                    <label class="form-label" for="team_id">{{ __('ui.instance_create_page.assigned_team') }}</label>
                    <div style="display:flex;gap:.5rem;align-items:flex-start">
                        <select id="team_id" name="team_id" x-ref="teamSelect"
                                class="form-control @error('team_id') error @enderror" style="flex:1">
                            <option value="">{{ __('ui.instance_create_page.no_team') }}</option>
                            @foreach($teams as $team)
                            <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                            @endforeach
                        </select>
                        <button type="button" @click="openTeamModal()"
                                class="btn btn-outline btn-sm"
                                style="flex-shrink:0;height:38px;padding:0 .65rem;font-size:1.1rem;line-height:1"
                                title="Créer une équipe">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                    @error('team_id') <div class="form-error">{{ $message }}</div> @enderror
                    <div class="form-hint">{{ __('ui.instance_create_page.assigned_team_hint') }}</div>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.5rem">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('ui.instance_create_page.setup_checklist') }}</div>
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;flex-direction:column;gap:.75rem">
                    @foreach([
                        ['icon' => 'ri-server-line',    'title' => __('ui.instance_create_page.steps.deploy_gateway.title'),    'desc' => __('ui.instance_create_page.steps.deploy_gateway.desc')],
                        ['icon' => 'ri-key-line',       'title' => __('ui.instance_create_page.steps.get_api_key.title'),       'desc' => __('ui.instance_create_page.steps.get_api_key.desc')],
                        ['icon' => 'ri-link',           'title' => __('ui.instance_create_page.steps.configure_webhook.title'), 'desc' => __('ui.instance_create_page.steps.configure_webhook.desc')],
                        ['icon' => 'ri-qr-code-line',   'title' => __('ui.instance_create_page.steps.scan_qr.title'),          'desc' => __('ui.instance_create_page.steps.scan_qr.desc')],
                    ] as $step)
                    <div style="display:flex;align-items:flex-start;gap:.75rem">
                        <div style="width:2rem;height:2rem;border-radius:.5rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;color:var(--brand);flex-shrink:0">
                            <i class="{{ $step['icon'] }}"></i>
                        </div>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--text-primary)">{{ $step['title'] }}</div>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.125rem">{{ $step['desc'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.5rem">
        <a href="{{ route('admin.instances.index') }}" class="btn btn-outline">{{ __('ui.cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('ui.instance_create_page.create_instance') }}</button>
    </div>
</form>

{{-- ══════════════════════════════════════════
     Modal 1: Create Team
══════════════════════════════════════════ --}}
<div x-show="teamModal && !userModal" x-cloak
     class="modal-overlay show" style="z-index:1000"
     @click.self="teamModal=false">
    <div class="modal-box" style="max-width:500px;text-align:left;padding:1.5rem;overflow:visible" @click.stop>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin:0">
                <i class="ri-team-line" style="color:var(--brand);margin-right:.375rem"></i>
                Créer une équipe
            </h3>
            <button type="button" @click="teamModal=false" class="btn btn-ghost btn-sm" style="padding:.25rem .5rem">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem">

            {{-- Name --}}
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Nom de l'équipe <span style="color:var(--brand)">*</span></label>
                <input type="text" x-model="teamForm.name"
                       :class="{'error': teamErrors.name}"
                       class="form-control"
                       placeholder="ex: Support Client"
                       @keydown.enter.prevent>
                <div x-show="teamErrors.name" x-text="teamErrors.name?.[0]" class="form-error"></div>
            </div>

            {{-- Description --}}
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Description</label>
                <input type="text" x-model="teamForm.description"
                       :class="{'error': teamErrors.description}"
                       class="form-control"
                       placeholder="Optionnel">
                <div x-show="teamErrors.description" x-text="teamErrors.description?.[0]" class="form-error"></div>
            </div>

            {{-- Members — SS-style dropdown opening upward --}}
            <div class="form-group" style="margin-bottom:0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                    <label class="form-label" style="margin-bottom:0">Membres de l'équipe</label>
                    <button type="button" @click="openUserModal()"
                            class="btn btn-outline btn-sm"
                            style="height:26px;padding:0 .625rem;font-size:.75rem">
                        <i class="ri-add-line"></i> Créer un utilisateur
                    </button>
                </div>

                {{-- SS wrap with forced open-up since we're in a modal --}}
                <div class="ss-wrap" :class="{'open': membersOpen, 'open-up': true}"
                     @click.outside="membersOpen=false">
                    <input type="text" class="ss-input" readonly
                           :value="membersDisplayText"
                           :placeholder="agents.length ? 'Sélectionner des membres' : 'Aucun agent — créez-en un'"
                           @click.stop="membersOpen = !membersOpen; membersSearch = ''">
                    <i class="ri-arrow-down-s-line ss-chevron"></i>
                    <div class="ss-dropdown" @click.stop>
                        <div class="ss-search-row">
                            <div class="ss-search-inner">
                                <i class="ri-search-line"></i>
                                <input type="text" x-model="membersSearch" placeholder="Filtrer…" autocomplete="off">
                            </div>
                        </div>
                        <div class="ss-list">
                            <template x-for="agent in filteredAgents" :key="agent.id">
                                <div class="ss-item"
                                     :class="{'ss-selected': teamForm.members.includes(agent.id)}"
                                     @mousedown.prevent="toggleMember(agent.id)"
                                     :data-value="agent.id">
                                    <span x-text="agent.name + ' (' + agent.role + ')'"></span>
                                </div>
                            </template>
                            <div class="ss-empty"
                                 x-show="filteredAgents.length === 0"
                                 x-text="membersSearch ? 'Aucun résultat' : 'Aucun agent disponible'">
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="teamForm.members.length"
                     x-text="teamForm.members.length + ' membre(s) sélectionné(s)'"
                     style="font-size:.75rem;color:var(--text-muted);margin-top:.375rem"></div>
            </div>

            {{-- Global error --}}
            <div x-show="teamError" x-text="teamError"
                 style="font-size:.8125rem;color:#ef4444;background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.625rem .875rem"></div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.5rem">
            <button type="button" @click="teamModal=false" class="btn btn-outline">Annuler</button>
            <button type="button" @click="createTeam()"
                    :disabled="teamSaving || !teamForm.name.trim()"
                    class="btn btn-primary">
                <span x-show="!teamSaving"><i class="ri-check-line"></i> Créer l'équipe</span>
                <span x-show="teamSaving"><span class="btn-spinner"></span> Création…</span>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════
     Modal 2: Create User
══════════════════════════════════════════ --}}
<div x-show="userModal" x-cloak
     class="modal-overlay show" style="z-index:1100"
     @click.self="userModal=false">
    <div class="modal-box" style="max-width:440px;text-align:left;padding:1.5rem" @click.stop>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin:0">
                <i class="ri-user-add-line" style="color:var(--brand);margin-right:.375rem"></i>
                Créer un utilisateur
            </h3>
            <button type="button" @click="userModal=false" class="btn btn-ghost btn-sm" style="padding:.25rem .5rem">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem">

            {{-- Name --}}
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Nom complet <span style="color:var(--brand)">*</span></label>
                <input type="text" x-model="userForm.name"
                       :class="{'error': userErrors.name}"
                       class="form-control"
                       placeholder="Prénom Nom"
                       @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.name" x-text="userErrors.name?.[0]" class="form-error"></div>
            </div>

            {{-- Email --}}
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Email <span style="color:var(--brand)">*</span></label>
                <input type="email" x-model="userForm.email"
                       :class="{'error': userErrors.email}"
                       class="form-control"
                       placeholder="email@example.com"
                       @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.email" x-text="userErrors.email?.[0]" class="form-error"></div>
            </div>

            {{-- Password --}}
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">
                    Mot de passe
                    <span style="font-weight:400;color:var(--text-muted)">(auto-généré si vide)</span>
                </label>
                <input type="password" x-model="userForm.password"
                       :class="{'error': userErrors.password}"
                       class="form-control"
                       placeholder="••••••••"
                       @keydown.enter.prevent="createUser()">
                <div x-show="userErrors.password" x-text="userErrors.password?.[0]" class="form-error"></div>
            </div>

            {{-- Global error --}}
            <div x-show="userError" x-text="userError"
                 style="font-size:.8125rem;color:#ef4444;background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.625rem .875rem"></div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.5rem">
            <button type="button" @click="userModal=false" class="btn btn-outline">Annuler</button>
            <button type="button" @click="createUser()"
                    :disabled="userSaving || !userForm.name.trim() || !userForm.email.trim()"
                    class="btn btn-primary">
                <span x-show="!userSaving"><i class="ri-check-line"></i> Créer</span>
                <span x-show="userSaving"><span class="btn-spinner"></span> Création…</span>
            </button>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

<script>
function instanceQuickCreate() {
    return {
        /* ── state ── */
        teamModal:    false,
        userModal:    false,
        teamSaving:   false,
        userSaving:   false,
        teamError:    '',
        userError:    '',
        teamErrors:   {},
        userErrors:   {},
        agents:       @json($agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'role' => $a->role])),
        teamForm:     { name: '', description: '', members: [] },
        userForm:     { name: '', email: '', password: '' },
        membersOpen:  false,
        membersSearch:'',

        /* ── computed ── */
        get membersDisplayText() {
            if (!this.teamForm.members.length) return '';
            const sel = this.agents.filter(a => this.teamForm.members.includes(a.id));
            if (sel.length === 1) return sel[0].name + ' (' + sel[0].role + ')';
            return sel.length + ' sélectionné(s)';
        },

        get filteredAgents() {
            const q = this.membersSearch.trim().toLowerCase();
            if (!q) return this.agents;
            return this.agents.filter(a =>
                (a.name + ' ' + a.role).toLowerCase().includes(q)
            );
        },

        /* ── methods ── */
        toggleMember(id) {
            const idx = this.teamForm.members.indexOf(id);
            if (idx >= 0) this.teamForm.members.splice(idx, 1);
            else          this.teamForm.members.push(id);
        },

        openTeamModal() {
            this.teamError    = '';
            this.teamErrors   = {};
            this.membersOpen  = false;
            this.membersSearch= '';
            this.teamForm     = { name: '', description: '', members: [] };
            this.teamModal    = true;
        },

        openUserModal() {
            this.userError  = '';
            this.userErrors = {};
            this.userForm   = { name: '', email: '', password: '' };
            this.userModal  = true;
        },

        escHandler(e) {
            if (this.membersOpen) { this.membersOpen = false; e.stopPropagation(); return; }
            if (this.userModal)   { this.userModal   = false; e.stopPropagation(); return; }
            if (this.teamModal)   { this.teamModal   = false; e.stopPropagation(); }
        },

        async createTeam() {
            if (!this.teamForm.name.trim()) return;
            this.teamError  = '';
            this.teamErrors = {};
            this.teamSaving = true;
            try {
                const res = await fetch(@json(route('admin.teams.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name:        this.teamForm.name.trim(),
                        description: this.teamForm.description.trim() || null,
                        is_active:   1,
                        members:     this.teamForm.members,
                    }),
                });

                if (!res.ok) {
                    const err = await res.json();
                    if (res.status === 422) {
                        this.teamErrors = err.errors || {};
                    } else {
                        this.teamError = err.message || 'Une erreur est survenue.';
                    }
                    return;
                }

                const team = await res.json();

                // Inject into instance form's team select & auto-select
                const sel = this.$refs.teamSelect;
                const opt = document.createElement('option');
                opt.value    = team.id;
                opt.text     = team.name;
                opt.selected = true;
                sel.add(opt);

                this.teamModal = false;
                window.showToast?.('success', 'Équipe « ' + team.name + ' » créée.');
            } catch (e) {
                this.teamError = 'Erreur réseau. Réessayez.';
            } finally {
                this.teamSaving = false;
            }
        },

        async createUser() {
            if (!this.userForm.name.trim() || !this.userForm.email.trim()) return;
            this.userError  = '';
            this.userErrors = {};
            this.userSaving = true;
            try {
                const res = await fetch(@json(route('admin.users.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name:     this.userForm.name.trim(),
                        email:    this.userForm.email.trim(),
                        password: this.userForm.password || null,
                        role:     'agent',
                    }),
                });

                if (!res.ok) {
                    const err = await res.json();
                    if (res.status === 422) {
                        this.userErrors = err.errors || {};
                    } else {
                        this.userError = err.message || 'Une erreur est survenue.';
                    }
                    return;
                }

                const user = await res.json();

                // Add to agents list and auto-select in members dropdown
                this.agents.push({ id: user.id, name: user.name, role: user.role });
                this.$nextTick(() => {
                    if (!this.teamForm.members.includes(user.id)) {
                        this.teamForm.members.push(user.id);
                    }
                });

                this.userModal = false;
                window.showToast?.('success', user.name + ' ajouté(e).');
            } catch (e) {
                this.userError = 'Erreur réseau. Réessayez.';
            } finally {
                this.userSaving = false;
            }
        },
    };
}
</script>
@endsection

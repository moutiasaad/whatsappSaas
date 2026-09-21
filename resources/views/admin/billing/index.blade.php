@extends('layouts.admin')

@section('title', __('ui.tenant_billing.title'))

@section('breadcrumb')
    <span>{{ __('ui.tenant_billing.breadcrumb') }}</span>
@endsection

@section('content')
@php
    $u          = auth()->user();
    $currency   = config('billing.currency', 'USD');
    $currentId  = (int) $tenant->plan_id;

    // The cheapest active plan that costs more than the current one — the
    // natural upgrade. Falls back to the current plan (a renewal) when the
    // tenant is already on the top tier, and to the cheapest when they have none.
    $suggestedPlanId = $plans
        ->first(fn ($p) => (float) $p->price_monthly > (float) ($tenant->plan?->price_monthly ?? -1))
        ?->id
        ?? ($currentId ?: $plans->first()?->id);
    $onTrial    = $tenant->subscription_status === 'trial' && $tenant->trial_ends_at;
    $trialLeft  = $onTrial ? max(0, (int) now()->startOfDay()->diffInDays($tenant->trial_ends_at->copy()->startOfDay(), false)) : 0;
    $endsAt     = $tenant->subscription_ends_at;

    // Trial progress. Anchored on subscription_starts_at when present so the
    // bar reflects the real window rather than assuming a fixed length.
    $trialTotal = $onTrial && $tenant->subscription_starts_at
        ? max(1, (int) $tenant->subscription_starts_at->startOfDay()->diffInDays($tenant->trial_ends_at->startOfDay()))
        : max(1, (int) ($tenant->plan?->trialDays() ?: config('app.trial_days', 7)));
    $trialPct   = $onTrial ? min(100, max(0, (int) round((($trialTotal - $trialLeft) / $trialTotal) * 100))) : 0;

    // ── Usage meters ──────────────────────────────────────────────────────
    $seatLimit  = $usage['user_limit'];
    $seatsUsed  = $usage['users'];
    $seatPct    = $seatLimit > 0 ? min(100, (int) round($seatsUsed / $seatLimit * 100)) : 0;
    $seatsFree  = $seatLimit > 0 ? max(0, $seatLimit - $seatsUsed) : null;

    $aiQuota    = $usage['ai_quota'];           // null = unlimited, 0 = no AI
    $aiUsed     = $usage['ai_used'];
    $aiCredits  = $usage['ai_credits'];
    $aiUnlimited= $aiQuota === null;
    $aiOff      = $aiQuota === 0;
    $aiPct      = ($aiUnlimited || $aiOff) ? 0 : min(100, (int) round($aiUsed / max(1, $aiQuota) * 100));
    // Messages actually left to spend: what remains of the allowance, plus the
    // top-up reserve that is only drawn on once the allowance is gone.
    $aiLeft     = $aiUnlimited ? null : max(0, $aiQuota - $aiUsed) + $aiCredits;

    $meterClass = fn (int $pct) => $pct >= 100 ? 'full' : ($pct >= 75 ? 'warn' : 'ok');

    // $19 stays "$19", $7.50 stays "$7.50" — trimming every trailing zero
    // turned a price with cents into "$7.5", which reads as a typo.
    $price = function ($value): string {
        $value = (float) $value;

        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0)
            : number_format($value, 2);
    };

    // Add-on availability. Packs are pointless on an unlimited plan and
    // impossible on one with no AI at all, so the row simply is not offered.
    $canBuyPack = $pricing['packs_enabled'] && !$aiUnlimited && !$aiOff;
    $canBuySeat = $pricing['seats_enabled'];
    $anyAddon   = $canBuyPack || $canBuySeat;
@endphp

<div class="bl-wrap"
     x-data="billingPage({
        currentPlan: {{ $currentId ?: 'null' }},
        suggestedPlan: {{ $suggestedPlanId ?: 'null' }},
        packPrice: {{ $pricing['pack_price'] }},
        packMessages: {{ $pricing['pack_messages'] }},
        maxPacks: {{ $pricing['pack_max'] }},
        seatPrice: {{ $pricing['seat_price'] }},
        maxSeats: {{ $pricing['seat_max'] }},
        canBuyPack: {{ $canBuyPack ? 'true' : 'false' }},
        canBuySeat: {{ $canBuySeat ? 'true' : 'false' }},
        plans: {{ Js::from($plans->map(fn ($p) => [
            'id'    => $p->id,
            'name'  => $p->name,
            'price' => (float) $p->price_monthly,
        ])->values()) }},
     })">

    {{-- ══════════ CURRENT PLAN / TRIAL ══════════ --}}
    @if($onTrial)
    <div class="bl-cur trial">
        <div class="ic"><i class="ri-time-line"></i></div>
        <div class="m">
            <div class="h">
                {{ __('ui.tenant_billing.trial_heading', ['plan' => $tenant->plan?->name ?? '—']) }}
                <span class="pill">{{ __('ui.tenant_billing.ends_on', ['date' => $tenant->trial_ends_at->translatedFormat('j M')]) }}</span>
            </div>
            <div class="s">{{ __('ui.tenant_billing.trial_sub') }}</div>
            <div class="bar"><i style="width:{{ $trialPct }}%"></i></div>
        </div>
        <div class="days"><b>{{ $trialLeft }}</b><span>{{ trans_choice('ui.tenant_billing.days_left', $trialLeft) }}</span></div>
    </div>
    @else
    <div class="bl-cur {{ $tenant->isActive() ? 'live' : 'lapsed' }}">
        <div class="ic"><i class="{{ $tenant->isActive() ? 'ri-shield-check-line' : 'ri-error-warning-line' }}"></i></div>
        <div class="m">
            <div class="h">
                {{ $tenant->plan?->name ?? __('ui.tenant_billing.no_plan') }}
                <span class="pill">{{ __('ui.tenant_billing.status_' . ($tenant->isActive() ? 'active' : 'inactive')) }}</span>
            </div>
            <div class="s">
                @if($tenant->isActive() && $endsAt)
                    {{ __('ui.tenant_billing.renews_on', ['date' => $endsAt->translatedFormat('j F Y')]) }}
                @else
                    {{ __('ui.tenant_billing.lapsed_sub') }}
                @endif
            </div>
        </div>
        @if($tenant->plan)
        <div class="days"><b>{{ $tenant->plan->formatLocalPrice($visitorCountry ?? null, 'monthly') }}</b><span>{{ __('ui.tenant_billing.per_month_short') }}</span></div>
        @endif
    </div>
    @endif

    <div class="bl-grid">
        <div class="bl-col">

            {{-- ══════════ USAGE ══════════ --}}
            <div class="card">
                <div class="bl-ch">
                    <div class="m">
                        <h3>{{ __('ui.tenant_billing.usage_title') }}</h3>
                        <p>
                            {{-- quota_reset_at is only advanced when a reply is
                                 billed, so an idle tenant carries a date in the
                                 past. Printing it would promise a reset that has
                                 visibly already gone by. --}}
                            @if($usage['ai_resets_at'] && $usage['ai_resets_at']->isFuture())
                                {{ __('ui.tenant_billing.usage_sub', ['date' => $usage['ai_resets_at']->translatedFormat('j F')]) }}
                            @else
                                {{ __('ui.tenant_billing.usage_sub_generic') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="bl-cb">
                    <div class="bl-usage">

                        <div class="um">
                            <div class="r1">
                                <span class="ic teal"><i class="ri-team-line"></i></span>
                                <span class="lb">{{ __('ui.tenant_billing.meter_users') }}</span>
                                @if($seatsFree === null)
                                    <span class="st ok">{{ __('ui.tenant_billing.no_limit') }}</span>
                                @else
                                    <span class="st {{ $meterClass($seatPct) }}">
                                        {{ $seatsFree > 0 ? trans_choice('ui.tenant_billing.seats_free', $seatsFree) : __('ui.tenant_billing.at_capacity') }}
                                    </span>
                                @endif
                            </div>
                            <div class="nums">
                                <span class="v">{{ number_format($seatsUsed) }}</span>
                                <span class="of">/ {{ $seatLimit > 0 ? number_format($seatLimit) : '∞' }}</span>
                            </div>
                            <div class="bar"><i class="{{ $meterClass($seatPct) }}" style="width:{{ $seatLimit > 0 ? $seatPct : 100 }}%"></i></div>
                            <div class="fo">
                                <span class="t">
                                    @if(($usage['extra_seats'] ?? 0) > 0)
                                        {{ trans_choice('ui.tenant_billing.seats_purchased_foot', $usage['extra_seats'], ['count' => $usage['extra_seats']]) }}
                                    @else
                                        {{ __('ui.tenant_billing.seats_foot') }}
                                    @endif
                                </span>
                                @if($canBuySeat)
                                <button type="button" class="btn btn-outline btn-sm" @click="jump('seats')">{{ __('ui.tenant_billing.add_seats') }}</button>
                                @else
                                <a class="btn btn-outline btn-sm" href="{{ route($u->routeNamePrefix() . '.users.index') }}">{{ __('ui.tenant_billing.manage_users') }}</a>
                                @endif
                            </div>
                        </div>

                        <div class="um">
                            <div class="r1">
                                <span class="ic accent"><i class="ri-sparkling-2-line"></i></span>
                                <span class="lb">{{ __('ui.tenant_billing.meter_ai') }}</span>
                                @if($aiUnlimited)
                                    <span class="st ok">{{ __('ui.tenant_billing.unlimited') }}</span>
                                @elseif($aiOff)
                                    <span class="st full">{{ __('ui.tenant_billing.ai_off') }}</span>
                                @else
                                    <span class="st {{ $meterClass($aiPct) }}">{{ __('ui.tenant_billing.pct_used', ['pct' => $aiPct]) }}</span>
                                @endif
                            </div>
                            <div class="nums">
                                <span class="v">{{ number_format($aiUsed) }}</span>
                                <span class="of">/ {{ $aiUnlimited ? '∞' : number_format((int) $aiQuota) }}</span>
                            </div>
                            <div class="bar"><i class="{{ $aiUnlimited ? 'ok' : $meterClass($aiPct) }}" style="width:{{ $aiUnlimited ? 100 : $aiPct }}%"></i></div>
                            <div class="fo">
                                <span class="t">
                                    @if($aiUnlimited)
                                        {{ __('ui.tenant_billing.ai_unlimited_foot') }}
                                    @elseif($aiOff)
                                        {{ __('ui.tenant_billing.ai_off_foot') }}
                                    @elseif($aiCredits > 0)
                                        {{ __('ui.tenant_billing.ai_credits_foot', ['n' => number_format($aiCredits)]) }}
                                    @else
                                        {{ __('ui.tenant_billing.ai_left_foot', ['n' => number_format((int) $aiLeft)]) }}
                                    @endif
                                </span>
                                @if($canBuyPack)
                                <button type="button" class="btn btn-outline btn-sm" @click="jump('packs')">{{ __('ui.tenant_billing.add_messages') }}</button>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ══════════ PLANS ══════════ --}}
            <div class="card">
                <div class="bl-ch">
                    <div class="m">
                        <h3>{{ __('ui.tenant_billing.plans_title') }}</h3>
                        <p>{{ __('ui.tenant_billing.plans_sub') }}</p>
                    </div>
                </div>
                <div class="bl-cb">
                    <div class="bl-plans">
                        @foreach($plans as $i => $p)
                        @php
                            $isCurrent = $p->id === $currentId;
                            $isPopular = $i === 1 && $plans->count() >= 2;
                            $picked    = $p->landingAttributes();
                        @endphp
                        <button type="button"
                                class="bl-plan"
                                :class="{ sel: selectedPlan === {{ $p->id }} }"
                                @click="selectPlan({{ $p->id }})"
                                @if($isCurrent) data-current="1" @endif>
                            <div class="tags">
                                @if($isCurrent)<span class="tag cur">{{ __('ui.tenant_billing.tag_current') }}</span>
                                @elseif($isPopular)<span class="tag pop">{{ __('landing.popular_short') }}</span>@endif
                            </div>
                            <div class="nm">{{ $p->name }}</div>
                            <div class="pr">
                                <b>${{ $price($p->price_monthly) }}</b>
                                <span>{{ __('landing.plan_per_month') }}</span>
                            </div>
                            <div class="quo">
                                <div class="q">
                                    <div class="qv">{{ $p->max_users ? number_format($p->max_users) : '∞' }}</div>
                                    <div class="ql">{{ __('ui.tenant_billing.q_users') }}</div>
                                </div>
                                <div class="q">
                                    <div class="qv">{{ $p->ai_message_quota === null ? '∞' : number_format((int) $p->ai_message_quota) }}</div>
                                    <div class="ql">{{ __('ui.tenant_billing.q_ai') }}</div>
                                </div>
                            </div>
                            <div class="unl">
                                <i class="ri-check-line"></i>
                                <span>{{ __('landing.plan_unlimited_headline') }}</span>
                            </div>
                            @if($picked)
                            <ul>
                                @foreach($picked as $attr)
                                    @php
                                        $line = str_starts_with($attr, 'module:')
                                            ? __('ui.plan_modules.' . substr($attr, 7))
                                            : null;
                                    @endphp
                                    @if($line)<li><i class="ri-check-line"></i>{{ $line }}</li>@endif
                                @endforeach
                            </ul>
                            @endif
                            <span class="pick" :class="{ on: selectedPlan === {{ $p->id }} }">
                                <template x-if="selectedPlan === {{ $p->id }}"><i class="ri-check-line"></i></template>
                                <span x-text="selectedPlan === {{ $p->id }} ? @js(__('ui.tenant_billing.selected')) : @js($isCurrent ? __('ui.tenant_billing.renew_this') : __('ui.tenant_billing.choose_this'))"></span>
                            </span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ══════════ ADD CAPACITY ══════════ --}}
            @if($anyAddon)
            <div class="card" id="addons">
                <div class="bl-ch">
                    <div class="m">
                        <h3>{{ __('ui.tenant_billing.addons_title') }}</h3>
                        <p>{{ __('ui.tenant_billing.addons_sub') }}</p>
                    </div>
                </div>
                <div class="bl-cb">

                    @if($canBuySeat)
                    <div class="bl-ao" id="row-seats">
                        <div class="ic teal"><i class="ri-user-add-line"></i></div>
                        <div class="m">
                            <div class="n">{{ __('ui.tenant_billing.seat_name') }}</div>
                            <div class="s">{{ __('ui.tenant_billing.seat_desc') }}</div>
                        </div>
                        <div class="pu">
                            ${{ $price($pricing['seat_price']) }}
                            <small>{{ __('ui.tenant_billing.per_seat') }}</small>
                        </div>
                        <div class="bl-step">
                            <button type="button" @click="seats = Math.max(0, seats - 1)" :disabled="seats === 0" aria-label="{{ __('ui.tenant_billing.decrease') }}"><i class="ri-subtract-line"></i></button>
                            <span class="v" x-text="seats"></span>
                            <button type="button" @click="seats = Math.min(maxSeats, seats + 1)" :disabled="seats === maxSeats" aria-label="{{ __('ui.tenant_billing.increase') }}"><i class="ri-add-line"></i></button>
                        </div>
                    </div>
                    @endif

                    @if($canBuyPack)
                    <div class="bl-ao" id="row-packs">
                        <div class="ic accent"><i class="ri-sparkling-2-line"></i></div>
                        <div class="m">
                            <div class="n">{{ __('ui.tenant_billing.pack_name', ['n' => number_format($pricing['pack_messages'])]) }}</div>
                            <div class="s">{{ __('ui.tenant_billing.pack_desc') }}</div>
                        </div>
                        <div class="pu">
                            ${{ $price($pricing['pack_price']) }}
                            <small>{{ __('ui.tenant_billing.per_pack') }}</small>
                        </div>
                        <div class="bl-step">
                            <button type="button" @click="packs = Math.max(0, packs - 1)" :disabled="packs === 0" aria-label="{{ __('ui.tenant_billing.decrease') }}"><i class="ri-subtract-line"></i></button>
                            <span class="v" x-text="packs"></span>
                            <button type="button" @click="packs = Math.min(maxPacks, packs + 1)" :disabled="packs === maxPacks" aria-label="{{ __('ui.tenant_billing.increase') }}"><i class="ri-add-line"></i></button>
                        </div>
                    </div>
                    @endif

                    <div class="bl-aonote">
                        <i class="ri-information-line"></i>
                        <span x-html="addonNote"></span>
                    </div>
                </div>
            </div>
            @endif

            {{-- ══════════ PAYMENT METHOD ══════════ --}}
            <div class="card">
                <div class="bl-ch"><div class="m">
                    <h3>{{ __('ui.tenant_billing.method_title') }}</h3>
                    <p>{{ __('ui.tenant_billing.method_sub') }}</p>
                </div></div>
                <div class="bl-cb">
                    <div class="bl-pm">
                        <div class="brand"><i class="ri-paypal-fill"></i></div>
                        <div class="m">
                            <div class="n">{{ __('ui.tenant_billing.method_paypal') }}</div>
                            <div class="s">{{ __('ui.tenant_billing.method_paypal_sub') }}</div>
                        </div>
                        <span class="badge badge-green">{{ __('ui.tenant_billing.method_ready') }}</span>
                    </div>
                </div>
            </div>

            {{-- ══════════ INVOICES ══════════ --}}
            <div class="card">
                <div class="bl-ch"><div class="m">
                    <h3>{{ __('ui.tenant_billing.invoices_title') }}</h3>
                    <p>{{ __('ui.tenant_billing.invoices_sub', ['email' => $u->email]) }}</p>
                </div></div>
                <div class="bl-cb" style="padding-top:10px">
                    <div class="bl-tblwrap">
                        <table class="data-table">
                            <thead><tr>
                                <th>{{ __('ui.tenant_billing.col_invoice') }}</th>
                                <th>{{ __('ui.tenant_billing.col_date') }}</th>
                                <th>{{ __('ui.tenant_billing.col_amount') }}</th>
                                <th>{{ __('ui.tenant_billing.col_status') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse($invoices as $inv)
                                <tr>
                                    <td>
                                        <div style="font-weight:600">
                                            @if($inv->isAiPack())
                                                {{ __('ui.tenant_billing.inv_ai_pack', ['n' => number_format($inv->packMessages())]) }}
                                            @elseif($inv->isSeatPack())
                                                {{ trans_choice('ui.tenant_billing.inv_seats', $inv->packSeats(), ['count' => $inv->packSeats()]) }}
                                            @else
                                                {{ $inv->plan?->name ?? __('ui.tenant_billing.inv_subscription') }}
                                            @endif
                                        </div>
                                        <div class="text-muted">#{{ str_pad($inv->id, 5, '0', STR_PAD_LEFT) }}</div>
                                    </td>
                                    <td>{{ ($inv->paid_at ?? $inv->created_at)?->translatedFormat('j M Y') }}</td>
                                    <td style="font-variant-numeric:tabular-nums">{{ $inv->currency }} {{ number_format((float) $inv->amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $inv->isCompleted() ? 'badge-green' : ($inv->isPending() ? 'badge-orange' : 'badge-red') }}">
                                            {{ __('ui.tenant_billing.pay_status_' . $inv->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" style="padding:26px 10px;text-align:center;color:var(--text-muted)">
                                    {{ __('ui.tenant_billing.no_invoices') }}
                                </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- ══════════ ORDER SUMMARY ══════════ --}}
        <div>
            <div class="bl-sum">
                <div class="sh">
                    <h3>{{ __('ui.tenant_billing.summary_title') }}</h3>
                    <p x-text="summarySub"></p>
                </div>
                <div class="sb">
                    <template x-if="!hasOrder">
                        <div class="bl-nochange">
                            <i class="ri-checkbox-circle-line"></i>
                            <div class="t">{{ __('ui.tenant_billing.nothing_selected') }}</div>
                            <div class="s">{{ __('ui.tenant_billing.nothing_selected_sub') }}</div>
                        </div>
                    </template>

                    <template x-if="hasOrder">
                        <div>
                            <template x-if="planLine">
                                <div class="li">
                                    <div class="m">
                                        <div class="n" x-text="planLine.name"></div>
                                        <div class="s" x-text="planLine.sub"></div>
                                    </div>
                                    <div class="v" x-text="money(planLine.amount)"></div>
                                </div>
                            </template>
                            <template x-if="seats > 0">
                                <div class="li">
                                    <div class="m">
                                        <div class="n">{{ __('ui.tenant_billing.seat_line') }}</div>
                                        <div class="s" x-text="seats + ' × ' + money(seatPrice)"></div>
                                    </div>
                                    <div class="v" x-text="money(seats * seatPrice)"></div>
                                </div>
                            </template>
                            <template x-if="packs > 0">
                                <div class="li">
                                    <div class="m">
                                        <div class="n">{{ __('ui.tenant_billing.pack_line') }}</div>
                                        <div class="s" x-text="packs + ' × ' + fmt(packMessages) + ' {{ __('ui.tenant_billing.messages_word') }}'"></div>
                                    </div>
                                    <div class="v" x-text="money(packs * packPrice)"></div>
                                </div>
                            </template>
                            <div class="bl-div"></div>
                            <div class="bl-tot">
                                <span class="l">{{ __('ui.tenant_billing.total') }}</span>
                                <div class="r">
                                    <b x-text="money(total)"></b>
                                    <span>{{ $currency }} · {{ __('ui.tenant_billing.excl_tax') }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="sf">
                    {{-- One order, one approval, one charge. A plan change and its
                         add-ons used to be up to three separate hand-offs to
                         PayPal, which read as three unrelated purchases. --}}
                    <template x-if="hasOrder">
                        <a class="btn btn-primary bl-btn-lg" :href="checkoutUrl">
                            <i class="ri-arrow-up-circle-line"></i>
                            <span x-text="ctaLabel"></span>
                        </a>
                    </template>

                    <template x-if="!hasOrder">
                        <button type="button" class="btn btn-outline bl-btn-lg" disabled>
                            {{ __('ui.tenant_billing.nothing_selected') }}
                        </button>
                    </template>

                    <div class="bl-trust">
                        <span><i class="ri-shield-check-line"></i>{{ __('ui.tenant_billing.trust_paypal') }}</span>
                        <span><i class="ri-check-line"></i>{{ __('ui.tenant_billing.trust_cancel') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function billingPage(cfg) {
    return {
        // Opens on the next plan up rather than nothing: the page's job is to
        // sell the upgrade, and an empty summary asks the reader to do the work.
        selectedPlan: cfg.suggestedPlan,
        currentPlan: cfg.currentPlan,
        packs: 0,
        seats: 0,
        packPrice: cfg.packPrice,
        packMessages: cfg.packMessages,
        maxPacks: cfg.maxPacks,
        seatPrice: cfg.seatPrice,
        maxSeats: cfg.maxSeats,
        canBuyPack: cfg.canBuyPack,
        canBuySeat: cfg.canBuySeat,
        plans: cfg.plans,

        money(n) { return '$' + Number(n).toFixed(2); },
        fmt(n)   { return Number(n).toLocaleString(); },

        selectPlan(id) {
            // Clicking the selected plan again clears it, so the summary can go
            // back to "nothing selected" without a reload.
            this.selectedPlan = this.selectedPlan === id ? null : id;
        },

        get planLine() {
            if (!this.selectedPlan) return null;
            const p = this.plans.find(x => x.id === this.selectedPlan);
            if (!p) return null;
            return {
                name: p.name + ' {{ __('ui.tenant_billing.plan_word') }}',
                sub: this.selectedPlan === this.currentPlan
                    ? @js(__('ui.tenant_billing.renewal_word'))
                    : @js(__('ui.tenant_billing.plan_change_word')),
                amount: p.price,
            };
        },

        /*
         * The button says what the tenant gets, not who processes the card.
         * Which phrasing depends on what is actually in the order: a plan they
         * are moving up to, one they already have (a renewal), a downgrade, or
         * add-ons on their own.
         */
        get ctaLabel() {
            const amount = this.money(this.total);
            const p = this.plans.find(x => x.id === this.selectedPlan);

            if (!p) return @js(__('ui.tenant_billing.add_capacity_cta')).replace(':amount', amount);

            const current = this.plans.find(x => x.id === this.currentPlan);
            let key;
            if (this.selectedPlan === this.currentPlan)        key = @js(__('ui.tenant_billing.renew_cta'));
            else if (current && p.price < current.price)       key = @js(__('ui.tenant_billing.downgrade_cta'));
            else                                               key = @js(__('ui.tenant_billing.upgrade_cta'));

            return key.replace(':plan', p.name).replace(':amount', amount);
        },

        get hasOrder() { return !!this.planLine || this.packs > 0 || this.seats > 0; },

        get checkoutUrl() {
            const q = new URLSearchParams();
            // Selecting the plan already in use is a renewal, which is a real
            // purchase — so it is sent like any other plan selection.
            if (this.selectedPlan) q.set('plan_id', this.selectedPlan);
            if (this.seats > 0) q.set('seats', this.seats);
            if (this.packs > 0) q.set('packs', this.packs);
            return @js(route('payment.cart')) + '?' + q.toString();
        },

        get total() {
            return (this.planLine ? this.planLine.amount : 0)
                 + this.packs * this.packPrice
                 + this.seats * this.seatPrice;
        },

        get summarySub() {
            if (!this.hasOrder) return @js(__('ui.tenant_billing.summary_sub_empty'));
            return @js(__('ui.tenant_billing.summary_sub')) ;
        },

        get addonNote() {
            const parts = [];
            if (this.seats > 0) {
                parts.push(@js(__('ui.tenant_billing.seat_note')).replace(':n', '<b>' + this.seats + '</b>'));
            }
            if (this.packs > 0) {
                parts.push(@js(__('ui.tenant_billing.pack_note')).replace(':n', '<b>' + this.fmt(this.packs * this.packMessages) + '</b>'));
            }
            return parts.length ? parts.join(' ') : @js(__('ui.tenant_billing.addon_note_zero'));
        },

        /* The usage meters' "Add" buttons scroll to the matching row and flash
           it, so the connection between the meter and the thing you buy is
           visible rather than implied. */
        jump(which) {
            const el = document.getElementById('row-' + which);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.animate([{ background: '#ecf7f6' }, { background: 'transparent' }], { duration: 1100 });
        },
    };
}
</script>
@endsection

@push('styles')
<style>
    /* Centred, and capped to match the dashboard's 1420px. Without the auto
       margin the whole page hugged the sidebar and left a wide dead strip down
       the right of any screen bigger than the cap. */
    .bl-wrap { max-width: 1420px; margin-inline: auto; }
    .bl-grid { display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 18px; align-items: start; }
    .bl-col  { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

    .bl-ch { padding: 16px 18px 0; display: flex; align-items: flex-start; gap: 12px; }
    .bl-ch .m { flex: 1; min-width: 0; }
    .bl-ch h3 { font-size: 15.5px; font-weight: 700; letter-spacing: -.015em; margin: 0; color: var(--text-primary); }
    .bl-ch p { font-size: 12.5px; color: var(--text-secondary); margin: 2px 0 0; }
    .bl-cb { padding: 16px 18px 18px; }

    /* ── current plan / trial banner ── */
    .bl-cur { border-radius: 16px; padding: 18px 20px; display: flex; align-items: center; gap: 18px; margin-bottom: 18px; }
    .bl-cur.trial  { border: 1px solid #fcd9a4; background: linear-gradient(100deg, #fef3e2, #fff 60%); }
    .bl-cur.live   { border: 1px solid var(--brand-light); background: linear-gradient(100deg, var(--brand-xlight), #fff 60%); }
    .bl-cur.lapsed { border: 1px solid #fecaca; background: linear-gradient(100deg, var(--red-bg), #fff 60%); }
    .bl-cur .ic { width: 44px; height: 44px; border-radius: 12px; color: #fff; display: grid; place-items: center; flex-shrink: 0; font-size: 21px; }
    .bl-cur.trial  .ic { background: var(--orange); }
    .bl-cur.live   .ic { background: var(--brand); }
    .bl-cur.lapsed .ic { background: var(--red); }
    .bl-cur .m { flex: 1; min-width: 0; }
    .bl-cur .h { font-size: 15.5px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
    .bl-cur .pill { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 3px 8px; border-radius: 5px; color: #fff; }
    .bl-cur.trial  .pill { background: var(--orange); }
    .bl-cur.live   .pill { background: var(--brand); }
    .bl-cur.lapsed .pill { background: var(--red); }
    .bl-cur .s { font-size: 13px; color: var(--text-secondary); margin-top: 3px; }
    .bl-cur .bar { height: 6px; border-radius: 99px; background: rgba(15,23,42,.09); margin-top: 11px; overflow: hidden; max-width: 460px; }
    .bl-cur .bar i { display: block; height: 100%; border-radius: 99px; background: var(--orange); }
    .bl-cur .days { text-align: center; flex-shrink: 0; padding: 0 4px; }
    .bl-cur .days b { display: block; font-size: 31px; font-weight: 800; letter-spacing: -.045em; line-height: 1; }
    .bl-cur.trial  .days b { color: var(--orange); }
    .bl-cur.live   .days b { color: var(--brand); }
    .bl-cur.lapsed .days b { color: var(--red); }
    .bl-cur .days span { font-size: 11px; font-weight: 600; color: var(--text-secondary); letter-spacing: .06em; text-transform: uppercase; }

    /* ── usage meters ── */
    .bl-usage { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .um { border: 1px solid var(--card-border); border-radius: 13px; padding: 15px 16px; }
    .um .r1 { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; }
    .um .ic { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; font-size: 15px; }
    .um .ic.teal   { background: var(--brand-xlight); color: var(--brand); }
    .um .ic.accent { background: rgba(21,182,168,.13); color: var(--accent); }
    .um .lb { font-size: 13px; font-weight: 600; flex: 1; min-width: 0; color: var(--text-primary); }
    .um .st { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 2.5px 7px; border-radius: 5px; white-space: nowrap; }
    .um .st.ok   { background: var(--brand-xlight); color: var(--brand); }
    .um .st.warn { background: var(--orange-bg); color: var(--orange); }
    .um .st.full { background: var(--red-bg); color: var(--red); }
    .um .nums { display: flex; align-items: flex-end; gap: 6px; }
    .um .v { font-size: 26px; font-weight: 800; letter-spacing: -.045em; line-height: 1; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    .um .of { font-size: 13px; color: var(--text-muted); font-weight: 500; font-variant-numeric: tabular-nums; }
    .um .bar { height: 6px; border-radius: 99px; background: var(--page-bg); margin-top: 10px; overflow: hidden; }
    .um .bar i { display: block; height: 100%; border-radius: 99px; background: var(--brand); transition: width .3s; }
    .um .bar i.warn { background: var(--orange); }
    .um .bar i.full { background: var(--red); }
    .um .fo { display: flex; align-items: center; gap: 10px; margin-top: 12px; }
    .um .fo .t { font-size: 11.5px; color: var(--text-secondary); flex: 1; min-width: 0; line-height: 1.4; }

    /* ── plan cards ── */
    .bl-plans { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; }
    .bl-plan { border: 1.5px solid var(--card-border); border-radius: 15px; padding: 20px 18px 18px; background: var(--card-bg); text-align: start; display: flex; flex-direction: column; position: relative; transition: var(--transition); min-width: 0; cursor: pointer; font-family: inherit; }
    .bl-plan:hover { border-color: var(--border-2, #cbd5e1); }
    .bl-plan.sel { border-color: var(--brand); box-shadow: 0 0 0 3.5px rgba(15,126,122,.11); }
    .bl-plan .tags { position: absolute; top: -10px; inset-inline-start: 18px; display: flex; gap: 6px; }
    .bl-plan .tag { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 3.5px 9px; border-radius: 6px; white-space: nowrap; }
    .bl-plan .tag.pop { background: var(--brand); color: #fff; }
    .bl-plan .tag.cur { background: var(--sidebar-bg); color: #fff; }
    .bl-plan .nm { font-size: 17px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
    .bl-plan .pr { display: flex; align-items: baseline; gap: 3px; margin-top: 8px; }
    .bl-plan .pr b { font-size: 31px; font-weight: 800; letter-spacing: -.045em; line-height: 1; color: var(--text-primary); }
    .bl-plan .pr span { font-size: 13px; font-weight: 500; color: var(--text-secondary); }
    .bl-plan .quo { display: flex; gap: 10px; margin-top: 14px; padding: 11px 0; border-top: 1px solid var(--card-border); border-bottom: 1px solid var(--card-border); }
    .bl-plan .quo .q { flex: 1; min-width: 0; }
    .bl-plan .quo .qv { font-size: 15.5px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
    .bl-plan .quo .ql { font-size: 10.5px; color: var(--text-secondary); font-weight: 600; letter-spacing: .04em; text-transform: uppercase; margin-top: 1px; }
    .bl-plan .unl { background: var(--brand-xlight); border: 1px solid var(--brand-light); border-radius: 10px; padding: 10px 12px; margin-top: 13px; display: flex; gap: 8px; align-items: flex-start; }
    .bl-plan .unl i { color: var(--brand); font-size: 14px; flex-shrink: 0; line-height: 1.35; }
    .bl-plan .unl span { font-size: 12px; font-weight: 600; color: var(--brand-dark); line-height: 1.4; }
    .bl-plan ul { list-style: none; margin: 13px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .bl-plan li { font-size: 13px; display: flex; gap: 8px; align-items: flex-start; line-height: 1.4; color: var(--text-primary); }
    .bl-plan li i { color: var(--brand); font-size: 14px; flex-shrink: 0; line-height: 1.35; }
    .bl-plan .pick { margin-top: 16px; height: 40px; border-radius: 10px; font-size: 13.5px; font-weight: 600; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-primary); transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 7px; }
    .bl-plan:hover .pick { background: var(--page-bg); }
    .bl-plan .pick.on { background: var(--brand); border-color: var(--brand); color: #fff; }

    /* ── add-on row ── */
    /* Each add-on is its own bordered card. Stacked flush they read as one
       run-on row, which is what made the two steppers look crowded. */
    .bl-ao { display: flex; align-items: center; gap: 14px; border: 1px solid var(--card-border); border-radius: 13px; padding: 16px; }
    .bl-ao + .bl-ao { margin-top: 12px; }
    .bl-ao .ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; flex-shrink: 0; font-size: 19px; }
    .bl-ao .ic.accent { background: rgba(21,182,168,.13); color: var(--accent); }
    .bl-ao .m { flex: 1; min-width: 0; }
    .bl-ao .n { font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
    .bl-ao .s { font-size: 12px; color: var(--text-secondary); margin-top: 2px; line-height: 1.45; }
    .bl-ao .pu { font-size: 13px; font-weight: 700; white-space: nowrap; text-align: end; color: var(--text-primary); }
    .bl-ao .pu small { display: block; font-size: 10.5px; font-weight: 500; color: var(--text-muted); }
    .bl-step { display: flex; align-items: center; border: 1px solid var(--card-border); border-radius: 9px; overflow: hidden; flex-shrink: 0; }
    .bl-step button { width: 32px; height: 34px; display: grid; place-items: center; color: var(--text-secondary); background: none; border: none; cursor: pointer; transition: var(--transition); }
    .bl-step button:hover:not(:disabled) { background: var(--page-bg); color: var(--text-primary); }
    .bl-step button:disabled { opacity: .35; cursor: default; }
    .bl-step .v { width: 40px; text-align: center; font-size: 14px; font-weight: 700; border-inline: 1px solid var(--card-border); height: 34px; line-height: 34px; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .bl-aonote { background: var(--page-bg); border: 1px solid var(--card-border); border-radius: 11px; padding: 11px 13px; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; margin-top: 15px; display: flex; gap: 9px; align-items: flex-start; }
    .bl-aonote i { flex-shrink: 0; color: var(--brand); font-size: 15px; line-height: 1.35; }

    /* ── payment method ── */
    .bl-pm { display: flex; align-items: center; gap: 13px; border: 1px solid var(--card-border); border-radius: 12px; padding: 14px; }
    .bl-pm .brand { width: 44px; height: 34px; border-radius: 7px; background: #003087; color: #fff; display: grid; place-items: center; flex-shrink: 0; font-size: 20px; }
    .bl-pm .m { flex: 1; min-width: 0; }
    .bl-pm .n { font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
    .bl-pm .s { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }

    .bl-tblwrap { overflow-x: auto; }

    /* ── order summary ── */
    .bl-sum { position: sticky; top: 16px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 15px; overflow: hidden; box-shadow: var(--card-shadow); }
    .bl-sum .sh { padding: 16px 18px; border-bottom: 1px solid var(--card-border); }
    .bl-sum .sh h3 { font-size: 15.5px; font-weight: 700; letter-spacing: -.015em; margin: 0; color: var(--text-primary); }
    .bl-sum .sh p { font-size: 12.5px; color: var(--text-secondary); margin: 2px 0 0; }
    .bl-sum .sb { padding: 15px 18px; }
    .bl-sum .sf { padding: 0 18px 18px; }
    .bl-sum .li { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; font-size: 13.5px; }
    .bl-sum .li .m { flex: 1; min-width: 0; }
    .bl-sum .li .n { font-weight: 500; color: var(--text-primary); }
    .bl-sum .li .s { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
    .bl-sum .li .v { font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .bl-div { height: 1px; background: var(--card-border); margin: 9px 0; }
    .bl-tot { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; padding-top: 4px; }
    .bl-tot .l { font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
    .bl-tot .r { text-align: end; }
    .bl-tot .r b { font-size: 26px; font-weight: 800; letter-spacing: -.04em; line-height: 1; display: block; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .bl-tot .r span { font-size: 11.5px; color: var(--text-secondary); }
    .bl-nochange { padding: 22px 8px; text-align: center; }
    .bl-nochange i { font-size: 26px; color: var(--brand); }
    .bl-nochange .t { font-size: 13.5px; font-weight: 600; color: var(--text-secondary); margin-top: 9px; }
    .bl-nochange .s { font-size: 12.5px; color: var(--text-muted); margin-top: 3px; line-height: 1.45; }
    .bl-btn-lg { width: 100%; padding: 12px 18px; font-size: 14px; }
    .bl-trust { display: flex; flex-direction: column; gap: 7px; margin-top: 13px; }
    .bl-trust span { font-size: 11.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 7px; line-height: 1.4; }
    .bl-trust i { flex-shrink: 0; color: var(--brand); font-size: 13px; }

    @media (max-width: 1160px) {
        .bl-grid { grid-template-columns: minmax(0,1fr); }
        .bl-sum { position: static; }
        .bl-plans { grid-template-columns: 1fr; }
    }
    @media (max-width: 820px) {
        .bl-usage { grid-template-columns: 1fr; }
        .bl-cur { flex-wrap: wrap; gap: 14px; padding: 16px; }
        .bl-cur .days { order: -1; }
    }
    @media (max-width: 560px) {
        .bl-ao { flex-wrap: wrap; gap: 10px; }
        .bl-ao .m { flex-basis: calc(100% - 52px); }
        .bl-ao .pu { margin-inline-start: auto; }
    }
</style>
@endpush

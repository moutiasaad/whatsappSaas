@extends('layouts.admin')

@section('title', 'Create Tenant')

@section('breadcrumb')
    <span>Platform</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <a href="{{ route('admin.platform.tenants') }}" style="color:var(--text-secondary);text-decoration:none">Tenants</a>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
    <span>Create</span>
@endsection

@section('content')
<div>
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Create Tenant</div>
            <div class="page-subtitle">One row per SaaS customer in the <code>tenants</code> table</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Tenant Details</div>
                <div class="card-subtitle">Key columns: name, slug, subscription_status, plan_id, trial_ends_at, stripe_id, settings</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.platform.tenants.store') }}" style="padding:0 1.5rem 1.5rem" data-loading>
            @csrf

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem">
                <div class="form-group">
                    <label class="form-label" for="name">Name <span style="color:#ef4444">*</span></label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') error @enderror" required placeholder="e.g. Demo Company">
                    @error('name') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="slug">Slug</label>
                    <input id="slug" type="text" name="slug" value="{{ old('slug') }}" class="form-control @error('slug') error @enderror" placeholder="auto from name if empty">
                    @error('slug') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="subscription_status">Subscription Status <span style="color:#ef4444">*</span></label>
                    <select id="subscription_status" name="subscription_status" class="form-control @error('subscription_status') error @enderror" required>
                        @foreach(['trial','active','suspended','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(old('subscription_status', 'trial') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    @error('subscription_status') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="plan_id">Plan</label>
                    <select id="plan_id" name="plan_id" class="form-control @error('plan_id') error @enderror">
                        <option value="">No plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) old('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    @error('plan_id') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="trial_ends_at">Trial Ends At</label>
                    <input id="trial_ends_at" type="date" name="trial_ends_at" value="{{ old('trial_ends_at') }}" class="form-control @error('trial_ends_at') error @enderror">
                    @error('trial_ends_at') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="stripe_id">Stripe Customer ID</label>
                    <input id="stripe_id" type="text" name="stripe_id" value="{{ old('stripe_id') }}" class="form-control @error('stripe_id') error @enderror" placeholder="cus_xxx">
                    @error('stripe_id') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="settings">Settings (JSON)</label>
                <textarea id="settings" name="settings" class="form-control @error('settings') error @enderror" rows="6" placeholder='{"timezone":"UTC","locale":"en"}'>{{ old('settings') }}</textarea>
                @error('settings') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;color:var(--text-secondary)">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1' || old('is_active') === 1) style="accent-color:var(--brand)">
                    Active tenant
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem">
                <a href="{{ route('admin.platform.tenants') }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Tenant</button>
            </div>
        </form>
    </div>
</div>
@endsection

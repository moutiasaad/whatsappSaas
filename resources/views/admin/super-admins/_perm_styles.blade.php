@push('styles')
<style>
.perm-group {
    border-top: 1px solid var(--card-border);
}
.perm-group-label {
    font-size: .75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: .875rem 1.25rem .5rem;
}
.perm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1.25rem;
    border-bottom: 1px solid var(--card-border);
    cursor: pointer;
    user-select: none;
    transition: background .12s;
}
.perm-row:last-child { border-bottom: none; }
.perm-row:hover { background: var(--page-bg); }
.perm-row-left {
    display: flex;
    align-items: center;
    gap: .75rem;
}
.perm-icon-wrap {
    width: 2rem;
    height: 2rem;
    border-radius: .5rem;
    background: rgba(16,185,129,.08);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--brand);
    flex-shrink: 0;
    font-size: 1rem;
}
.perm-label-text {
    font-size: .9375rem;
    color: var(--text-primary);
}
.toggle-switch { position: relative; }
.toggle-switch input[type="checkbox"] { display: none; }
.toggle-track {
    display: block;
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: #e2e8f0;
    transition: background .2s;
    position: relative;
}
.toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: transform .2s;
}
.perm-checkbox:checked ~ .toggle-track { background: var(--brand); }
.perm-checkbox:checked ~ .toggle-track .toggle-thumb { transform: translateX(20px); }
</style>
@endpush

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('click', function () {
    document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = true; });
});

document.getElementById('deselectAll')?.addEventListener('click', function () {
    document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; });
});
</script>
@endpush

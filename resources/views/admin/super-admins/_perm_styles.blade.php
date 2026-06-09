@push('styles')
<style>
.perm-toggle {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .625rem .875rem;
    border: 1.5px solid var(--card-border);
    border-radius: .625rem;
    cursor: pointer;
    font-size: .875rem;
    color: var(--text-secondary);
    transition: border-color .15s, background .15s, color .15s;
    user-select: none;
}
.perm-toggle:hover {
    border-color: var(--brand);
    color: var(--brand);
    background: rgba(16,185,129,.04);
}
.perm-toggle.checked {
    border-color: var(--brand);
    background: rgba(16,185,129,.08);
    color: var(--brand);
}
.perm-toggle input[type="checkbox"] { display: none; }
.perm-toggle i { font-size: 1rem; flex-shrink: 0; }
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.addEventListener('change', function () {
        this.closest('.perm-toggle').classList.toggle('checked', this.checked);
    });
});

document.getElementById('selectAll')?.addEventListener('click', function () {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = true;
        cb.closest('.perm-toggle').classList.add('checked');
    });
});

document.getElementById('deselectAll')?.addEventListener('click', function () {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = false;
        cb.closest('.perm-toggle').classList.remove('checked');
    });
});
</script>
@endpush

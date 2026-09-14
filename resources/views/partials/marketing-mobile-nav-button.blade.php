{{-- The hamburger itself, rendered inside the header row.

     Kept separate from partials/marketing-mobile-nav because the panel CANNOT
     live inside .nav: that element carries backdrop-filter, which makes it the
     containing block for position:fixed descendants, so a panel nested in there
     resolved top/bottom against the 70px header and collapsed to zero height.
     The button belongs in the header flow; the panel belongs outside it. --}}
<button class="mnav-t" type="button" aria-expanded="false" aria-controls="mnav"
        aria-label="{{ __('landing.nav_menu_open') }}" data-mnav-toggle>
    <span class="mnav-bars" aria-hidden="true"><i></i><i></i><i></i></span>
</button>

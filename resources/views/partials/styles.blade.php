@once
@push('css')
<style>
    .fin-num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fin-neg { color: var(--bs-danger, #dc3545); }
    .fin-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 1rem; }
    .fin-tile { border: 1px solid var(--bs-border-color, #e4e6ef); border-radius: .75rem; padding: 1rem 1.15rem; background: var(--bs-body-bg, #fff); }
    .fin-tile small { display: block; color: var(--bs-secondary-color, #7e8299); font-size: .8rem; margin-bottom: .25rem; }
    .fin-tile strong { font-size: 1.3rem; font-variant-numeric: tabular-nums; }
    .fin-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .75rem 1.5rem; margin: 0; }
    .fin-facts dt { font-weight: 500; color: var(--bs-secondary-color, #7e8299); font-size: .85rem; }
    .fin-facts dd { margin: 0; }
    .fin-tree-1 { padding-left: 1.5rem !important; } .fin-tree-2 { padding-left: 3rem !important; } .fin-tree-3 { padding-left: 4.5rem !important; } .fin-tree-4 { padding-left: 6rem !important; }
    .fin-total td, .fin-total th { font-weight: 700; border-top: 2px solid var(--bs-border-color, #e4e6ef); }
    .fin-lines input, .fin-lines select { min-width: 7rem; }
    @media print { #kt_aside, #kt_header, #kt_toolbar, .nx-filters, .fin-no-print, #kt_footer { display: none !important; } .nx-panel { border: 0 !important; } }
</style>
@endpush
@endonce

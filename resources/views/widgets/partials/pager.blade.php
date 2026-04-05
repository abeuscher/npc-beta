{{-- Shared pagination partial for listing widgets.
     Expects to be nested inside an Alpine x-data scope providing:
       - page        (int, 1-indexed current page)
       - totalPages  (int, computed total pages)
       - setPage(n)  (method to change page)
--}}
<nav
    x-show="totalPages > 1"
    x-cloak
    class="widget-pager"
    aria-label="Pagination"
>
    <button
        type="button"
        class="widget-pager__btn"
        :disabled="page <= 1"
        @click="setPage(page - 1)"
        aria-label="Previous page"
    >&lsaquo;</button>

    <template x-for="p in totalPages" :key="p">
        <template x-if="totalPages <= 7 || p === 1 || p === totalPages || (p >= page - 1 && p <= page + 1)">
            <button
                type="button"
                class="widget-pager__btn"
                :class="{ 'widget-pager__btn--active': p === page }"
                :aria-current="p === page ? 'page' : false"
                @click="setPage(p)"
                x-text="p"
            ></button>
        </template>
    </template>

    {{-- Ellipsis before current cluster --}}
    <span
        x-show="totalPages > 7 && page > 3"
        class="widget-pager__ellipsis"
        aria-hidden="true"
    >&hellip;</span>

    {{-- Ellipsis after current cluster --}}
    <span
        x-show="totalPages > 7 && page < totalPages - 2"
        class="widget-pager__ellipsis"
        aria-hidden="true"
    >&hellip;</span>

    <button
        type="button"
        class="widget-pager__btn"
        :disabled="page >= totalPages"
        @click="setPage(page + 1)"
        aria-label="Next page"
    >&rsaquo;</button>
</nav>

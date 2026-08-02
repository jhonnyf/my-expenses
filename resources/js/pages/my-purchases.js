import Utils from '../utils';

const MyPurchases = (() => {
    let initialized = false;
    let debounceTimer = null;

    // Parte da URL atual (não da rota base) — preserva start_date/end_date do
    // filtro de período já aplicado, senão buscar por emissor perderia o filtro.
    const submitSearch = (input) => {
        const term = input.value.trim();
        const url = new URL(window.location.href);

        if (term !== '') {
            url.searchParams.set('search', term);
        } else {
            url.searchParams.delete('search');
        }

        window.location.href = url.toString();
    };

    const handleInput = (e) => {
        const input = e.target.closest('#myPurchasesSearchInput');
        if (!input) return;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => submitSearch(input), 500);
    };

    const handleClick = (e) => {
        if (e.target.closest('#myPurchasesSearchClear')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            Utils.initPeriodFilter();
            document.addEventListener('input', handleInput);
            document.addEventListener('click', handleClick);
        }
    };
})();

export default MyPurchases;

const MyPurchases = (() => {
    let initialized = false;
    let debounceTimer = null;

    const submitSearch = (input) => {
        const { myPurchasesIndexUrl = '' } = window.pageConfig ?? {};
        const term = input.value.trim();

        const url = new URL(myPurchasesIndexUrl, window.location.origin);
        if (term !== '') url.searchParams.set('search', term);

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
            const { myPurchasesIndexUrl = '' } = window.pageConfig ?? {};
            window.location.href = myPurchasesIndexUrl;
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            document.addEventListener('input', handleInput);
            document.addEventListener('click', handleClick);
        }
    };
})();

export default MyPurchases;

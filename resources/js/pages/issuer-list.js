const IssuerList = (() => {
    let initialized = false;

    const filterIssuers = function () {
        const term = this.value.toLowerCase();
        let visibleCount = 0;

        document.querySelectorAll('#issuersTable tbody .issuer-row').forEach(row => {
            const name = row.querySelector('.issuer-name')?.textContent.toLowerCase() ?? '';
            const matches = name.includes(term);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        document.getElementById('issuerNoSearchResults')?.classList.toggle('hidden', visibleCount > 0);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            document.getElementById('issuerSearchInput')?.addEventListener('input', filterIssuers);
        }
    };
})();

export default IssuerList;

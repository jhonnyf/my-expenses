const IssuerDetail = (() => {
    let initialized = false;

    const filterInvoices = function () {
        const term = this.value.toLowerCase();

        document.querySelectorAll('.invoice-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            document.getElementById('invoiceSearchInput')?.addEventListener('input', filterInvoices);
        }
    };
})();

export default IssuerDetail;

const IssuerList = (() => {
    let initialized = false;

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            const form = document.getElementById('issuerFilterForm');
            if (!form) return;

            // A busca por texto só roda no botão "Buscar" (ou Enter); filtros e ordenação aplicam ao mudar.
            form.querySelectorAll('select, input[type="checkbox"]').forEach(el => {
                el.addEventListener('change', () => form.requestSubmit());
            });
        }
    };
})();

export default IssuerList;

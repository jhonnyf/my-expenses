import Utils from '../utils';

const MyPurchases = (() => {
    let initialized = false;

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            Utils.initPeriodFilter();

            const form = document.getElementById('myPurchasesFilterForm');
            if (!form) return;

            // A busca por texto só roda no botão "Buscar" (ou Enter); emissor, situação e ordenação aplicam ao mudar.
            form.querySelectorAll('select').forEach(el => {
                el.addEventListener('change', () => form.requestSubmit());
            });
        }
    };
})();

export default MyPurchases;

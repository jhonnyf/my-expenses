import axios from 'axios';

const Utils = (() => {
    const http = (url, { method = 'GET', body } = {}) => {
        return axios({ url, method, data: body }).then(r => r.data);
    };

    const formatCurrency = (value) => {
        return parseFloat(value).toFixed(2).replace('.', ',');
    };

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    const updateCategoryDot = (select) => {
        const dot = select.closest('.item-category-cell')?.querySelector('.category-dot');
        const color = select.options[select.selectedIndex]?.dataset.color;
        if (dot && color) dot.style.backgroundColor = color;
    };

    // Sincroniza um <select> KTSelect com um valor definido por fora (ex: reset de
    // formulário, ou o par desktop/mobile do mesmo item) — setar select.value
    // diretamente não atualiza o dropdown customizado do KTUI, só o <select>
    // nativo escondido por trás dele.
    const syncSelectValue = (select, value) => {
        const instance = window.KTSelect?.getInstance(select);
        if (instance) {
            instance.setSelectedOptions(value ? [value] : []);
            instance.updateSelectedOptionDisplay();
        } else {
            select.value = value;
        }
    };

    // Idem, mas para habilitar/desabilitar — select.disabled = true não bloqueia
    // o dropdown customizado do KTUI (só o <select> nativo escondido).
    const setSelectDisabled = (select, disabled) => {
        const instance = window.KTSelect?.getInstance(select);
        if (instance) {
            disabled ? instance.disable() : instance.enable();
        } else {
            select.disabled = disabled;
        }
    };

    const initCategoryAssignment = (assignCategoryUrl) => {
        document.addEventListener('change', (e) => {
            // KTUI redispara os eventos dos próprios componentes direto em `document`
            // (barramento de evento global) — target aí é o document, que não tem
            // .closest(). Sem essa guarda, esse redisparo (que não nos interessa,
            // já tratamos o 'change' nativo abaixo) derruba com exceção.
            if (!(e.target instanceof Element)) return;

            const select = e.target.closest('[data-action="assign-category"]');
            if (!select) return;

            const { itemId } = select.dataset;
            const categoryId = select.value;

            document.querySelectorAll(`[data-action="assign-category"][data-item-id="${itemId}"]`).forEach(s => {
                if (s !== select) syncSelectValue(s, categoryId);
                updateCategoryDot(s);
            });

            http(assignCategoryUrl, {
                method: 'POST',
                body: { item_id: itemId, category_id: categoryId || null },
            });
        });
    };

    const QUICK_PERIODS = {
        this_month: () => {
            const now = new Date();
            return [new Date(now.getFullYear(), now.getMonth(), 1), now];
        },
        last_month: () => {
            const now = new Date();
            return [new Date(now.getFullYear(), now.getMonth() - 1, 1), new Date(now.getFullYear(), now.getMonth(), 0)];
        },
        this_year: () => {
            const now = new Date();
            return [new Date(now.getFullYear(), 0, 1), now];
        },
        // "Tudo": usa uma data-sentinela bem anterior a qualquer NFC-e real
        // (eletrônica só existe desde ~2008) em vez de mudar a API do backend.
        all: () => [new Date(2000, 0, 1), new Date()],
    };

    const toISODate = (date) => date.toISOString().slice(0, 10);

    // Liga os botões "Este Mês / Mês Passado / Personalizado" a um form GET com
    // start_date/end_date — usado em qualquer página com filtro de período
    // (dashboard, categorias). Os ids/data-action são fixos de propósito para
    // não precisar duplicar esta função por página.
    const initPeriodFilter = () => {
        const form = document.getElementById('periodFilterForm');
        if (!form) return;

        const startInput = document.getElementById('periodStartDate');
        const endInput = document.getElementById('periodEndDate');

        document.querySelectorAll('[data-action="filter-period"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const { period } = btn.dataset;

                if (period === 'custom') {
                    document.getElementById('periodCustomRange').classList.remove('hidden');
                    startInput.focus();
                    return;
                }

                const [start, end] = QUICK_PERIODS[period]();
                startInput.value = toISODate(start);
                endInput.value = toISODate(end);
                form.submit();
            });
        });
    };

    return { http, formatCurrency, escapeHtml, initCategoryAssignment, initPeriodFilter, syncSelectValue, setSelectDisabled };
})();

export default Utils;

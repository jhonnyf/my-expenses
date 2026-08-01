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

    const initCategoryAssignment = (assignCategoryUrl) => {
        document.addEventListener('change', (e) => {
            const select = e.target.closest('[data-action="assign-category"]');
            if (!select) return;

            const { itemId } = select.dataset;
            const categoryId = select.value;

            document.querySelectorAll(`[data-action="assign-category"][data-item-id="${itemId}"]`).forEach(s => {
                if (s !== select) s.value = categoryId;
                updateCategoryDot(s);
            });

            http(assignCategoryUrl, {
                method: 'POST',
                body: { item_id: itemId, category_id: categoryId || null },
            });
        });
    };

    return { http, formatCurrency, escapeHtml, initCategoryAssignment };
})();

export default Utils;

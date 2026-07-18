import Utils from '../utils';

const InvoiceDetail = (() => {
    let initialized = false;
    let assignCategoryUrl;

    const assignCategory = (itemId, categoryId) => {
        Utils.http(assignCategoryUrl, {
            method: 'POST',
            body: { item_id: itemId, category_id: categoryId || null },
        });
    };

    const updateCategoryDot = (select) => {
        const dot = select.closest('.item-category-cell')?.querySelector('.category-dot');
        const color = select.options[select.selectedIndex]?.dataset.color;
        if (dot && color) dot.style.backgroundColor = color;
    };

    const handleChange = (e) => {
        const select = e.target.closest('[data-action="assign-category"]');
        if (!select) return;

        const { itemId } = select.dataset;
        const categoryId = select.value;

        document.querySelectorAll(`[data-action="assign-category"][data-item-id="${itemId}"]`).forEach(s => {
            if (s !== select) s.value = categoryId;
            updateCategoryDot(s);
        });

        assignCategory(itemId, categoryId);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ assignCategoryUrl } = window.pageConfig || {});

            document.addEventListener('change', handleChange);
        }
    };
})();

export default InvoiceDetail;

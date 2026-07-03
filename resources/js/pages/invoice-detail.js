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

    const handleChange = (e) => {
        const select = e.target.closest('[data-action="assign-category"]');
        if (select) assignCategory(select.dataset.itemId, select.value);
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

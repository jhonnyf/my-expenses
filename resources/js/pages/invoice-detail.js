import Utils from '../utils';

const InvoiceDetail = (() => {
    let initialized = false;

    const handleAliasUpdated = (e) => {
        const { description, display_name: displayName } = e.detail;

        document.querySelectorAll(`.item-alias-name[data-item-description="${CSS.escape(description)}"]`).forEach(el => {
            el.textContent = displayName;
        });
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            const { assignCategoryUrl } = window.pageConfig || {};

            Utils.initCategoryAssignment(assignCategoryUrl);
            document.addEventListener('product-alias:updated', handleAliasUpdated);
        }
    };
})();

export default InvoiceDetail;

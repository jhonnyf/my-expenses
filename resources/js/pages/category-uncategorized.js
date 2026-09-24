import Utils from '../utils';

const CategoryUncategorized = (() => {
    let initialized = false;

    // Item categorizado sai da lista de revisão: remove a linha, atualiza o contador e, ao esvaziar, mostra o estado vazio.
    const handleAssigned = ({ detail }) => {
        if (!detail.categoryId) return;

        document.querySelector(`[data-item-row="${detail.itemId}"]`)?.remove();

        const counter = document.getElementById('uncategorizedRemaining');
        if (counter) counter.textContent = Math.max(0, parseInt(counter.textContent.replace(/\D/g, ''), 10) - 1).toLocaleString('pt-BR');

        if (!document.querySelector('[data-item-row]')) {
            document.getElementById('uncategorizedList')?.classList.add('hidden');
            document.getElementById('uncategorizedEmpty')?.classList.remove('hidden');
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            const { assignCategoryUrl, suggestItemCategoryUrl } = window.pageConfig ?? {};

            Utils.initPeriodFilter();
            Utils.initCategoryAssignment(assignCategoryUrl);
            if (suggestItemCategoryUrl) Utils.initCategoryAiSuggestion(suggestItemCategoryUrl, assignCategoryUrl);

            document.addEventListener('item-category:assigned', handleAssigned);
        }
    };
})();

export default CategoryUncategorized;

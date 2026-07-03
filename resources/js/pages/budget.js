import Utils from '../utils';

const Budget = (() => {
    let initialized = false;
    let storeUrl, baseUrl;

    const saveBudget = () => {
        const categoryId = document.getElementById('budgetCategory').value || null;
        const amount = parseFloat(document.getElementById('budgetAmount').value);
        if (!amount || amount <= 0) return;

        Utils.http(storeUrl, {
            method: 'POST',
            body: { category_id: categoryId, amount },
        }).then(() => location.reload());
    };

    const deleteBudget = (id) => {
        if (!confirm('Deseja remover este orçamento?')) return;

        Utils.http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                const el = document.getElementById('budget-' + id);
                if (el) el.remove();
            });
    };

    const handleClick = (e) => {
        if (e.target.closest('[data-action="save-budget"]')) {
            saveBudget();
            return;
        }

        const deleteBtn = e.target.closest('[data-action="delete-budget"]');
        if (deleteBtn) {
            deleteBudget(deleteBtn.dataset.budgetId);
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ storeUrl, baseUrl } = window.pageConfig);

            document.addEventListener('click', handleClick);
        }
    };
})();

export default Budget;

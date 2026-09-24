import Utils from '../utils';

const Budget = (() => {
    let initialized = false;
    let storeUrl, baseUrl;
    let editingId = null;

    const { showFlash, reloadWithFlash, restoreFlash, errorMessage } = Utils;

    const categorySelect = () => document.getElementById('budgetCategory');
    const amountInput = () => document.getElementById('budgetAmount');
    const saveLabel = () => document.getElementById('btnSaveBudgetLabel');
    const cancelEditBtn = () => document.getElementById('btnCancelEditBudget');

    const setFormError = (message) => {
        const el = document.getElementById('budgetFormError');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('hidden', !message);
    };

    const setDeleteError = (message) => {
        const el = document.getElementById('deleteBudgetError');
        el.textContent = message || '';
        el.classList.toggle('hidden', !message);
    };

    const resetForm = () => {
        editingId = null;
        Utils.syncSelectValue(categorySelect(), '');
        Utils.setSelectDisabled(categorySelect(), false);
        amountInput().value = '';
        saveLabel().textContent = 'Salvar orçamento';
        cancelEditBtn().classList.add('hidden');
        setFormError('');
    };

    const editBudget = (id, categoryId, amount) => {
        editingId = id;
        Utils.syncSelectValue(categorySelect(), categoryId || '');
        Utils.setSelectDisabled(categorySelect(), true);
        amountInput().value = amount;
        amountInput().focus();
        saveLabel().textContent = 'Atualizar orçamento';
        cancelEditBtn().classList.remove('hidden');
        setFormError('');
        amountInput().scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    // Os cards trazem projeção, variação e totais calculados no servidor: recarrega em vez de remontar o card.
    const saveBudget = () => {
        setFormError('');

        const categoryId = categorySelect().value || null;
        const amount = parseFloat(amountInput().value);
        if (!amount || amount <= 0) return setFormError('Informe um limite maior que zero.');

        const updating = editingId !== null;

        Utils.http(storeUrl, { method: 'POST', body: { category_id: categoryId, amount } })
            .then(() => reloadWithFlash(updating ? 'Orçamento atualizado.' : 'Orçamento salvo.'))
            .catch(error => setFormError(errorMessage(error, 'Não foi possível salvar o orçamento.')));
    };

    const prepareDelete = (btn) => {
        document.getElementById('deleteBudgetModal').dataset.budgetId = btn.dataset.budgetId;
        document.getElementById('deleteBudgetName').textContent = btn.dataset.budgetName;
        setDeleteError('');
    };

    const confirmDelete = () => {
        const id = document.getElementById('deleteBudgetModal').dataset.budgetId;

        Utils.http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                window.KTModal?.getInstance(document.getElementById('deleteBudgetModal'))?.hide();
                reloadWithFlash('Orçamento excluído.');
            })
            .catch(error => setDeleteError(errorMessage(error, 'Não foi possível excluir o orçamento.')));
    };

    const ACTIONS = {
        'save-budget': () => saveBudget(),
        'cancel-edit-budget': () => resetForm(),
        'edit-budget': (btn) => editBudget(btn.dataset.budgetId, btn.dataset.budgetCategoryId, btn.dataset.budgetAmount),
        'prepare-delete-budget': (btn) => prepareDelete(btn),
        'confirm-delete-budget': () => confirmDelete(),
    };

    const handleClick = (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        ACTIONS[btn.dataset.action]?.(btn);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ storeUrl, baseUrl } = window.pageConfig);

            document.addEventListener('click', handleClick);
            restoreFlash();
        }
    };
})();

export default Budget;

import Utils from '../utils';

const Budget = (() => {
    let initialized = false;
    let storeUrl, baseUrl;
    let editingId = null;

    const resolveStatus = (percentage) => {
        if (percentage < 75) return { textStatus: 'text-green-600', accentColor: '#22c55e' };
        if (percentage < 100) return { textStatus: 'text-yellow-600', accentColor: '#eab308' };
        return { textStatus: 'text-destructive', accentColor: '#ef4444' };
    };

    const buildBudgetCardHtml = (budget) => {
        const pct = Math.min(budget.percentage, 100);
        const { textStatus, accentColor } = resolveStatus(budget.percentage);

        const headerHtml = budget.category
            ? `<span class="size-3 rounded-full shrink-0" style="background-color: ${budget.category.color || '#94A3B8'}"></span> ${Utils.escapeHtml(budget.category.name)}`
            : `<i class="ki-filled ki-wallet text-primary"></i> Geral`;

        const exceededHtml = budget.percentage >= 100 ? `
            <div class="bg-red-500/10 rounded-xl px-3 py-2 text-xs text-destructive flex items-center gap-1.5">
                <i class="ki-filled ki-information-2 shrink-0"></i>
                Orçamento excedido em R$ ${Utils.formatCurrency(budget.spent - budget.amount)}
            </div>` : '';

        return `
            <div class="kt-card transition-shadow hover:shadow-md" style="box-shadow: inset 0 3px 0 0 ${accentColor}" id="budget-${budget.id}">
                <div class="kt-card-header">
                    <h3 class="kt-card-title gap-2">${headerHtml}</h3>
                    <div class="kt-card-toolbar gap-1">
                        <button data-action="edit-budget"
                                data-budget-id="${budget.id}"
                                data-budget-category-id="${budget.category_id ?? ''}"
                                data-budget-amount="${budget.amount}"
                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Editar">
                            <i class="ki-filled ki-pencil text-muted-foreground"></i>
                        </button>
                        <button data-action="delete-budget" data-budget-id="${budget.id}" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Excluir">
                            <i class="ki-filled ki-trash text-muted-foreground"></i>
                        </button>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-secondary-foreground">Limite</span>
                            <span class="text-sm font-semibold font-mono text-foreground tabular-nums">R$ ${Utils.formatCurrency(budget.amount)}</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-secondary-foreground">Gasto</span>
                            <span class="text-sm font-semibold font-mono ${textStatus} tabular-nums">R$ ${Utils.formatCurrency(budget.spent)}</span>
                        </div>
                        <div class="kt-progress h-2">
                            <div class="kt-progress-indicator" style="width: ${pct}%; background-color: ${accentColor}"></div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs ${textStatus} font-medium tabular-nums">${Math.round(budget.percentage)}%</span>
                            <span class="text-xs text-secondary-foreground tabular-nums">
                                R$ ${Utils.formatCurrency(budget.spent)} / R$ ${Utils.formatCurrency(budget.amount)}
                            </span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-secondary-foreground">Restante</span>
                            <span class="text-sm font-semibold font-mono ${budget.remaining > 0 ? 'text-green-600' : 'text-destructive'} tabular-nums">
                                R$ ${Utils.formatCurrency(budget.remaining)}
                            </span>
                        </div>
                        ${exceededHtml}
                    </div>
                </div>
            </div>`;
    };

    const upsertBudgetCard = (budget) => {
        let grid = document.getElementById('budgetsGrid');
        if (grid.classList.contains('kt-card')) {
            grid.outerHTML = '<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" id="budgetsGrid"></div>';
            grid = document.getElementById('budgetsGrid');
        }

        const cardHtml = buildBudgetCardHtml(budget);
        const existing = document.getElementById(`budget-${budget.id}`);

        if (existing) {
            existing.outerHTML = cardHtml;
        } else {
            grid.insertAdjacentHTML('beforeend', cardHtml);
        }
    };

    const categorySelect = () => document.getElementById('budgetCategory');
    const amountInput = () => document.getElementById('budgetAmount');
    const saveLabel = () => document.getElementById('btnSaveBudgetLabel');
    const cancelEditBtn = () => document.getElementById('btnCancelEditBudget');

    const resetForm = () => {
        editingId = null;
        categorySelect().value = '';
        categorySelect().disabled = false;
        amountInput().value = '';
        saveLabel().textContent = 'Salvar Orçamento';
        cancelEditBtn().classList.add('hidden');
    };

    const editBudget = (id, categoryId, amount) => {
        editingId = id;
        categorySelect().value = categoryId || '';
        categorySelect().disabled = true;
        amountInput().value = amount;
        amountInput().focus();
        saveLabel().textContent = 'Atualizar Orçamento';
        cancelEditBtn().classList.remove('hidden');
        amountInput().scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const saveBudget = () => {
        const categoryId = categorySelect().value || null;
        const amount = parseFloat(amountInput().value);
        if (!amount || amount <= 0) return;

        Utils.http(storeUrl, {
            method: 'POST',
            body: { category_id: categoryId, amount },
        }).then(budget => {
            upsertBudgetCard(budget);
            resetForm();
        });
    };

    const deleteBudget = (id) => {
        if (!confirm('Deseja remover este orçamento?')) return;

        Utils.http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                const el = document.getElementById('budget-' + id);
                if (el) el.remove();
                if (String(editingId) === String(id)) resetForm();
            });
    };

    const handleClick = (e) => {
        if (e.target.closest('[data-action="save-budget"]')) {
            saveBudget();
            return;
        }

        if (e.target.closest('[data-action="cancel-edit-budget"]')) {
            resetForm();
            return;
        }

        const editBtn = e.target.closest('[data-action="edit-budget"]');
        if (editBtn) {
            editBudget(editBtn.dataset.budgetId, editBtn.dataset.budgetCategoryId || null, editBtn.dataset.budgetAmount);
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

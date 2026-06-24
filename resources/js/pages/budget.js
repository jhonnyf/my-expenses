import { http } from '../utils';

export default function init() {
    const { storeUrl, baseUrl } = window.pageConfig;

    window.saveBudget = () => {
        const categoryId = document.getElementById('budgetCategory').value || null;
        const amount = parseFloat(document.getElementById('budgetAmount').value);
        if (!amount || amount <= 0) return;

        http(storeUrl, {
            method: 'POST',
            body: { category_id: categoryId, amount },
        }).then(() => location.reload());
    };

    window.deleteBudget = (id) => {
        if (!confirm('Deseja remover este orçamento?')) return;

        http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                const el = document.getElementById('budget-' + id);
                if (el) el.remove();
            });
    };
}

export default function init() {
    var config = window.pageConfig;

    window.saveBudget = function () {
        var categoryId = document.getElementById('budgetCategory').value || null;
        var amount = parseFloat(document.getElementById('budgetAmount').value);
        if (!amount || amount <= 0) return;

        fetch(config.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ category_id: categoryId, amount: amount }),
        })
        .then(function (r) { return r.json(); })
        .then(function () { location.reload(); });
    };

    window.deleteBudget = function (id) {
        if (!confirm('Deseja remover este orçamento?')) return;
        fetch(config.baseUrl + '/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': config.csrfToken, 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            var el = document.getElementById('budget-' + id);
            if (el) el.remove();
        });
    };
}

import Utils from '../utils';

const RecurringPurchase = (() => {
    let initialized = false;
    let config;

    const post = (url, body, failure) => Utils.http(url, { method: 'POST', body })
        .catch(error => {
            Utils.showFlash(Utils.errorMessage(error, failure), 'error');
            return null;
        });

    const addToList = async (btn) => {
        const { listId, description, unitPrice, issuerId, unit, quantity } = btn.dataset;

        const data = await post(config.addToListUrl, {
            shopping_list_id: listId || null,
            description,
            unit_price: unitPrice || null,
            issuer_id: issuerId || null,
            unit: unit || null,
            quantity: quantity || 1,
        }, 'Não foi possível adicionar à lista.');

        if (data?.success) Utils.showFlash(`Adicionado a "${data.list_name}".`);
    };

    const toggleDismissal = async (btn, url, message) => {
        const data = await post(url, { description: btn.dataset.description }, 'Não foi possível atualizar o produto.');
        if (data?.success) Utils.reloadWithFlash(message);
    };

    const createReplenishmentList = async (btn) => {
        btn.disabled = true;
        const data = await post(btn.dataset.url, {}, 'Não foi possível criar a lista.');
        if (data?.success) {
            location.href = config.shoppingListUrl;
        } else {
            btn.disabled = false;
        }
    };

    const handleDocumentClick = (e) => {
        const target = e.target.closest('[data-action], #replenishment-btn');
        if (!target) return;

        if (target.id === 'replenishment-btn') return createReplenishmentList(target);

        const actions = {
            'add-to-list': () => addToList(target),
            dismiss: () => toggleDismissal(target, config.dismissUrl, 'Produto ocultado da lista.'),
            restore: () => toggleDismissal(target, config.restoreUrl, 'Produto de volta à lista.'),
        };
        actions[target.dataset.action]?.();
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            config = window.pageConfig;
            Utils.restoreFlash();
            document.addEventListener('click', handleDocumentClick);
        },
    };
})();

export default RecurringPurchase;

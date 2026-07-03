import Utils from '../utils';

const RecurringPurchase = (() => {
    let initialized = false;
    let addToListUrl;

    const addToList = (btn) => {
        const { listId, description, unitPrice, issuerId, unit } = btn.dataset;

        Utils.http(addToListUrl, {
            method: 'POST',
            body: {
                shopping_list_id: listId,
                description,
                unit_price: unitPrice,
                issuer_id: issuerId || null,
                unit: unit || null,
            },
        }).then(data => {
            if (!data.success) return;

            const check = document.createElement('span');
            check.className = 'text-green-500 text-sm';
            check.innerHTML = '<i class="ki-filled ki-check"></i>';
            btn.closest('td').appendChild(check);
        });
    };

    const handleDocumentClick = (e) => {
        const addToListBtn = e.target.closest('[data-action="add-to-list"]');
        if (addToListBtn) {
            addToList(addToListBtn);
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ addToListUrl } = window.pageConfig);

            document.addEventListener('click', handleDocumentClick);
        }
    };
})();

export default RecurringPurchase;

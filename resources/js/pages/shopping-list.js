import Utils from '../utils';

const ShoppingList = (() => {
    let initialized = false;
    let baseUrl, searchUrl;

    let currentListId = null;
    let shoppingItems = [];
    let debounceTimer = null;

    let searchInput, searchResults, resultsList, shoppingListContainer, btnNew, listNameCard;

    const fetchResults = (query) => {
        Utils.http(`${searchUrl}?q=${encodeURIComponent(query)}`)
            .then(data => {
                if (data.length === 0) {
                    resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
                } else {
                    resultsList.innerHTML = data.map((item, index) => {
                        const price = Utils.formatCurrency(item.unit_price);
                        const date = item.issued_at ? new Date(item.issued_at).toLocaleDateString('pt-BR') : '';
                        const isFav = item.is_favorite == 1;

                        return `
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors ${isFav ? 'bg-yellow-50 dark:bg-yellow-500/5' : ''}"
                                 data-add-item="${index}">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-foreground truncate">
                                        ${isFav ? '<i class="ki-filled ki-star text-yellow-500 text-xs me-1"></i>' : ''}${item.description}
                                    </p>
                                    <p class="text-xs text-secondary-foreground mt-0.5">
                                        <span class="font-medium">${item.issuer_name}</span>
                                        ${date ? `<span class="mx-1">&middot;</span> ${date}` : ''}
                                        ${item.unit ? `<span class="mx-1">&middot;</span> ${item.unit}` : ''}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 ms-4 shrink-0">
                                    <span class="font-semibold font-mono text-sm text-primary">R$ ${price}</span>
                                    <i class="ki-filled ki-plus-squared text-lg text-muted-foreground hover:text-primary"></i>
                                </div>
                            </div>`;
                    }).join('');

                    resultsList._lastData = data;
                }

                searchResults.classList.remove('hidden');
            });
    };

    const handleSearchInput = () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            resultsList.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => fetchResults(query), 300);
    };

    const ensureListExists = async () => {
        if (currentListId) return currentListId;

        const name = document.getElementById('listName').value.trim() || '';
        const data = await Utils.http(baseUrl, { method: 'POST', body: { name } });
        currentListId = data.id;
        addSavedListToSidebar(data.id, data.name, 0);
        return currentListId;
    };

    const addToList = async (item) => {
        await ensureListExists();

        const saved = await Utils.http(`${baseUrl}/${currentListId}/items`, {
            method: 'POST',
            body: {
                description: item.description,
                unit_price: parseFloat(item.unit_price),
                unit: item.unit,
                issuer_id: item.issuer_id,
                quantity: 1,
            },
        });

        shoppingItems.push({
            id: saved.id,
            description: saved.description,
            unit_price: parseFloat(saved.unit_price),
            unit: saved.unit,
            issuer_name: saved.issuer.name,
            issuer_id: saved.issuer_id,
            quantity: saved.quantity,
            purchased_at: null,
        });

        searchInput.value = '';
        searchResults.classList.add('hidden');
        renderList();
    };

    const handleResultsClick = (e) => {
        const row = e.target.closest('[data-add-item]');
        if (!row || !resultsList._lastData) return;

        const index = parseInt(row.dataset.addItem);
        const item = resultsList._lastData[index];
        if (item) addToList(item);
    };

    const removeItem = async (index) => {
        const item = shoppingItems[index];
        await Utils.http(`${baseUrl}/${currentListId}/items/${item.id}`, { method: 'DELETE' });
        shoppingItems.splice(index, 1);
        renderList();
    };

    const updateQuantity = async (index, delta) => {
        const item = shoppingItems[index];
        const newQty = Math.max(1, item.quantity + delta);
        await Utils.http(`${baseUrl}/${currentListId}/items/${item.id}`, {
            method: 'PATCH',
            body: { quantity: newQty },
        });
        shoppingItems[index].quantity = newQty;
        patchQuantityRow(index);
    };

    const patchQuantityRow = (index) => {
        const item = shoppingItems[index];
        const row = document.querySelector(`[data-item-row="${index}"]`);
        if (!row) {
            renderList();
            return;
        }

        const subtotal = item.unit_price * item.quantity;
        row.querySelector('[data-qty-display]').textContent = item.quantity;
        row.querySelector('[data-subtotal-display]').textContent = `R$ ${Utils.formatCurrency(subtotal)}`;

        const group = document.querySelector(`[data-group="u-${item.issuer_id}"]`);
        if (group) {
            const groupItems = shoppingItems.filter(i => i.issuer_id === item.issuer_id && !i.purchased_at);
            const groupTotal = groupItems.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
            group.querySelector('[data-group-summary]').innerHTML = `
                ${groupItems.length} ${groupItems.length === 1 ? 'item' : 'itens'}
                &middot; R$ ${Utils.formatCurrency(groupTotal)}`;
        }

        const total = shoppingItems.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
        document.getElementById('totalPrice').textContent = `R$ ${Utils.formatCurrency(total)}`;
    };

    const togglePurchased = async (index) => {
        const item = shoppingItems[index];
        const data = await Utils.http(`${baseUrl}/${currentListId}/items/${item.id}/toggle-purchased`, { method: 'POST' });
        shoppingItems[index].purchased_at = data.purchased_at;
        renderList();
    };

    const newList = () => {
        currentListId = null;
        shoppingItems = [];
        document.getElementById('listName').value = '';
        renderList();
        listNameCard.style.display = 'block';
        searchInput.focus();
    };

    const saveName = async () => {
        if (!currentListId) return;
        const name = document.getElementById('listName').value.trim();
        if (!name) return;

        await Utils.http(`${baseUrl}/${currentListId}`, { method: 'PATCH', body: { name } });
        const el = document.querySelector(`#saved-list-${currentListId} p:first-child`);
        if (el) el.textContent = name;
    };

    const buildItemRow = (item, isPurchased) => {
        const subtotal = item.unit_price * item.quantity;
        const checkedClass = isPurchased ? 'bg-green-500 border-green-500' : 'border-border';
        const textClass = isPurchased ? 'line-through text-secondary-foreground' : 'text-foreground';

        return `
            <div class="flex items-center justify-between py-2.5 px-4" data-item-row="${item._index}">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button data-toggle-purchased="${item._index}"
                            class="flex items-center justify-center size-5 rounded border ${checkedClass} shrink-0 transition-colors">
                        ${isPurchased ? '<i class="ki-filled ki-check text-white text-xs"></i>' : ''}
                    </button>
                    <div class="min-w-0">
                        <p class="text-sm font-medium ${textClass} truncate">${item.description}</p>
                        <p class="text-xs text-secondary-foreground">R$ ${Utils.formatCurrency(item.unit_price)} / ${item.unit || 'un'}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 ms-4 shrink-0">
                    ${!isPurchased ? `
                        <div class="flex items-center gap-1.5">
                            <button data-qty-delta="${item._index},-1"
                                    class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-6 rounded-md">
                                <i class="ki-filled ki-minus text-xs"></i>
                            </button>
                            <span class="text-sm font-medium w-8 text-center" data-qty-display>${item.quantity}</span>
                            <button data-qty-delta="${item._index},1"
                                    class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-6 rounded-md">
                                <i class="ki-filled ki-plus text-xs"></i>
                            </button>
                        </div>
                    ` : `<span class="text-sm text-secondary-foreground w-8 text-center">${item.quantity}</span>`}
                    <span class="font-semibold font-mono text-sm w-24 text-right ${isPurchased ? 'text-secondary-foreground' : ''}" data-subtotal-display>R$ ${Utils.formatCurrency(subtotal)}</span>
                    <button data-remove-item="${item._index}"
                            class="text-muted-foreground hover:text-destructive transition-colors">
                        <i class="ki-filled ki-trash text-sm"></i>
                    </button>
                </div>
            </div>`;
    };

    const renderGroupedItems = (items, isPurchased) => {
        const grouped = {};
        for (const item of items) {
            const group = grouped[item.issuer_id] ||= { name: item.issuer_name, items: [] };
            group.items.push(item);
        }

        return Object.values(grouped).map(group => {
            const groupTotal = group.items.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
            const rows = group.items.map(item => buildItemRow(item, isPurchased)).join('');

            const issuerId = group.items[0].issuer_id;

            return `
                <div class="kt-card ${isPurchased ? 'opacity-75' : ''}" data-group="${isPurchased ? 'p' : 'u'}-${issuerId}">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">
                            <i class="ki-filled ki-shop ${isPurchased ? 'text-green-500' : 'text-primary'} me-1"></i> ${group.name}
                        </h3>
                        <span class="text-xs text-secondary-foreground" data-group-summary>
                            ${group.items.length} ${group.items.length === 1 ? 'item' : 'itens'}
                            &middot; R$ ${Utils.formatCurrency(groupTotal)}
                        </span>
                    </div>
                    <div class="kt-card-content pb-3">
                        <div class="divide-y divide-border">${rows}</div>
                    </div>
                </div>`;
        }).join('');
    };

    const updateSidebarCount = () => {
        if (!currentListId) return;
        const el = document.querySelector(`#saved-list-${currentListId} .text-xs`);
        if (el) {
            const count = shoppingItems.length;
            const today = new Date().toLocaleDateString('pt-BR');
            el.textContent = `${count} ${count === 1 ? 'item' : 'itens'} · ${today}`;
        }
    };

    const renderList = () => {
        if (shoppingItems.length === 0) {
            shoppingListContainer.style.display = 'none';
            btnNew.style.display = currentListId ? 'inline-flex' : 'none';
            if (!currentListId) listNameCard.style.display = 'none';
            return;
        }

        shoppingListContainer.style.display = 'block';
        btnNew.style.display = 'inline-flex';
        listNameCard.style.display = 'block';

        const pending = [];
        const purchased = [];

        shoppingItems.forEach((item, idx) => {
            const entry = { ...item, _index: idx };
            (item.purchased_at ? purchased : pending).push(entry);
        });

        document.getElementById('pendingList').innerHTML = renderGroupedItems(pending, false);
        document.getElementById('emptyPending').style.display = pending.length === 0 ? 'block' : 'none';
        document.getElementById('totalPending').textContent = pending.length;

        const purchasedSection = document.getElementById('purchasedSection');
        if (purchased.length > 0) {
            purchasedSection.style.display = 'block';
            document.getElementById('purchasedList').innerHTML = renderGroupedItems(purchased, true);
            document.getElementById('totalPurchased').textContent = purchased.length;
        } else {
            purchasedSection.style.display = 'none';
        }

        const total = shoppingItems.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
        document.getElementById('totalPrice').textContent = 'R$ ' + Utils.formatCurrency(total);
        updateSidebarCount();
    };

    const handleListContainerClick = (e) => {
        const toggleBtn = e.target.closest('[data-toggle-purchased]');
        if (toggleBtn) {
            togglePurchased(parseInt(toggleBtn.dataset.togglePurchased));
            return;
        }

        const qtyBtn = e.target.closest('[data-qty-delta]');
        if (qtyBtn) {
            const [index, delta] = qtyBtn.dataset.qtyDelta.split(',').map(Number);
            updateQuantity(index, delta);
            return;
        }

        const removeBtn = e.target.closest('[data-remove-item]');
        if (removeBtn) {
            removeItem(parseInt(removeBtn.dataset.removeItem));
        }
    };

    const addSavedListToSidebar = (id, name, count) => {
        const noMsg = document.getElementById('noListsMsg');
        if (noMsg) noMsg.remove();

        const container = document.getElementById('savedLists');
        const today = new Date().toLocaleDateString('pt-BR');
        container.insertAdjacentHTML('afterbegin', `
            <div class="flex items-center justify-between py-2.5 px-1 group" id="saved-list-${id}">
                <button data-load-list="${id}" class="flex-1 text-left min-w-0">
                    <p class="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">${name}</p>
                    <p class="text-xs text-secondary-foreground">${count} ${count === 1 ? 'item' : 'itens'} &middot; ${today}</p>
                </button>
                <button data-delete-list="${id}" class="text-muted-foreground hover:text-destructive transition-colors ms-2 opacity-0 group-hover:opacity-100">
                    <i class="ki-filled ki-trash text-sm"></i>
                </button>
            </div>`);
    };

    const loadList = async (id) => {
        const data = await Utils.http(`${baseUrl}/${id}`);
        currentListId = data.id;
        document.getElementById('listName').value = data.name;
        shoppingItems = data.items.map(item => ({
            id: item.id,
            description: item.description,
            unit_price: parseFloat(item.unit_price),
            unit: item.unit,
            issuer_name: item.issuer.name,
            issuer_id: item.issuer_id,
            quantity: item.quantity,
            purchased_at: item.purchased_at,
        }));
        renderList();
    };

    const deleteList = async (id) => {
        if (!confirm('Deseja excluir esta lista?')) return;

        await Utils.http(`${baseUrl}/${id}`, { method: 'DELETE' });
        const el = document.getElementById(`saved-list-${id}`);
        if (el) el.remove();

        if (currentListId === id) {
            currentListId = null;
            shoppingItems = [];
            document.getElementById('listName').value = '';
            renderList();
        }
    };

    const handleSavedListsClick = (e) => {
        const loadBtn = e.target.closest('[data-load-list]');
        if (loadBtn) {
            loadList(parseInt(loadBtn.dataset.loadList));
            return;
        }

        const deleteBtn = e.target.closest('[data-delete-list]');
        if (deleteBtn) {
            deleteList(parseInt(deleteBtn.dataset.deleteList));
        }
    };

    const handleDocumentClick = (e) => {
        if (e.target.closest('[data-action="new-list"]')) {
            newList();
            return;
        }

        if (e.target.closest('[data-action="save-name"]')) {
            saveName();
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ baseUrl, searchUrl } = window.pageConfig);

            searchInput = document.getElementById('searchInput');
            searchResults = document.getElementById('searchResults');
            resultsList = document.getElementById('resultsList');
            shoppingListContainer = document.getElementById('shoppingListContainer');
            btnNew = document.getElementById('btnNew');
            listNameCard = document.getElementById('listNameCard');

            searchInput.addEventListener('input', handleSearchInput);
            resultsList.addEventListener('click', handleResultsClick);
            shoppingListContainer.addEventListener('click', handleListContainerClick);
            document.getElementById('savedLists').addEventListener('click', handleSavedListsClick);
            document.addEventListener('click', handleDocumentClick);
        }
    };
})();

export default ShoppingList;

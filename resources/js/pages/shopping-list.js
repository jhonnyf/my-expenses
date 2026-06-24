import { http, formatCurrency } from '../utils';

export default function init() {
    const { baseUrl, searchUrl } = window.pageConfig;

    let currentListId = null;
    let shoppingItems = [];
    let debounceTimer = null;

    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const resultsList = document.getElementById('resultsList');
    const shoppingListContainer = document.getElementById('shoppingListContainer');
    const btnNew = document.getElementById('btnNew');
    const listNameCard = document.getElementById('listNameCard');

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            resultsList.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => fetchResults(query), 300);
    });

    function fetchResults(query) {
        http(`${searchUrl}?q=${encodeURIComponent(query)}`)
            .then(data => {
                if (data.length === 0) {
                    resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
                } else {
                    resultsList.innerHTML = data.map((item, index) => {
                        const price = formatCurrency(item.unit_price);
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
    }

    resultsList.addEventListener('click', (e) => {
        const row = e.target.closest('[data-add-item]');
        if (!row || !resultsList._lastData) return;

        const index = parseInt(row.dataset.addItem);
        const item = resultsList._lastData[index];
        if (item) addToList(item);
    });

    async function ensureListExists() {
        if (currentListId) return currentListId;

        const name = document.getElementById('listName').value.trim() || '';
        const data = await http(baseUrl, { method: 'POST', body: { name } });
        currentListId = data.id;
        addSavedListToSidebar(data.id, data.name, 0);
        return currentListId;
    }

    async function addToList(item) {
        await ensureListExists();

        const saved = await http(`${baseUrl}/${currentListId}/items`, {
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
    }

    async function removeItem(index) {
        const item = shoppingItems[index];
        await http(`${baseUrl}/${currentListId}/items/${item.id}`, { method: 'DELETE' });
        shoppingItems.splice(index, 1);
        renderList();
    }

    async function updateQuantity(index, delta) {
        const item = shoppingItems[index];
        const newQty = Math.max(1, item.quantity + delta);
        await http(`${baseUrl}/${currentListId}/items/${item.id}`, {
            method: 'PATCH',
            body: { quantity: newQty },
        });
        shoppingItems[index].quantity = newQty;
        renderList();
    }

    async function togglePurchased(index) {
        const item = shoppingItems[index];
        const data = await http(`${baseUrl}/${currentListId}/items/${item.id}/toggle-purchased`, { method: 'POST' });
        shoppingItems[index].purchased_at = data.purchased_at;
        renderList();
    }

    function newList() {
        currentListId = null;
        shoppingItems = [];
        document.getElementById('listName').value = '';
        renderList();
        listNameCard.style.display = 'block';
        searchInput.focus();
    }

    async function saveName() {
        if (!currentListId) return;
        const name = document.getElementById('listName').value.trim();
        if (!name) return;

        await http(`${baseUrl}/${currentListId}`, { method: 'PATCH', body: { name } });
        const el = document.querySelector(`#saved-list-${currentListId} p:first-child`);
        if (el) el.textContent = name;
    }

    function renderList() {
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
        document.getElementById('totalPrice').textContent = 'R$ ' + formatCurrency(total);
        updateSidebarCount();
    }

    function renderGroupedItems(items, isPurchased) {
        const grouped = {};
        for (const item of items) {
            const group = grouped[item.issuer_id] ||= { name: item.issuer_name, items: [] };
            group.items.push(item);
        }

        return Object.values(grouped).map(group => {
            const groupTotal = group.items.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
            const rows = group.items.map(item => buildItemRow(item, isPurchased)).join('');

            return `
                <div class="kt-card ${isPurchased ? 'opacity-75' : ''}">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">
                            <i class="ki-filled ki-shop ${isPurchased ? 'text-green-500' : 'text-primary'} me-1"></i> ${group.name}
                        </h3>
                        <span class="text-xs text-secondary-foreground">
                            ${group.items.length} ${group.items.length === 1 ? 'item' : 'itens'}
                            &middot; R$ ${formatCurrency(groupTotal)}
                        </span>
                    </div>
                    <div class="kt-card-content pb-3">
                        <div class="divide-y divide-border">${rows}</div>
                    </div>
                </div>`;
        }).join('');
    }

    function buildItemRow(item, isPurchased) {
        const subtotal = item.unit_price * item.quantity;
        const checkedClass = isPurchased ? 'bg-green-500 border-green-500' : 'border-border';
        const textClass = isPurchased ? 'line-through text-secondary-foreground' : 'text-foreground';

        return `
            <div class="flex items-center justify-between py-2.5 px-4">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button data-toggle-purchased="${item._index}"
                            class="flex items-center justify-center size-5 rounded border ${checkedClass} shrink-0 transition-colors">
                        ${isPurchased ? '<i class="ki-filled ki-check text-white text-xs"></i>' : ''}
                    </button>
                    <div class="min-w-0">
                        <p class="text-sm font-medium ${textClass} truncate">${item.description}</p>
                        <p class="text-xs text-secondary-foreground">R$ ${formatCurrency(item.unit_price)} / ${item.unit || 'un'}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 ms-4 shrink-0">
                    ${!isPurchased ? `
                        <div class="flex items-center gap-1.5">
                            <button data-qty-delta="${item._index},-1"
                                    class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-6 rounded-md">
                                <i class="ki-filled ki-minus text-xs"></i>
                            </button>
                            <span class="text-sm font-medium w-8 text-center">${item.quantity}</span>
                            <button data-qty-delta="${item._index},1"
                                    class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-6 rounded-md">
                                <i class="ki-filled ki-plus text-xs"></i>
                            </button>
                        </div>
                    ` : `<span class="text-sm text-secondary-foreground w-8 text-center">${item.quantity}</span>`}
                    <span class="font-semibold font-mono text-sm w-24 text-right ${isPurchased ? 'text-secondary-foreground' : ''}">R$ ${formatCurrency(subtotal)}</span>
                    <button data-remove-item="${item._index}"
                            class="text-muted-foreground hover:text-destructive transition-colors">
                        <i class="ki-filled ki-trash text-sm"></i>
                    </button>
                </div>
            </div>`;
    }

    shoppingListContainer.addEventListener('click', (e) => {
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
    });

    function updateSidebarCount() {
        if (!currentListId) return;
        const el = document.querySelector(`#saved-list-${currentListId} .text-xs`);
        if (el) {
            const count = shoppingItems.length;
            const today = new Date().toLocaleDateString('pt-BR');
            el.textContent = `${count} ${count === 1 ? 'item' : 'itens'} · ${today}`;
        }
    }

    function addSavedListToSidebar(id, name, count) {
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
    }

    async function loadList(id) {
        const data = await http(`${baseUrl}/${id}`);
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
    }

    async function deleteList(id) {
        if (!confirm('Deseja excluir esta lista?')) return;

        await http(`${baseUrl}/${id}`, { method: 'DELETE' });
        const el = document.getElementById(`saved-list-${id}`);
        if (el) el.remove();

        if (currentListId === id) {
            currentListId = null;
            shoppingItems = [];
            document.getElementById('listName').value = '';
            renderList();
        }
    }

    document.getElementById('savedLists').addEventListener('click', (e) => {
        const loadBtn = e.target.closest('[data-load-list]');
        if (loadBtn) {
            loadList(parseInt(loadBtn.dataset.loadList));
            return;
        }

        const deleteBtn = e.target.closest('[data-delete-list]');
        if (deleteBtn) {
            deleteList(parseInt(deleteBtn.dataset.deleteList));
        }
    });

    window.newList = newList;
    window.saveName = saveName;
}

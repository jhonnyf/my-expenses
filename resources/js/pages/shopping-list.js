export default function init() {
    const BASE_URL = window.pageConfig.baseUrl;
    const CSRF_TOKEN = window.pageConfig.csrfToken;
    const SEARCH_URL = window.pageConfig.searchUrl;

    let currentListId = null;
    let shoppingItems = [];
    let searchTimeout = null;

    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const resultsList = document.getElementById('resultsList');
    const shoppingListContainer = document.getElementById('shoppingListContainer');
    const btnNew = document.getElementById('btnNew');
    const listNameCard = document.getElementById('listNameCard');

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        if (query.length < 2) {
            searchResults.classList.add('hidden');
            resultsList.innerHTML = '';
            return;
        }
        searchTimeout = setTimeout(() => fetchResults(query), 300);
    });

    function apiRequest(url, method, body) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
        };
        if (body) options.body = JSON.stringify(body);
        return fetch(url, options).then(r => r.json());
    }

    function fetchResults(query) {
        fetch(`${SEARCH_URL}?q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(data => {
                resultsList.innerHTML = '';
                if (data.length === 0) {
                    resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
                } else {
                    data.forEach(item => {
                        resultsList.innerHTML += buildResultItem(item);
                    });
                }
                searchResults.classList.remove('hidden');
            });
    }

    function buildResultItem(item) {
        const price = parseFloat(item.unit_price);
        const date = item.issued_at ? new Date(item.issued_at).toLocaleDateString('pt-BR') : '';
        const isFav = item.is_favorite == 1;
        const encoded = encodeURIComponent(JSON.stringify(item));
        return `
            <div class="flex items-center justify-between px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors ${isFav ? 'bg-yellow-50 dark:bg-yellow-500/5' : ''}"
                 onclick='window.addToList(decodeURIComponent("${encoded}"))'>
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
                    <span class="font-semibold font-mono text-sm text-primary">R$ ${price.toFixed(2).replace('.', ',')}</span>
                    <i class="ki-filled ki-plus-squared text-lg text-muted-foreground hover:text-primary"></i>
                </div>
            </div>`;
    }

    async function ensureListExists() {
        if (currentListId) return currentListId;

        const name = document.getElementById('listName').value.trim() || '';
        const data = await apiRequest(BASE_URL, 'POST', { name });
        currentListId = data.id;
        addSavedListToSidebar(data.id, data.name, 0);
        return currentListId;
    }

    async function addToList(json) {
        const item = JSON.parse(json);

        await ensureListExists();

        const saved = await apiRequest(`${BASE_URL}/${currentListId}/items`, 'POST', {
            description: item.description,
            unit_price: parseFloat(item.unit_price),
            unit: item.unit,
            issuer_id: item.issuer_id,
            quantity: 1,
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
        await apiRequest(`${BASE_URL}/${currentListId}/items/${item.id}`, 'DELETE');
        shoppingItems.splice(index, 1);
        renderList();
    }

    async function updateQuantity(index, delta) {
        const item = shoppingItems[index];
        const newQty = Math.max(1, item.quantity + delta);
        await apiRequest(`${BASE_URL}/${currentListId}/items/${item.id}`, 'PATCH', { quantity: newQty });
        shoppingItems[index].quantity = newQty;
        renderList();
    }

    async function togglePurchased(index) {
        const item = shoppingItems[index];
        const data = await apiRequest(`${BASE_URL}/${currentListId}/items/${item.id}/toggle-purchased`, 'POST');
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
        await apiRequest(`${BASE_URL}/${currentListId}`, 'PATCH', { name });
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
            if (item.purchased_at) {
                purchased.push(entry);
            } else {
                pending.push(entry);
            }
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

        let total = 0;
        shoppingItems.forEach(item => { total += item.unit_price * item.quantity; });
        document.getElementById('totalPrice').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
        updateSidebarCount();
    }

    function renderGroupedItems(items, isPurchased) {
        const grouped = {};
        items.forEach(item => {
            const key = item.issuer_id;
            if (!grouped[key]) {
                grouped[key] = { name: item.issuer_name, items: [] };
            }
            grouped[key].items.push(item);
        });

        let html = '';

        Object.keys(grouped).forEach(key => {
            const group = grouped[key];
            let groupTotal = 0;

            let rows = '';
            group.items.forEach(item => {
                const subtotal = item.unit_price * item.quantity;
                groupTotal += subtotal;
                rows += buildItemRow(item, isPurchased);
            });

            html += `
                <div class="kt-card ${isPurchased ? 'opacity-75' : ''}">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">
                            <i class="ki-filled ki-shop ${isPurchased ? 'text-green-500' : 'text-primary'} me-1"></i> ${group.name}
                        </h3>
                        <span class="text-xs text-secondary-foreground">
                            ${group.items.length} ${group.items.length === 1 ? 'item' : 'itens'}
                            &middot; R$ ${groupTotal.toFixed(2).replace('.', ',')}
                        </span>
                    </div>
                    <div class="kt-card-content pb-3">
                        <div class="divide-y divide-border">
                            ${rows}
                        </div>
                    </div>
                </div>`;
        });

        return html;
    }

    function buildItemRow(item, isPurchased) {
        const subtotal = item.unit_price * item.quantity;
        const checkedClass = isPurchased ? 'bg-green-500 border-green-500' : 'border-border';
        const textClass = isPurchased ? 'line-through text-secondary-foreground' : 'text-foreground';

        return `
            <div class="flex items-center justify-between py-2.5 px-4">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button onclick="window.togglePurchased(${item._index})"
                            class="flex items-center justify-center size-5 rounded border ${checkedClass} shrink-0 transition-colors">
                        ${isPurchased ? '<i class="ki-filled ki-check text-white text-xs"></i>' : ''}
                    </button>
                    <div class="min-w-0">
                        <p class="text-sm font-medium ${textClass} truncate">${item.description}</p>
                        <p class="text-xs text-secondary-foreground">R$ ${item.unit_price.toFixed(2).replace('.', ',')} / ${item.unit || 'un'}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 ms-4 shrink-0">
                    ${!isPurchased ? `
                        <div class="flex items-center gap-1.5">
                            <button onclick="window.updateQuantity(${item._index}, -1)"
                                    class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-6 rounded-md">
                                <i class="ki-filled ki-minus text-xs"></i>
                            </button>
                            <span class="text-sm font-medium w-8 text-center">${item.quantity}</span>
                            <button onclick="window.updateQuantity(${item._index}, 1)"
                                    class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-6 rounded-md">
                                <i class="ki-filled ki-plus text-xs"></i>
                            </button>
                        </div>
                    ` : `<span class="text-sm text-secondary-foreground w-8 text-center">${item.quantity}</span>`}
                    <span class="font-semibold font-mono text-sm w-24 text-right ${isPurchased ? 'text-secondary-foreground' : ''}">R$ ${subtotal.toFixed(2).replace('.', ',')}</span>
                    <button onclick="window.removeItem(${item._index})"
                            class="text-muted-foreground hover:text-destructive transition-colors">
                        <i class="ki-filled ki-trash text-sm"></i>
                    </button>
                </div>
            </div>`;
    }

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
        const html = `
            <div class="flex items-center justify-between py-2.5 px-1 group" id="saved-list-${id}">
                <button onclick="window.loadList(${id})" class="flex-1 text-left min-w-0">
                    <p class="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">${name}</p>
                    <p class="text-xs text-secondary-foreground">${count} ${count === 1 ? 'item' : 'itens'} &middot; ${today}</p>
                </button>
                <button onclick="window.deleteList(${id})" class="text-muted-foreground hover:text-destructive transition-colors ms-2 opacity-0 group-hover:opacity-100">
                    <i class="ki-filled ki-trash text-sm"></i>
                </button>
            </div>`;
        container.insertAdjacentHTML('afterbegin', html);
    }

    async function loadList(id) {
        const data = await apiRequest(`${BASE_URL}/${id}`, 'GET');
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

        await apiRequest(`${BASE_URL}/${id}`, 'DELETE');
        const el = document.getElementById(`saved-list-${id}`);
        if (el) el.remove();
        if (currentListId === id) {
            currentListId = null;
            shoppingItems = [];
            document.getElementById('listName').value = '';
            renderList();
        }
    }

    window.addToList = addToList;
    window.removeItem = removeItem;
    window.updateQuantity = updateQuantity;
    window.togglePurchased = togglePurchased;
    window.newList = newList;
    window.saveName = saveName;
    window.loadList = loadList;
    window.deleteList = deleteList;
}

import Utils from '../utils';

const ShoppingList = (() => {
    let initialized = false;
    let baseUrl, searchUrl;

    let currentListId = null;
    let shoppingItems = [];
    let debounceTimer = null;
    let locationOverride = null;

    let searchInput, searchResults, resultsList, shoppingListContainer, btnNew, listNameCard;
    let locationDefault, locationOverrideEl, locationOverrideLabel, locationModalCitySelect;

    const itemTotal = (item) => item.unit_price != null ? item.unit_price * item.quantity : 0;

    const handleLocationUpdated = (e) => {
        const { cidade, estado } = e.detail;

        locationDefault.innerHTML = `
            <i class="ki-filled ki-geolocation text-primary"></i>
            <span class="text-secondary-foreground">
                Comprando perto de <span class="font-semibold text-foreground">${Utils.escapeHtml(cidade)}/${Utils.escapeHtml(estado)}</span>
            </span>`;

        refreshCurrentSearch();
    };

    const buildSearchUrl = (query) => {
        const params = new URLSearchParams({ q: query });
        if (locationOverride) {
            params.set('city', locationOverride.city);
            params.set('state', locationOverride.state);
        }
        return `${searchUrl}?${params.toString()}`;
    };

    const buildGenericRow = (query) => `
        <div class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors border-t border-border"
             data-add-generic="${Utils.escapeHtml(query)}">
            <div class="flex items-center gap-2 min-w-0">
                <i class="ki-filled ki-note text-muted-foreground"></i>
                <span class="text-sm text-secondary-foreground truncate">
                    Adicionar <span class="font-medium text-foreground">"${Utils.escapeHtml(query)}"</span> como lembrete, sem preço
                </span>
            </div>
            <i class="ki-filled ki-plus-squared text-lg text-muted-foreground"></i>
        </div>`;

    const fetchResults = (query) => {
        Utils.http(buildSearchUrl(query))
            .then(data => {
                if (data.length === 0) {
                    resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>' + buildGenericRow(query);
                } else {
                    resultsList.innerHTML = data.map((item, index) => {
                        const price = Utils.formatCurrency(item.unit_price);
                        const date = item.issued_at ? new Date(item.issued_at).toLocaleDateString('pt-BR') : '';
                        const isFav = item.is_favorite == 1;
                        const isOwn = item.is_own == 1;
                        const description = Utils.escapeHtml(item.description);
                        const displayDescription = Utils.escapeHtml(item.display_description || item.description);
                        const officialDescription = item.official_description ? Utils.escapeHtml(item.official_description) : null;
                        const issuerName = Utils.escapeHtml(item.issuer_name);

                        return `
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-3 px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors ${isFav ? 'bg-yellow-50 dark:bg-yellow-500/5' : ''}"
                                 data-add-item="${index}">
                                <div class="w-full sm:flex-1 sm:min-w-0">
                                    <p class="text-sm font-medium text-foreground truncate">
                                        ${isFav ? '<i class="ki-filled ki-star text-yellow-500 text-xs me-1"></i>' : ''}${displayDescription}
                                    </p>
                                    ${officialDescription ? `<p class="text-xs text-secondary-foreground truncate">${officialDescription}</p>` : ''}
                                    <p class="text-xs text-secondary-foreground mt-0.5 flex items-center flex-wrap gap-x-1">
                                        <span class="font-medium">${issuerName}</span>
                                        ${date ? `<span class="mx-1">&middot;</span> ${date}` : ''}
                                        ${item.unit ? `<span class="mx-1">&middot;</span> ${item.unit}` : ''}
                                        ${!isOwn ? '<span class="kt-badge kt-badge-outline kt-badge-sm shrink-0">Comunidade</span>' : ''}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="font-semibold font-mono text-sm text-primary">R$ ${price}</span>
                                    <button type="button" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm"
                                            data-action="favorite-product" data-description="${description}" data-unit="${item.unit || ''}"
                                            title="Avisar quando o preço cair">
                                        <i class="ki-filled ki-heart text-base text-muted-foreground"></i>
                                    </button>
                                    <i class="ki-filled ki-plus-squared text-lg text-muted-foreground hover:text-primary"></i>
                                </div>
                            </div>`;
                    }).join('') + buildGenericRow(query);

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

    const refreshCurrentSearch = () => {
        const query = searchInput.value.trim();
        if (query.length >= 2) fetchResults(query);
    };

    const applyLocationOverride = () => {
        const value = locationModalCitySelect.value;
        if (!value) return;

        const [city, state] = value.split('|');
        locationOverride = { city, state };

        locationDefault.classList.add('hidden');
        locationOverrideEl.classList.remove('hidden');
        locationOverrideLabel.textContent = `${city}/${state}`;

        refreshCurrentSearch();
    };

    const clearLocationOverride = () => {
        locationOverride = null;

        locationOverrideEl.classList.add('hidden');
        locationDefault.classList.remove('hidden');

        refreshCurrentSearch();
    };

    const ensureListExists = async () => {
        if (currentListId) return currentListId;

        const name = document.getElementById('listName').value.trim() || '';
        const data = await Utils.http(baseUrl, { method: 'POST', body: { name } });
        currentListId = data.id;
        addSavedListToSidebar(data.id, data.name, 0);
        return currentListId;
    };

    const mapIssuerAddress = (issuer) => ({
        street: issuer.street,
        street_number: issuer.street_number,
        neighborhood: issuer.neighborhood,
        city: issuer.city,
        state: issuer.state,
        zip_code: issuer.zip_code,
    });

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
            issuer_name: saved.issuer.display_name,
            issuer_id: saved.issuer_id,
            ...mapIssuerAddress(saved.issuer),
            quantity: saved.quantity,
            purchased_at: null,
        });

        searchInput.value = '';
        searchResults.classList.add('hidden');
        renderList();
    };

    const addGenericToList = async (description) => {
        await ensureListExists();

        const saved = await Utils.http(`${baseUrl}/${currentListId}/items`, {
            method: 'POST',
            body: { description, quantity: 1 },
        });

        shoppingItems.push({
            id: saved.id,
            description: saved.description,
            unit_price: null,
            unit: saved.unit,
            issuer_name: 'Sem mercado definido',
            issuer_id: null,
            quantity: saved.quantity,
            purchased_at: null,
        });

        searchInput.value = '';
        searchResults.classList.add('hidden');
        renderList();
    };

    const handleResultsClick = (e) => {
        // O botão de favoritar produto é tratado globalmente por
        // Utils.initFavoriteProduct() via data-action="favorite-product" — aqui só
        // ignoramos o clique nele pra não também disparar o "adicionar à lista".
        if (e.target.closest('[data-action="favorite-product"]')) return;

        const genericRow = e.target.closest('[data-add-generic]');
        if (genericRow) {
            addGenericToList(genericRow.dataset.addGeneric);
            return;
        }

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

        const subtotal = itemTotal(item);
        row.querySelector('[data-qty-display]').textContent = item.quantity;
        row.querySelector('[data-subtotal-display]').textContent = item.unit_price != null ? `R$ ${Utils.formatCurrency(subtotal)}` : '—';

        const group = document.querySelector(`[data-group="u-${item.issuer_id}"]`);
        if (group) {
            const groupItems = shoppingItems.filter(i => i.issuer_id === item.issuer_id && !i.purchased_at);
            const groupTotal = groupItems.reduce((sum, i) => sum + itemTotal(i), 0);
            group.querySelector('[data-group-summary]').innerHTML = `
                ${groupItems.length} ${groupItems.length === 1 ? 'item' : 'itens'}
                ${item.issuer_id != null ? `&middot; R$ ${Utils.formatCurrency(groupTotal)}` : ''}`;
        }

        const total = shoppingItems.reduce((sum, i) => sum + itemTotal(i), 0);
        document.getElementById('totalPrice').textContent = `R$ ${Utils.formatCurrency(total)}`;
        updateSidebarCount();
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
        const hasPrice = item.unit_price != null;
        const subtotal = itemTotal(item);
        const checkedClass = isPurchased ? 'bg-green-500 border-green-500' : 'border-border';
        const textClass = isPurchased ? 'line-through text-secondary-foreground' : 'text-foreground';

        return `
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 py-2.5 px-4 transition-colors hover:bg-accent/30" data-item-row="${item._index}">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button data-toggle-purchased="${item._index}"
                            class="flex items-center justify-center size-5 rounded border ${checkedClass} shrink-0 transition-colors">
                        ${isPurchased ? '<i class="ki-filled ki-check text-white text-xs"></i>' : ''}
                    </button>
                    <div class="min-w-0">
                        <p class="text-sm font-medium ${textClass} truncate">${Utils.escapeHtml(item.description)}</p>
                        <p class="text-xs text-secondary-foreground">${hasPrice ? `R$ ${Utils.formatCurrency(item.unit_price)} / ${item.unit || 'un'}` : 'Sem preço definido'}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between sm:justify-end gap-3 ps-8 sm:ps-0 sm:ms-4 shrink-0">
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
                    <span class="font-semibold font-mono text-sm w-24 text-right ${isPurchased ? 'text-secondary-foreground' : ''}" data-subtotal-display>${hasPrice ? `R$ ${Utils.formatCurrency(subtotal)}` : '—'}</span>
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
            const groupTotal = group.items.reduce((sum, item) => sum + itemTotal(item), 0);
            const rows = group.items.map(item => buildItemRow(item, isPurchased)).join('');

            const issuerId = group.items[0].issuer_id;
            const isGeneric = issuerId == null;

            return `
                <div class="kt-card ${isPurchased ? 'opacity-75' : ''}" data-group="${isPurchased ? 'p' : 'u'}-${issuerId}">
                    <div class="kt-card-header gap-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="kt-card-title truncate">
                                <i class="ki-filled ${isGeneric ? 'ki-note-2 text-muted-foreground' : `ki-shop ${isPurchased ? 'text-green-500' : 'text-primary'}`} me-1"></i> ${Utils.escapeHtml(group.name)}
                            </h3>
                            <p class="text-xs text-secondary-foreground mt-0.5" data-group-summary>
                                ${group.items.length} ${group.items.length === 1 ? 'item' : 'itens'}
                                ${!isGeneric ? `&middot; R$ ${Utils.formatCurrency(groupTotal)}` : ''}
                            </p>
                        </div>
                        ${!isGeneric ? `
                        <button type="button"
                                class="kt-btn kt-btn-sm kt-btn-outline shrink-0"
                                data-directions="${issuerId}"
                                data-kt-modal-toggle="#directionsModal">
                            <i class="ki-filled ki-geolocation"></i> Como chegar
                        </button>` : ''}
                    </div>
                    <div class="kt-card-content pb-3">
                        <div class="divide-y divide-border">${rows}</div>
                    </div>
                </div>`;
        }).join('');
    };

    const buildAddressParts = (item) => [
        item.street ? [item.street, item.street_number].filter(Boolean).join(', ') : null,
        item.neighborhood,
        item.city,
        item.state,
        item.zip_code,
    ].filter(Boolean);

    const openDirectionsModal = (issuerId) => {
        const item = shoppingItems.find(i => i.issuer_id === issuerId);
        if (!item) return;

        document.getElementById('directionsModalTitle').textContent = item.issuer_name;

        const parts = buildAddressParts(item);
        const hasAddress = parts.length > 0;

        document.getElementById('directionsModalMapWrapper').classList.toggle('hidden', !hasAddress);
        document.getElementById('directionsModalOpenLink').classList.toggle('hidden', !hasAddress);
        document.getElementById('directionsModalWazeLink').classList.toggle('hidden', !hasAddress);
        document.getElementById('directionsModalEmpty').classList.toggle('hidden', hasAddress);
        document.getElementById('directionsModalAddress').textContent = hasAddress ? parts.join(' — ') : '';

        if (hasAddress) {
            const query = encodeURIComponent([...parts, 'Brasil'].join(', '));
            document.getElementById('directionsModalMap').src = `https://maps.google.com/maps?q=${query}&output=embed&z=16&hl=pt-BR`;
            document.getElementById('directionsModalOpenLink').href = `https://www.google.com/maps/dir/?api=1&destination=${query}`;
            document.getElementById('directionsModalWazeLink').href = `https://waze.com/ul?q=${query}&navigate=yes`;
        }
    };

    const updateSidebarCount = () => {
        if (!currentListId) return;
        const el = document.querySelector(`#saved-list-${currentListId} .text-xs`);
        if (el) {
            const count = shoppingItems.length;
            const total = shoppingItems.reduce((sum, i) => sum + itemTotal(i), 0);
            const today = new Date().toLocaleDateString('pt-BR');
            el.textContent = `${count} ${count === 1 ? 'item' : 'itens'} · R$ ${Utils.formatCurrency(total)} · ${today}`;
        }
    };

    const updateSummaryProgress = (pendingCount, purchasedCount) => {
        const total = pendingCount + purchasedCount;
        const pct = total > 0 ? Math.round((purchasedCount / total) * 100) : 0;

        document.getElementById('summaryProgress').style.width = `${pct}%`;
        document.getElementById('summaryProgressLabel').textContent = `${purchasedCount} de ${total} ${total === 1 ? 'item comprado' : 'itens comprados'}`;
        document.getElementById('summaryProgressPct').textContent = `${pct}%`;
    };

    const renderList = () => {
        if (shoppingItems.length === 0) {
            shoppingListContainer.style.display = 'none';
            btnNew.style.display = currentListId ? 'inline-flex' : 'none';
            if (!currentListId) listNameCard.style.display = 'none';
            return;
        }

        shoppingListContainer.style.display = 'flex';
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

        const total = shoppingItems.reduce((sum, item) => sum + itemTotal(item), 0);
        document.getElementById('totalPrice').textContent = 'R$ ' + Utils.formatCurrency(total);
        updateSummaryProgress(pending.length, purchased.length);
        updateSidebarCount();
    };

    const handleListContainerClick = (e) => {
        const directionsBtn = e.target.closest('[data-directions]');
        if (directionsBtn) {
            openDirectionsModal(parseInt(directionsBtn.dataset.directions));
            return;
        }

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
            <div class="flex items-center gap-3 py-3 px-1 group rounded-lg transition-colors hover:bg-accent/60" id="saved-list-${id}">
                <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary shrink-0">
                    <i class="ki-filled ki-basket text-sm"></i>
                </div>
                <button data-load-list="${id}" class="flex-1 text-left min-w-0">
                    <p class="text-sm font-semibold text-foreground truncate group-hover:text-primary transition-colors">${Utils.escapeHtml(name)}</p>
                    <p class="text-xs text-secondary-foreground">${count} ${count === 1 ? 'item' : 'itens'} &middot; R$ 0,00 &middot; ${today}</p>
                </button>
                <button data-delete-list="${id}" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive shrink-0">
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
            unit_price: item.unit_price != null ? parseFloat(item.unit_price) : null,
            unit: item.unit,
            issuer_name: item.issuer ? item.issuer.display_name : 'Sem mercado definido',
            issuer_id: item.issuer_id,
            ...mapIssuerAddress(item.issuer || {}),
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
            locationDefault = document.getElementById('locationDefault');
            locationOverrideEl = document.getElementById('locationOverride');
            locationOverrideLabel = document.getElementById('locationOverrideLabel');
            locationModalCitySelect = document.getElementById('locationModalCitySelect');

            searchInput.addEventListener('input', handleSearchInput);
            resultsList.addEventListener('click', handleResultsClick);
            shoppingListContainer.addEventListener('click', handleListContainerClick);
            document.getElementById('savedLists').addEventListener('click', handleSavedListsClick);
            document.getElementById('btnApplyLocationOverride').addEventListener('click', applyLocationOverride);
            document.getElementById('btnClearLocationOverride').addEventListener('click', clearLocationOverride);
            document.addEventListener('location:updated', handleLocationUpdated);
            document.addEventListener('click', handleDocumentClick);
        }
    };
})();

export default ShoppingList;

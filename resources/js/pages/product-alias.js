import Utils from '../utils';

const ProductAlias = (() => {
    let initialized = false;
    let storeUrl, mergeUrl, dismissUrl, suggestionsUrl, aiSuggestUrl, communitySuggestionsUrl;

    const modalEl = () => document.getElementById('productAliasModal');

    const hideModal = () => {
        const el = modalEl();
        if (el && window.KTModal) {
            window.KTModal.getInstance(el)?.hide();
        }
    };

    const showError = (message) => {
        const el = document.getElementById('productAliasModalError');
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('hidden', !message);
    };

    const openModal = (btn) => {
        const modal = modalEl();
        if (!modal) return;

        modal.dataset.description = btn.dataset.description;

        if (btn.dataset.descriptions) {
            modal.dataset.descriptions = btn.dataset.descriptions;
        } else {
            delete modal.dataset.descriptions;
        }

        document.getElementById('productAliasModalOriginalName').textContent = btn.dataset.description;
        document.getElementById('productAliasModalInput').value = btn.dataset.canonicalName || '';
        showError('');
    };

    const dispatchUpdated = (detail) => {
        document.dispatchEvent(new CustomEvent('product-alias:updated', { detail }));
    };

    const saveAlias = async () => {
        const modal = modalEl();
        const description = modal?.dataset.description;
        if (!description) {
            showError('Não foi possível identificar o produto. Feche o modal e tente novamente.');
            return;
        }

        const input = document.getElementById('productAliasModalInput');
        const canonicalName = input.value.trim();
        const saveBtn = document.getElementById('productAliasModalSave');
        const groupedDescriptions = modal.dataset.descriptions ? JSON.parse(modal.dataset.descriptions) : null;

        if (groupedDescriptions && !canonicalName) {
            showError('Informe um nome para o produto.');
            return;
        }

        saveBtn.disabled = true;
        showError('');

        try {
            let result;

            if (groupedDescriptions) {
                await Utils.http(mergeUrl, {
                    method: 'POST',
                    body: { canonical_name: canonicalName, descriptions: groupedDescriptions },
                });
                result = { description, canonical_name: canonicalName, display_name: canonicalName };
            } else {
                result = await Utils.http(storeUrl, {
                    method: 'POST',
                    body: { description, canonical_name: canonicalName },
                });
            }

            document.querySelectorAll(`[data-action="edit-product-alias"][data-description="${CSS.escape(description)}"]`).forEach(btn => {
                btn.dataset.canonicalName = result.canonical_name || '';
            });

            dispatchUpdated(result);
            hideModal();
        } catch (error) {
            const message = error?.response?.data?.errors?.canonical_name?.[0]
                ?? error?.response?.data?.message
                ?? 'Erro ao salvar nome do produto. Tente novamente.';
            showError(message);
        } finally {
            saveBtn.disabled = false;
        }
    };

    const requestAiSuggestion = (descriptions) =>
        Utils.http(aiSuggestUrl, { method: 'POST', body: { descriptions } });

    const setButtonLoading = (btn, isLoading) => {
        if (isLoading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Pensando...';
        } else {
            btn.disabled = false;
            if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
        }
    };

    const aiSuggestForModal = async (btn) => {
        const modal = modalEl();
        const description = modal?.dataset.description;
        if (!description) return;

        const grouped = modal.dataset.descriptions ? JSON.parse(modal.dataset.descriptions) : null;
        const descriptions = grouped?.length ? grouped : [description];

        setButtonLoading(btn, true);
        showError('');

        try {
            const result = await requestAiSuggestion(descriptions);
            if (result.confident && result.suggested_name) {
                document.getElementById('productAliasModalInput').value = result.suggested_name;
            } else {
                showError('Nenhuma sugestão automática disponível para este produto. Preencha manualmente.');
            }
        } catch (error) {
            showError('Não foi possível obter sugestão agora. Preencha manualmente.');
        } finally {
            setButtonLoading(btn, false);
        }
    };

    const showSuggestionRowHint = (row, message) => {
        let hint = row.querySelector('[data-suggestion-hint]');
        if (!hint) {
            hint = document.createElement('p');
            hint.dataset.suggestionHint = '';
            hint.className = 'text-xs text-secondary-foreground';
            row.querySelector('.flex.items-center.gap-2')?.after(hint);
        }
        hint.textContent = message;
    };

    const aiSuggestForSuggestionRow = async (row, btn) => {
        const { descriptionA, descriptionB } = row.dataset;
        const input = row.querySelector('[data-suggestion-name]');

        setButtonLoading(btn, true);

        try {
            const result = await requestAiSuggestion([descriptionA, descriptionB]);
            if (result.confident && result.suggested_name) {
                input.value = result.suggested_name;
                row.querySelector('[data-suggestion-hint]')?.remove();
            } else {
                showSuggestionRowHint(row, 'Nenhuma sugestão automática disponível — revise manualmente.');
            }
        } catch (error) {
            showSuggestionRowHint(row, 'Sugestão indisponível no momento.');
        } finally {
            setButtonLoading(btn, false);
        }
    };

    const renderSuggestions = (suggestions) => {
        const card = document.getElementById('suggestionsCard');
        const list = document.getElementById('suggestionsList');
        const count = document.getElementById('suggestionsCount');
        if (!card || !list) return;

        if (suggestions.length === 0) {
            if (card.dataset.showEmptyState) {
                card.style.display = '';
                count.textContent = '';
                list.innerHTML = '<p class="text-sm text-secondary-foreground py-6 text-center">Nenhuma sugestão pendente no momento.</p>';
            } else {
                card.style.display = 'none';
            }

            return;
        }

        card.style.display = '';
        count.textContent = `${suggestions.length} ${suggestions.length === 1 ? 'sugestão' : 'sugestões'}`;

        list.innerHTML = suggestions.map(s => `
            <div class="flex flex-col gap-2 py-3" data-suggestion
                 data-description-a="${Utils.escapeHtml(s.description_a)}"
                 data-description-b="${Utils.escapeHtml(s.description_b)}">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="text-sm min-w-0">
                        <span class="font-medium text-foreground">${Utils.escapeHtml(s.description_a)}</span>
                        <span class="text-secondary-foreground"> parece igual a </span>
                        <span class="font-medium text-foreground">${Utils.escapeHtml(s.description_b)}</span>
                    </div>
                    <span class="kt-badge kt-badge-outline kt-badge-sm shrink-0">${s.score}% parecido</span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" class="kt-input kt-input-sm flex-1" value="${Utils.escapeHtml(s.description_a)}" data-suggestion-name />
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline shrink-0" data-suggestion-action="ai-suggest" title="Sugerir nome automaticamente">
                        <i class="ki-filled ki-artificial-intelligence"></i>
                    </button>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-primary shrink-0" data-suggestion-action="merge">Unificar</button>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline shrink-0" data-suggestion-action="dismiss">Ignorar</button>
                </div>
            </div>`).join('');
    };

    const renderCommunitySuggestions = (suggestions) => {
        const card = document.getElementById('communitySuggestionsCard');
        const list = document.getElementById('communitySuggestionsList');
        const count = document.getElementById('communitySuggestionsCount');
        if (!card || !list) return;

        if (suggestions.length === 0) {
            card.style.display = 'none';
            return;
        }

        card.style.display = '';
        count.textContent = `${suggestions.length} ${suggestions.length === 1 ? 'sugestão' : 'sugestões'}`;

        list.innerHTML = suggestions.map(s => `
            <div class="flex items-center justify-between gap-3 py-3" data-community-suggestion
                 data-description="${Utils.escapeHtml(s.description)}"
                 data-canonical-name="${Utils.escapeHtml(s.canonical_name)}">
                <div class="text-sm min-w-0">
                    <span class="text-secondary-foreground">${Utils.escapeHtml(s.description)}</span>
                    <span class="text-secondary-foreground"> &rarr; </span>
                    <span class="font-medium text-foreground">${Utils.escapeHtml(s.canonical_name)}</span>
                </div>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-primary shrink-0" data-community-action="use">Usar</button>
            </div>`).join('');
    };

    const fetchSuggestions = () => {
        if (!suggestionsUrl || !document.getElementById('suggestionsList')) return;

        Utils.http(suggestionsUrl).then(renderSuggestions);
    };

    const fetchCommunitySuggestions = () => {
        if (!communitySuggestionsUrl || !document.getElementById('communitySuggestionsList')) return;

        Utils.http(communitySuggestionsUrl).then(renderCommunitySuggestions);
    };

    const mergeSuggestion = async (row) => {
        const { descriptionA, descriptionB } = row.dataset;
        const canonicalName = row.querySelector('[data-suggestion-name]').value.trim();

        if (!canonicalName) return;

        await Utils.http(mergeUrl, {
            method: 'POST',
            body: { canonical_name: canonicalName, descriptions: [descriptionA, descriptionB] },
        });

        dispatchUpdated({ description: descriptionA, canonical_name: canonicalName, display_name: canonicalName });
        dispatchUpdated({ description: descriptionB, canonical_name: canonicalName, display_name: canonicalName });
        row.remove();
    };

    const dismissSuggestion = async (row) => {
        const { descriptionA, descriptionB } = row.dataset;

        await Utils.http(dismissUrl, {
            method: 'POST',
            body: { description_a: descriptionA, description_b: descriptionB },
        });

        row.remove();
    };

    const useCommunitySuggestion = async (row) => {
        const { description, canonicalName } = row.dataset;

        const result = await Utils.http(storeUrl, {
            method: 'POST',
            body: { description, canonical_name: canonicalName },
        });

        dispatchUpdated(result);
        row.remove();
    };

    const handleClick = (e) => {
        const editTrigger = e.target.closest('[data-action="edit-product-alias"]');
        if (editTrigger) {
            openModal(editTrigger);
            return;
        }

        if (e.target.closest('#productAliasModalSave')) {
            saveAlias();
            return;
        }

        if (e.target.closest('#productAliasModalAiSuggest')) {
            aiSuggestForModal(e.target.closest('button'));
            return;
        }

        const communityAction = e.target.closest('[data-community-action="use"]');
        if (communityAction) {
            const row = communityAction.closest('[data-community-suggestion]');
            if (row) useCommunitySuggestion(row);
            return;
        }

        const suggestionAction = e.target.closest('[data-suggestion-action]');
        if (suggestionAction) {
            const row = suggestionAction.closest('[data-suggestion]');
            if (!row) return;

            if (suggestionAction.dataset.suggestionAction === 'merge') {
                mergeSuggestion(row);
            } else if (suggestionAction.dataset.suggestionAction === 'dismiss') {
                dismissSuggestion(row);
            } else if (suggestionAction.dataset.suggestionAction === 'ai-suggest') {
                aiSuggestForSuggestionRow(row, suggestionAction);
            }
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({
                productAliasStoreUrl: storeUrl,
                productAliasMergeUrl: mergeUrl,
                productAliasDismissUrl: dismissUrl,
                productAliasSuggestionsUrl: suggestionsUrl,
                productAliasAiSuggestUrl: aiSuggestUrl,
                productAliasCommunitySuggestionsUrl: communitySuggestionsUrl,
            } = window.pageConfig || {});

            // Fase de captura: o KTUI trata cliques em [data-kt-modal-toggle] em
            // document.body na fase de bubble e chama stopPropagation(), o que
            // impediria este listener de rodar antes do modal abrir.
            document.addEventListener('click', handleClick, true);

            fetchSuggestions();
            fetchCommunitySuggestions();
        }
    };
})();

export default ProductAlias;

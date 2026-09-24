import Utils from '../utils';

const FLASH_STORAGE_KEY = 'categoryFlash';
const PREVIEW_DEBOUNCE_MS = 600;

const FLASH_CLASSES = {
    success: ['border-green-500/30', 'bg-green-500/10', 'text-green-600'],
    error: ['border-destructive/30', 'bg-destructive/10', 'text-destructive'],
};

const Category = (() => {
    let initialized = false;
    let baseUrl;
    const previewTimers = {};

    // ---------- Aviso inline (a página não tem Toast) ----------

    const showFlash = (message, variant = 'success') => {
        const box = document.getElementById('categoryFlash');
        if (!box) return;

        Object.values(FLASH_CLASSES).flat().forEach(c => box.classList.remove(c));
        box.classList.add(...FLASH_CLASSES[variant]);
        box.querySelector('[data-flash-text]').textContent = message;
        box.classList.remove('hidden');
        box.classList.add('flex');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    // Ações que recarregam a página (números de vários cards mudam) deixam a mensagem para depois do reload.
    const reloadWithFlash = (message) => {
        try {
            sessionStorage.setItem(FLASH_STORAGE_KEY, message);
        } catch {
            // sem sessionStorage (modo privado): só perde a mensagem
        }
        location.reload();
    };

    const restoreFlash = () => {
        try {
            const message = sessionStorage.getItem(FLASH_STORAGE_KEY);
            if (!message) return;
            sessionStorage.removeItem(FLASH_STORAGE_KEY);
            showFlash(message);
        } catch {
            // ignora
        }
    };

    const errorMessage = (error, fallback) => {
        const errors = error.response?.data?.errors;
        return (errors && Object.values(errors).flat()[0]) || error.response?.data?.message || fallback;
    };

    const closeModal = (id) => window.KTModal?.getInstance(document.getElementById(id))?.hide();

    const setModalError = (id, message) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('hidden', !message);
    };

    // ---------- Erros dos formulários ----------

    const clearFormErrors = (context) => {
        document.querySelectorAll(`[data-form-error^="${context}-"]`).forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });
    };

    const showFormError = (context, field, message) => {
        const el = document.querySelector(`[data-form-error="${context}-${field}"]`);
        if (!el) return showFlash(message, 'error');

        el.textContent = message;
        el.classList.remove('hidden');
    };

    // Erros de validação (422) vão para o campo; cor e demais entram no do nome.
    const showValidationErrors = (context, error) => {
        const errors = error.response?.data?.errors;
        if (!errors) return showFlash(errorMessage(error, 'Não foi possível salvar a categoria.'), 'error');

        Object.entries(errors).forEach(([field, messages]) => {
            showFormError(context, field === 'keywords' ? 'keywords' : 'name', messages[0]);
        });
    };

    // ---------- Formulários e cards ----------

    const showNewForm = () => {
        document.getElementById('newCategoryForm').style.display = 'block';
    };

    const hideNewForm = () => {
        document.getElementById('newCategoryForm').style.display = 'none';
        clearFormErrors('new');
    };

    const buildKeywordsHtml = (keywords) => {
        if (!keywords.length) return '';

        const shown = keywords.slice(0, 8).map(kw => `<span class="text-xs bg-accent px-1.5 py-0.5 rounded">${Utils.escapeHtml(kw)}</span>`).join('');
        const extra = keywords.length > 8 ? `<span class="text-xs text-secondary-foreground">+${keywords.length - 8}</span>` : '';

        return `
            <div>
                <p class="text-xs text-secondary-foreground mb-1.5">Palavras-chave</p>
                <div class="flex flex-wrap gap-1">${shown}${extra}</div>
            </div>`;
    };

    const buildCategoryCardHtml = (category) => {
        const keywords = category.keywords || [];
        const color = /^#[0-9a-fA-F]{6}$/.test(category.color) ? category.color : '#94A3B8';
        const name = Utils.escapeHtml(category.name);
        const iconBtn = 'kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm transition-transform hover:scale-110';

        return `
            <div class="kt-card transition-shadow hover:shadow-md" style="box-shadow: inset 0 3px 0 0 ${color}" id="category-${category.id}">
                <div class="kt-card-header">
                    <h3 class="kt-card-title gap-2">
                        <span class="size-3 rounded-full shrink-0" data-color-dot style="background-color: ${color}"></span>
                        <a href="${baseUrl}/${category.id}" class="hover:underline" data-category-name>${name}</a>
                    </h3>
                    <div class="kt-card-toolbar gap-1">
                        <a href="${baseUrl}/${category.id}" class="${iconBtn}" title="Ver detalhes">
                            <i class="ki-filled ki-eye text-muted-foreground"></i>
                        </a>
                        <button data-action="edit-category"
                                data-category-id="${category.id}"
                                data-category-name="${name}"
                                data-category-color="${color}"
                                data-category-keywords="${Utils.escapeHtml(keywords.join(', '))}"
                                class="${iconBtn}" title="Editar">
                            <i class="ki-filled ki-pencil text-muted-foreground"></i>
                        </button>
                        <button data-action="prepare-merge" data-kt-modal-toggle="#mergeCategoryModal"
                                data-category-id="${category.id}" data-category-name="${name}"
                                class="${iconBtn}" title="Mesclar em outra categoria">
                            <i class="ki-filled ki-arrow-right-left text-muted-foreground"></i>
                        </button>
                        <button data-action="prepare-delete" data-kt-modal-toggle="#deleteCategoryModal"
                                data-category-id="${category.id}" data-category-name="${name}"
                                data-items-count="0" data-has-budget="0"
                                class="${iconBtn}" title="Excluir">
                            <i class="ki-filled ki-trash text-muted-foreground"></i>
                        </button>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-secondary-foreground">Itens</span>
                            <span class="text-sm font-medium text-foreground">0</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-secondary-foreground">Total gasto</span>
                            <span class="text-sm font-semibold font-mono text-primary tabular-nums">R$ 0,00</span>
                        </div>
                        <div data-keywords-section>${buildKeywordsHtml(keywords)}</div>
                    </div>
                </div>
            </div>`;
    };

    const insertCategoryCard = (category) => {
        const grid = document.getElementById('categoriesGrid');
        const cardHtml = buildCategoryCardHtml(category);

        if (grid.classList.contains('kt-card')) {
            grid.outerHTML = `<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" id="categoriesGrid">${cardHtml}</div>`;
        } else {
            grid.insertAdjacentHTML('beforeend', cardHtml);
        }
    };

    const patchCategoryCard = (category) => {
        const card = document.getElementById(`category-${category.id}`);
        if (!card) return;

        const keywords = category.keywords || [];
        const color = category.color || '#94A3B8';

        card.style.boxShadow = `inset 0 3px 0 0 ${color}`;
        card.querySelector('[data-color-dot]').style.backgroundColor = color;
        card.querySelector('[data-category-name]').textContent = category.name;
        card.querySelector('[data-keywords-section]').innerHTML = buildKeywordsHtml(keywords);

        const editBtn = card.querySelector('[data-action="edit-category"]');
        if (editBtn) {
            editBtn.dataset.categoryName = category.name;
            editBtn.dataset.categoryColor = color;
            editBtn.dataset.categoryKeywords = keywords.join(', ');
        }

        ['prepare-merge', 'prepare-delete'].forEach(action => {
            const btn = card.querySelector(`[data-action="${action}"]`);
            if (btn) btn.dataset.categoryName = category.name;
        });
    };

    const saveCategory = () => {
        clearFormErrors('new');

        const name = document.getElementById('newName').value.trim();
        if (!name) return showFormError('new', 'name', 'Informe o nome da categoria.');

        Utils.http(baseUrl, {
            method: 'POST',
            body: {
                name,
                color: document.getElementById('newColor').value,
                keywords: document.getElementById('newKeywords').value,
            },
        }).then(category => {
            insertCategoryCard(category);
            document.getElementById('newName').value = '';
            document.getElementById('newColor').value = '#3B82F6';
            document.getElementById('newKeywords').value = '';
            setPreview('new', '');
            hideNewForm();
            showFlash('Categoria criada.');
        }).catch(error => showValidationErrors('new', error));
    };

    const editCategory = (id, name, color, keywords) => {
        clearFormErrors('edit');
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editColor').value = color || '#94A3B8';
        document.getElementById('editKeywords').value = keywords;
        setPreview('edit', '');

        const form = document.getElementById('editCategoryForm');
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    };

    const hideEditForm = () => {
        document.getElementById('editCategoryForm').style.display = 'none';
        clearFormErrors('edit');
    };

    const updateCategory = () => {
        clearFormErrors('edit');

        const id = document.getElementById('editId').value;
        const name = document.getElementById('editName').value.trim();
        if (!name) return showFormError('edit', 'name', 'Informe o nome da categoria.');

        Utils.http(`${baseUrl}/${id}`, {
            method: 'PATCH',
            body: {
                name,
                color: document.getElementById('editColor').value,
                keywords: document.getElementById('editKeywords').value,
            },
        }).then(category => {
            patchCategoryCard(category);
            hideEditForm();
            showFlash('Categoria atualizada.');
        }).catch(error => showValidationErrors('edit', error));
    };

    // ---------- Excluir / mesclar / desfazer (modais) ----------

    const prepareDelete = (btn) => {
        const modal = document.getElementById('deleteCategoryModal');
        const items = parseInt(btn.dataset.itemsCount, 10) || 0;

        modal.dataset.categoryId = btn.dataset.categoryId;
        document.getElementById('deleteCategoryName').textContent = btn.dataset.categoryName;
        document.getElementById('deleteCategoryItems').textContent = items === 0
            ? 'Nenhum item está nesta categoria.'
            : `${items.toLocaleString('pt-BR')} ${items === 1 ? 'item fica' : 'itens ficam'} sem categoria.`;
        document.getElementById('deleteCategoryBudget').classList.toggle('hidden', btn.dataset.hasBudget !== '1');
        setModalError('deleteCategoryError', '');
    };

    const confirmDelete = () => {
        const id = document.getElementById('deleteCategoryModal').dataset.categoryId;

        Utils.http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                closeModal('deleteCategoryModal');
                reloadWithFlash('Categoria excluída.');
            })
            .catch(error => setModalError('deleteCategoryError', errorMessage(error, 'Não foi possível excluir a categoria.')));
    };

    // Destinos: as demais categorias que estão nos cards da página (do usuário e do sistema).
    const prepareMerge = (btn) => {
        const modal = document.getElementById('mergeCategoryModal');
        const select = document.getElementById('mergeCategoryTarget');
        const sourceId = btn.dataset.categoryId;

        modal.dataset.categoryId = sourceId;
        document.getElementById('mergeCategoryName').textContent = btn.dataset.categoryName;
        setModalError('mergeCategoryError', '');

        select.innerHTML = '';
        document.querySelectorAll('#categoriesGrid [id^="category-"]').forEach(card => {
            const id = card.id.replace('category-', '');
            if (id === sourceId) return;

            const option = document.createElement('option');
            option.value = id;
            option.textContent = card.querySelector('[data-category-name]').textContent.trim();
            select.appendChild(option);
        });
    };

    const confirmMerge = () => {
        const id = document.getElementById('mergeCategoryModal').dataset.categoryId;
        const targetId = document.getElementById('mergeCategoryTarget').value;
        if (!targetId) return setModalError('mergeCategoryError', 'Escolha a categoria de destino.');

        Utils.http(`${baseUrl}/${id}/merge`, { method: 'POST', body: { target_id: targetId } })
            .then(({ moved }) => {
                closeModal('mergeCategoryModal');
                reloadWithFlash(`Categorias mescladas: ${moved.toLocaleString('pt-BR')} ${moved === 1 ? 'item movido' : 'itens movidos'}.`);
            })
            .catch(error => setModalError('mergeCategoryError', errorMessage(error, 'Não foi possível mesclar as categorias.')));
    };

    const prepareRevert = (btn) => {
        document.getElementById('revertAutoCount').textContent = Number(btn.dataset.count).toLocaleString('pt-BR');
        setModalError('revertAutoError', '');
    };

    const confirmRevert = () => {
        Utils.http(`${baseUrl}/revert-auto-categorization`, { method: 'POST' })
            .then(({ reverted }) => {
                closeModal('revertAutoModal');
                reloadWithFlash(`${reverted.toLocaleString('pt-BR')} ${reverted === 1 ? 'item voltou' : 'itens voltaram'} a ficar sem categoria.`);
            })
            .catch(error => setModalError('revertAutoError', errorMessage(error, 'Não foi possível desfazer.')));
    };

    // ---------- Auto-categorização ----------

    const autoCategorize = () => {
        const btn = document.getElementById('btnAuto');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-setting-2 animate-spin"></i> Processando...';

        Utils.http(`${baseUrl}/auto-categorize`, { method: 'POST' })
            .then(({ categorized, ai_pending: aiPending }) => {
                const base = `${categorized.toLocaleString('pt-BR')} ${categorized === 1 ? 'item categorizado' : 'itens categorizados'} por palavras-chave e regras aprendidas (todas as suas notas).`;
                reloadWithFlash(aiPending ? `${base} A IA está analisando os demais em segundo plano.` : base);
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                showFlash(errorMessage(error, 'Não foi possível auto-categorizar.'), 'error');
            });
    };

    // ---------- Palavras-chave: sugestão por IA e prévia ----------

    const setPreview = (context, text) => {
        const el = document.querySelector(`[data-keywords-preview="${context}"]`);
        if (el) el.textContent = text;
    };

    const previewKeywords = (context) => {
        const keywords = document.getElementById(`${context}Keywords`).value.trim();
        if (!keywords) return setPreview(context, '');

        Utils.http(`${baseUrl}/preview-keywords`, { method: 'POST', body: { keywords } })
            .then(({ count, samples }) => {
                if (count === 0) return setPreview(context, 'Nenhum item sem categoria seria pego por estas palavras.');

                const noun = count === 1 ? 'item sem categoria' : 'itens sem categoria';
                setPreview(context, `Pegaria ${count.toLocaleString('pt-BR')} ${noun} (ex.: ${samples.join(', ')}).`);
            })
            .catch(error => {
                setPreview(context, '');
                showFormError(context, 'keywords', errorMessage(error, 'Palavras-chave inválidas.'));
            });
    };

    const schedulePreview = (context) => {
        clearTimeout(previewTimers[context]);
        document.querySelector(`[data-form-error="${context}-keywords"]`)?.classList.add('hidden');
        previewTimers[context] = setTimeout(() => previewKeywords(context), PREVIEW_DEBOUNCE_MS);
    };

    const suggestKeywords = (context) => {
        const name = document.getElementById(`${context}Name`).value.trim();
        if (!name) return showFormError(context, 'name', 'Informe o nome da categoria primeiro.');

        const btn = document.getElementById(context === 'new' ? 'btnSuggestNew' : 'btnSuggestEdit');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-setting-2 animate-spin"></i> Sugerindo...';

        Utils.http(`${baseUrl}/suggest-keywords`, { method: 'POST', body: { name } })
            .then(data => {
                document.getElementById(`${context}Keywords`).value = (data.keywords || []).join(', ');
                previewKeywords(context);
            })
            .catch(error => showFlash(errorMessage(error, 'Não foi possível obter sugestões da IA.'), 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    };

    const ACTIONS = {
        'auto-categorize': () => autoCategorize(),
        'show-new-form': () => showNewForm(),
        'save-category': () => saveCategory(),
        'hide-new-form': () => hideNewForm(),
        'update-category': () => updateCategory(),
        'hide-edit-form': () => hideEditForm(),
        'suggest-keywords-new': () => suggestKeywords('new'),
        'suggest-keywords-edit': () => suggestKeywords('edit'),
        'edit-category': (btn) => editCategory(
            btn.dataset.categoryId,
            btn.dataset.categoryName,
            btn.dataset.categoryColor,
            btn.dataset.categoryKeywords,
        ),
        'prepare-delete': (btn) => prepareDelete(btn),
        'confirm-delete-category': () => confirmDelete(),
        'prepare-merge': (btn) => prepareMerge(btn),
        'confirm-merge-category': () => confirmMerge(),
        'prepare-revert': (btn) => prepareRevert(btn),
        'confirm-revert-auto': () => confirmRevert(),
    };

    const handleClick = (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        ACTIONS[btn.dataset.action]?.(btn);
    };

    const handleInput = (e) => {
        if (e.target.id === 'newKeywords') schedulePreview('new');
        if (e.target.id === 'editKeywords') schedulePreview('edit');
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ baseUrl } = window.pageConfig);

            Utils.initPeriodFilter();
            document.addEventListener('click', handleClick);
            document.addEventListener('input', handleInput);
            restoreFlash();
        }
    };
})();

export default Category;

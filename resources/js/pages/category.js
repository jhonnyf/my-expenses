import Utils from '../utils';

const Category = (() => {
    let initialized = false;
    let baseUrl;

    const showNewForm = () => {
        document.getElementById('newCategoryForm').style.display = 'block';
    };

    const hideNewForm = () => {
        document.getElementById('newCategoryForm').style.display = 'none';
    };

    const buildKeywordsHtml = (keywords) => {
        if (!keywords.length) return '';

        const shown = keywords.slice(0, 8).map(kw => `<span class="text-xs bg-accent px-1.5 py-0.5 rounded">${Utils.escapeHtml(kw)}</span>`).join('');
        const extra = keywords.length > 8 ? `<span class="text-xs text-secondary-foreground">+${keywords.length - 8}</span>` : '';

        return `
            <div>
                <p class="text-xs text-secondary-foreground mb-1.5">Keywords</p>
                <div class="flex flex-wrap gap-1">${shown}${extra}</div>
            </div>`;
    };

    const buildCategoryCardHtml = (category) => {
        const keywords = category.keywords || [];
        const color = category.color || '#94A3B8';

        return `
            <div class="kt-card transition-shadow hover:shadow-md" style="box-shadow: inset 0 3px 0 0 ${color}" id="category-${category.id}">
                <div class="kt-card-header">
                    <h3 class="kt-card-title gap-2">
                        <span class="size-3 rounded-full shrink-0" data-color-dot style="background-color: ${color}"></span>
                        <span data-category-name>${Utils.escapeHtml(category.name)}</span>
                    </h3>
                    <div class="kt-card-toolbar gap-1">
                        <button data-action="edit-category"
                                data-category-id="${category.id}"
                                data-category-name="${Utils.escapeHtml(category.name)}"
                                data-category-color="${color}"
                                data-category-keywords="${Utils.escapeHtml(keywords.join(', '))}"
                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm transition-transform hover:scale-110" title="Editar">
                            <i class="ki-filled ki-pencil text-muted-foreground"></i>
                        </button>
                        <button data-action="delete-category" data-category-id="${category.id}"
                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm transition-transform hover:scale-110" title="Excluir">
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
    };

    const saveCategory = () => {
        const name = document.getElementById('newName').value.trim();
        if (!name) return;

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
            hideNewForm();
        });
    };

    const editCategory = (id, name, color, keywords) => {
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editColor').value = color || '#94A3B8';
        document.getElementById('editKeywords').value = keywords;

        const form = document.getElementById('editCategoryForm');
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    };

    const hideEditForm = () => {
        document.getElementById('editCategoryForm').style.display = 'none';
    };

    const updateCategory = () => {
        const id = document.getElementById('editId').value;
        const name = document.getElementById('editName').value.trim();
        if (!name) return;

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
        });
    };

    const deleteCategory = (id) => {
        if (!confirm('Deseja excluir esta categoria?')) return;

        Utils.http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                const el = document.getElementById('category-' + id);
                if (el) el.remove();
            });
    };

    const autoCategorize = () => {
        const btn = document.getElementById('btnAuto');
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-setting-2 animate-spin"></i> Processando...';

        Utils.http(`${baseUrl}/auto-categorize`, { method: 'POST' })
            .then(data => {
                alert(data.categorized + ' itens categorizados!');
                location.reload();
            });
    };

    const ACTIONS = {
        'auto-categorize': () => autoCategorize(),
        'show-new-form': () => showNewForm(),
        'save-category': () => saveCategory(),
        'hide-new-form': () => hideNewForm(),
        'update-category': () => updateCategory(),
        'hide-edit-form': () => hideEditForm(),
        'edit-category': (btn) => editCategory(
            btn.dataset.categoryId,
            btn.dataset.categoryName,
            btn.dataset.categoryColor,
            btn.dataset.categoryKeywords,
        ),
        'delete-category': (btn) => deleteCategory(btn.dataset.categoryId),
    };

    const handleClick = (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        ACTIONS[btn.dataset.action]?.(btn);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ baseUrl } = window.pageConfig);

            document.addEventListener('click', handleClick);
        }
    };
})();

export default Category;

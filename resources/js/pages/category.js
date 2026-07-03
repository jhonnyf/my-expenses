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
        }).then(() => location.reload());
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
        }).then(() => location.reload());
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

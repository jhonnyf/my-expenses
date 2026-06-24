import { http } from '../utils';

export default function init() {
    const { baseUrl } = window.pageConfig;

    window.showNewForm = () => {
        document.getElementById('newCategoryForm').style.display = 'block';
    };

    window.hideNewForm = () => {
        document.getElementById('newCategoryForm').style.display = 'none';
    };

    window.saveCategory = () => {
        const name = document.getElementById('newName').value.trim();
        if (!name) return;

        http(baseUrl, {
            method: 'POST',
            body: {
                name,
                color: document.getElementById('newColor').value,
                keywords: document.getElementById('newKeywords').value,
            },
        }).then(() => location.reload());
    };

    window.editCategory = (id, name, color, keywords) => {
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editColor').value = color || '#94A3B8';
        document.getElementById('editKeywords').value = keywords;

        const form = document.getElementById('editCategoryForm');
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    };

    window.hideEditForm = () => {
        document.getElementById('editCategoryForm').style.display = 'none';
    };

    window.updateCategory = () => {
        const id = document.getElementById('editId').value;
        const name = document.getElementById('editName').value.trim();
        if (!name) return;

        http(`${baseUrl}/${id}`, {
            method: 'PATCH',
            body: {
                name,
                color: document.getElementById('editColor').value,
                keywords: document.getElementById('editKeywords').value,
            },
        }).then(() => location.reload());
    };

    window.deleteCategory = (id) => {
        if (!confirm('Deseja excluir esta categoria?')) return;

        http(`${baseUrl}/${id}`, { method: 'DELETE' })
            .then(() => {
                const el = document.getElementById('category-' + id);
                if (el) el.remove();
            });
    };

    window.autoCategorize = () => {
        const btn = document.getElementById('btnAuto');
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-setting-2 animate-spin"></i> Processando...';

        http(`${baseUrl}/auto-categorize`, { method: 'POST' })
            .then(data => {
                alert(data.categorized + ' itens categorizados!');
                location.reload();
            });
    };
}

export default function init() {
    var BASE = window.pageConfig.baseUrl;
    var CSRF = window.pageConfig.csrfToken;

    window.showNewForm = function () {
        document.getElementById('newCategoryForm').style.display = 'block';
    };

    window.hideNewForm = function () {
        document.getElementById('newCategoryForm').style.display = 'none';
    };

    window.saveCategory = function () {
        var name = document.getElementById('newName').value.trim();
        if (!name) return;

        fetch(BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({
                name: name,
                color: document.getElementById('newColor').value,
                keywords: document.getElementById('newKeywords').value,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function () { location.reload(); });
    };

    window.editCategory = function (id, name, color, keywords) {
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editColor').value = color || '#94A3B8';
        document.getElementById('editKeywords').value = keywords;
        document.getElementById('editCategoryForm').style.display = 'block';
        document.getElementById('editCategoryForm').scrollIntoView({ behavior: 'smooth' });
    };

    window.hideEditForm = function () {
        document.getElementById('editCategoryForm').style.display = 'none';
    };

    window.updateCategory = function () {
        var id = document.getElementById('editId').value;
        var name = document.getElementById('editName').value.trim();
        if (!name) return;

        fetch(BASE + '/' + id, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({
                name: name,
                color: document.getElementById('editColor').value,
                keywords: document.getElementById('editKeywords').value,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function () { location.reload(); });
    };

    window.deleteCategory = function (id) {
        if (!confirm('Deseja excluir esta categoria?')) return;
        fetch(BASE + '/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            var el = document.getElementById('category-' + id);
            if (el) el.remove();
        });
    };

    window.autoCategorize = function () {
        var btn = document.getElementById('btnAuto');
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-setting-2 animate-spin"></i> Processando...';

        fetch(BASE + '/auto-categorize', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            alert(data.categorized + ' itens categorizados!');
            location.reload();
        });
    };
}

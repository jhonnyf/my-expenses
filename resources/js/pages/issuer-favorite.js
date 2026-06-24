export default function init() {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-favorite-id]');
        if (!btn || btn.disabled) return;
        btn.disabled = true;

        var config = window.pageConfig || {};
        var baseUrl = config.issuerBaseUrl || '';
        var csrfToken = config.csrfToken || '';

        fetch(baseUrl + '/' + btn.dataset.favoriteId + '/favorite', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.statusText);
            return r.json();
        })
        .then(function (data) {
            var span = btn.querySelector('span');
            if (data.is_favorite) {
                btn.classList.remove('text-muted-foreground', 'hover:text-yellow-500');
                btn.classList.add('text-yellow-500');
                if (span) {
                    btn.classList.add('border-yellow-500');
                    span.textContent = 'Favoritado';
                }
                if (btn.hasAttribute('title')) btn.title = 'Remover dos favoritos';
            } else {
                btn.classList.remove('text-yellow-500', 'border-yellow-500');
                btn.classList.add('text-muted-foreground', 'hover:text-yellow-500');
                if (span) span.textContent = 'Favoritar';
                if (btn.hasAttribute('title')) btn.title = 'Favoritar emitente';
            }
        })
        .catch(function () {
            alert('Erro ao atualizar favorito. Tente novamente.');
        })
        .finally(function () {
            btn.disabled = false;
        });
    });
}

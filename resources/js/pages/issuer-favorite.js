import { http } from '../utils';

export default function init() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-favorite-id]');
        if (!btn || btn.disabled) return;
        btn.disabled = true;

        const baseUrl = window.pageConfig?.issuerBaseUrl || '';

        http(`${baseUrl}/${btn.dataset.favoriteId}/favorite`, { method: 'POST' })
            .then(data => {
                const span = btn.querySelector('span');

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
            .catch(() => {
                alert('Erro ao atualizar favorito. Tente novamente.');
            })
            .finally(() => {
                btn.disabled = false;
            });
    });
}

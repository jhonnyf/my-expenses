import { http } from '../utils';

export default function init() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-favorite-id]');
        if (!btn || btn.disabled) return;
        btn.disabled = true;

        const baseUrl = window.pageConfig?.issuerBaseUrl || '';

        http(`${baseUrl}/${btn.dataset.favoriteId}/favorite`, { method: 'POST' })
            .then(data => {
                applyFavoriteState(btn, data.is_favorite);
            })
            .catch(() => {
                alert('Erro ao atualizar favorito. Tente novamente.');
            })
            .finally(() => {
                btn.disabled = false;
            });
    });
}

function applyFavoriteState(btn, isFavorite) {
    const label = btn.querySelector('span') ?? document.getElementById('btnFavoriteLabel');
    const icon  = btn.querySelector('i')   ?? document.getElementById('btnFavoriteIcon');

    // Botão do header (tem id="btnFavorite")
    if (btn.id === 'btnFavorite') {
        if (isFavorite) {
            btn.classList.remove('kt-btn-outline', 'hover:border-yellow-500', 'hover:text-yellow-500');
            btn.classList.add('bg-yellow-500', 'hover:bg-yellow-600', 'text-white', 'border-yellow-500', 'shadow-md', 'shadow-yellow-500/30');
            icon?.classList.add('scale-125');
        } else {
            btn.classList.remove('bg-yellow-500', 'hover:bg-yellow-600', 'text-white', 'border-yellow-500', 'shadow-md', 'shadow-yellow-500/30');
            btn.classList.add('kt-btn-outline', 'hover:border-yellow-500', 'hover:text-yellow-500');
            icon?.classList.remove('scale-125');
        }
        if (label) label.textContent = isFavorite ? 'Favoritado' : 'Favoritar';
        if (btn.hasAttribute('title')) btn.title = isFavorite ? 'Remover dos favoritos' : 'Favoritar emitente';
    }

    // Botão de listagem (ícone simples, sem id)
    if (!btn.id || btn.id !== 'btnFavorite') {
        if (isFavorite) {
            btn.classList.remove('text-muted-foreground', 'hover:text-yellow-500');
            btn.classList.add('text-yellow-500');
        } else {
            btn.classList.remove('text-yellow-500');
            btn.classList.add('text-muted-foreground', 'hover:text-yellow-500');
        }
        if (btn.hasAttribute('title')) btn.title = isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
    }

    // Avatar do card de perfil (página de detalhe)
    const avatar = document.getElementById('profileAvatar');
    if (avatar) {
        if (isFavorite) {
            avatar.classList.remove('bg-primary/10', 'text-primary', 'ring-primary/30');
            avatar.classList.add('bg-yellow-500/15', 'text-yellow-600', 'ring-yellow-400', 'shadow-lg', 'shadow-yellow-500/20');
        } else {
            avatar.classList.remove('bg-yellow-500/15', 'text-yellow-600', 'ring-yellow-400', 'shadow-lg', 'shadow-yellow-500/20');
            avatar.classList.add('bg-primary/10', 'text-primary', 'ring-primary/30');
        }
    }

    // Badge "Favorito" do card de perfil
    const badge = document.getElementById('favoriteBadge');
    if (badge) {
        badge.classList.toggle('hidden', !isFavorite);
    }
}

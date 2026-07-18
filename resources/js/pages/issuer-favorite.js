import Utils from '../utils';

const DETAIL_BUTTON_ID = 'btnFavorite';

const DETAIL_FAVORITE_CLASSES = ['bg-yellow-500', 'hover:bg-yellow-600', 'text-white', 'border-yellow-500', 'shadow-md', 'shadow-yellow-500/30'];
const DETAIL_OUTLINE_CLASSES = ['kt-btn-outline', 'hover:border-yellow-500', 'hover:text-yellow-500'];

const AVATAR_FAVORITE_CLASSES = ['bg-yellow-500/15', 'text-yellow-600', 'ring-yellow-400', 'shadow-lg', 'shadow-yellow-500/20'];
const AVATAR_DEFAULT_CLASSES = ['bg-primary/10', 'text-primary', 'ring-primary/30'];

const LIST_ROW_FAVORITE_CLASSES = ['bg-yellow-500/5', 'border-yellow-400/40'];
const LIST_CELL_ACCENT_CLASSES = ['shadow-[inset_3px_0_0_0_#eab308]'];
const LIST_AVATAR_FAVORITE_CLASSES = ['bg-yellow-500/10', 'text-yellow-600', 'ring-2', 'ring-yellow-400/40'];
const LIST_AVATAR_DEFAULT_CLASSES = ['bg-primary/10', 'text-primary'];

const IssuerFavorite = (() => {
    let initialized = false;

    const toggleClassList = (el, classes, force) => classes.forEach(c => el.classList.toggle(c, force));

    const resolveButton = (e) => {
        const btn = e.target.closest('[data-favorite-id]');
        return (!btn || btn.disabled) ? null : btn;
    };

    const toggleFavorite = async (btn) => {
        btn.disabled = true;

        const { issuerBaseUrl = '' } = window.pageConfig ?? {};

        try {
            const { is_favorite: isFavorite } = await Utils.http(`${issuerBaseUrl}/${btn.dataset.favoriteId}/favorite`, { method: 'POST' });
            applyFavoriteState(btn, isFavorite);
        } catch {
            alert('Erro ao atualizar favorito. Tente novamente.');
        } finally {
            btn.disabled = false;
        }
    };

    const handleClick = (e) => {
        const btn = resolveButton(e);
        if (btn) toggleFavorite(btn);
    };

    const updateFavoritesBadge = (delta) => {
        const badge = document.getElementById('issuerFavoritesBadge');
        const countEl = document.getElementById('issuerFavoritesCount');
        const labelEl = document.getElementById('issuerFavoritesLabel');
        if (!badge || !countEl || !labelEl) return;

        const count = Math.max(0, (parseInt(countEl.textContent, 10) || 0) + delta);
        countEl.textContent = count;
        labelEl.textContent = count === 1 ? 'favorito' : 'favoritos';
        badge.classList.toggle('hidden', count === 0);
    };

    const applyIconButtonState = (btn, isFavorite) => {
        const icon = btn.querySelector('i');

        icon?.classList.toggle('text-yellow-500', isFavorite);

        if (btn.hasAttribute('title')) {
            btn.title = isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
        }

        const row = btn.closest('.issuer-row');
        if (!row) return;

        toggleClassList(row, LIST_ROW_FAVORITE_CLASSES, isFavorite);

        const cell = btn.closest('td');
        if (cell) toggleClassList(cell, LIST_CELL_ACCENT_CLASSES, isFavorite);

        const avatar = row.querySelector('.issuer-avatar');
        if (avatar) {
            toggleClassList(avatar, LIST_AVATAR_FAVORITE_CLASSES, isFavorite);
            toggleClassList(avatar, LIST_AVATAR_DEFAULT_CLASSES, !isFavorite);
        }
    };

    const applyProfileCardState = (isFavorite) => {
        const avatar = document.getElementById('profileAvatar');

        if (avatar) {
            toggleClassList(avatar, AVATAR_FAVORITE_CLASSES, isFavorite);
            toggleClassList(avatar, AVATAR_DEFAULT_CLASSES, !isFavorite);
        }

        document.getElementById('favoriteBadge')?.classList.toggle('hidden', !isFavorite);
    };

    const applyDetailButtonState = (btn, isFavorite) => {
        const icon = btn.querySelector('i');
        const label = btn.querySelector('span');

        if (label) label.textContent = isFavorite ? 'Favoritado' : 'Favoritar';
        btn.title = isFavorite ? 'Remover dos favoritos' : 'Favoritar emitente';
        icon?.classList.toggle('scale-125', isFavorite);

        toggleClassList(btn, DETAIL_FAVORITE_CLASSES, isFavorite);
        toggleClassList(btn, DETAIL_OUTLINE_CLASSES, !isFavorite);

        applyProfileCardState(isFavorite);
    };

    const applyFavoriteState = (btn, isFavorite) => {
        if (btn.id === DETAIL_BUTTON_ID) {
            applyDetailButtonState(btn, isFavorite);
            return;
        }

        const issuerId = btn.dataset.favoriteId;
        document.querySelectorAll(`[data-favorite-id="${issuerId}"]`).forEach(b => applyIconButtonState(b, isFavorite));
        updateFavoritesBadge(isFavorite ? 1 : -1);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            document.addEventListener('click', handleClick);
        }
    };
})();

export default IssuerFavorite;

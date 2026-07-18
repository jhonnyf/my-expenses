import Utils from '../utils';

const IssuerNickname = (() => {
    let initialized = false;

    const modalEl = () => document.getElementById('nicknameModal');

    const hideModal = () => {
        const el = modalEl();
        if (el && window.KTModal) {
            window.KTModal.getInstance(el)?.hide();
        }
    };

    const showError = (message) => {
        const el = document.getElementById('nicknameModalError');
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('hidden', !message);
    };

    const openModal = (btn) => {
        const modal = modalEl();
        if (!modal) return;

        modal.dataset.issuerId = btn.dataset.issuerId;

        document.getElementById('nicknameModalOriginalName').textContent = btn.dataset.issuerName;
        document.getElementById('nicknameModalInput').value = btn.dataset.issuerNickname || '';
        showError('');
    };

    const updateListRow = (issuerId, displayName) => {
        document.querySelectorAll(`[data-action="edit-nickname"][data-issuer-id="${issuerId}"]`).forEach(btn => {
            const row = btn.closest('.issuer-row');
            if (!row) return;

            const nameEl = row.querySelector('.issuer-name');
            if (nameEl) {
                nameEl.textContent = displayName;
                if (btn.dataset.issuerNickname) {
                    nameEl.title = `Nome oficial: ${btn.dataset.issuerName}`;
                } else {
                    nameEl.removeAttribute('title');
                }
            }

            const avatar = row.querySelector('.issuer-avatar');
            if (avatar) avatar.textContent = displayName.substring(0, 2).toUpperCase();
        });
    };

    const updateDetailPage = (nickname, displayName) => {
        if (!document.getElementById('issuerDisplayName')) return;

        document.getElementById('issuerDisplayName').textContent = displayName;
        document.getElementById('issuerBreadcrumbName').textContent = displayName;
        document.getElementById('profileAvatarInitials').textContent = displayName.substring(0, 2).toUpperCase();

        document.getElementById('issuerOriginalNameWrap')?.classList.toggle('hidden', !nickname);
    };

    const saveNickname = async () => {
        const issuerId = modalEl()?.dataset.issuerId;
        if (!issuerId) {
            showError('Não foi possível identificar o emissor. Feche o modal e tente novamente.');
            return;
        }

        const input = document.getElementById('nicknameModalInput');
        const nickname = input.value.trim();
        const saveBtn = document.getElementById('nicknameModalSave');

        saveBtn.disabled = true;
        showError('');

        try {
            const { nickname: savedNickname, display_name: displayName } = await Utils.http(`/issuers/${issuerId}/nickname`, {
                method: 'PUT',
                body: { nickname },
            });

            document.querySelectorAll(`[data-action="edit-nickname"][data-issuer-id="${issuerId}"]`).forEach(btn => {
                btn.dataset.issuerNickname = savedNickname || '';
            });

            updateListRow(issuerId, displayName);
            updateDetailPage(savedNickname, displayName);

            hideModal();
        } catch (error) {
            const message = error?.response?.data?.errors?.nickname?.[0]
                ?? error?.response?.data?.message
                ?? 'Erro ao salvar apelido. Tente novamente.';
            showError(message);
        } finally {
            saveBtn.disabled = false;
        }
    };

    const handleClick = (e) => {
        const trigger = e.target.closest('[data-action="edit-nickname"]');
        if (trigger) {
            openModal(trigger);
            return;
        }

        if (e.target.closest('#nicknameModalSave')) {
            saveNickname();
        }
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            // Fase de captura: o KTUI trata cliques em [data-kt-modal-toggle] em
            // document.body na fase de bubble e chama stopPropagation(), o que
            // impediria este listener de rodar antes do modal abrir.
            document.addEventListener('click', handleClick, true);
        }
    };
})();

export default IssuerNickname;

import Utils from '../utils';

const TAB_TOGGLE_SELECTORS = {
    settings: '[data-kt-tab-toggle="#tab_settings"]',
    security: '[data-kt-tab-toggle="#tab_security"]',
};

const Account = (() => {
    let initialized = false;

    const openTab = (tab) => {
        document.querySelector(TAB_TOGGLE_SELECTORS[tab])?.click();
    };

    // Trocar o e-mail exige a senha atual: o campo só aparece quando o e-mail difere do cadastrado.
    const togglePasswordForEmailChange = (input) => {
        const field = document.getElementById('email_password_field');
        if (!field) return;

        const changed = input.value.trim().toLowerCase() !== input.dataset.original.toLowerCase();
        field.classList.toggle('hidden', !changed);
    };

    // Mantém a aba na URL (?tab=), para link direto e para o F5 voltar na mesma aba.
    const syncTabInUrl = (e) => {
        const toggle = e.target.closest('[data-kt-tab-toggle]');
        if (!toggle) return;

        const tab = toggle.dataset.ktTabToggle.replace('#tab_', '');
        const url = new URL(location.href);
        if (tab === 'overview') url.searchParams.delete('tab'); else url.searchParams.set('tab', tab);
        history.replaceState(null, '', url);
    };

    const handleLocationUpdated = (e) => {
        const { cidade, estado } = e.detail;

        const cidadeInput = document.getElementById('cidade');
        const estadoSelect = document.getElementById('estado');
        if (!cidadeInput || !estadoSelect) return;

        cidadeInput.value = cidade;
        Utils.syncSelectValue(estadoSelect, estado);

        const status = document.getElementById('locationCaptureStatus');
        if (status) {
            status.classList.remove('hidden');
            setTimeout(() => status.classList.add('hidden'), 4000);
        }
    };

    const previewAvatar = (input) => {
        const file = input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const wrapper = document.getElementById('avatar_preview_frame');
            if (wrapper) {
                wrapper.innerHTML = `<img src="${e.target.result}" alt="preview" class="size-full object-cover" />`;
            }
        };
        reader.readAsDataURL(file);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            document.getElementById('btn_edit_profile')?.addEventListener('click', () => openTab('settings'));

            document.getElementById('avatar')?.addEventListener('change', function () {
                previewAvatar(this);
            });

            document.addEventListener('location:updated', handleLocationUpdated);
            document.addEventListener('click', syncTabInUrl);
            document.addEventListener('click', (e) => {
                const target = e.target.closest('[data-open-tab]');
                if (target) openTab(target.dataset.openTab);
            });

            const emailInput = document.getElementById('email');
            emailInput?.addEventListener('input', () => togglePasswordForEmailChange(emailInput));

            const { openTab: tab, openDeleteAccountModal } = window.pageConfig ?? {};
            if (tab && TAB_TOGGLE_SELECTORS[tab]) openTab(tab);
            if (openDeleteAccountModal) {
                document.querySelector('[data-kt-modal-toggle="#deleteAccountModal"]')?.click();
            }
        }
    };
})();

export default Account;

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

            const { openTab: tab } = window.pageConfig ?? {};
            if (tab) openTab(tab);
        }
    };
})();

export default Account;

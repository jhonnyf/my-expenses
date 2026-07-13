const TAB_TOGGLE_SELECTORS = {
    settings: '[data-kt-tab-toggle="#tab_settings"]',
    security: '[data-kt-tab-toggle="#tab_security"]',
};

const Account = (() => {
    let initialized = false;

    const openTab = (tab) => {
        document.querySelector(TAB_TOGGLE_SELECTORS[tab])?.click();
    };

    const previewAvatar = (input) => {
        const file = input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const wrapper = document.getElementById('avatar_preview_wrapper');
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

            const { openTab: tab } = window.pageConfig ?? {};
            if (tab) openTab(tab);
        }
    };
})();

export default Account;

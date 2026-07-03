const TABS = ['xml', 'qrcode', 'access_key'];

const Upload = (() => {
    let initialized = false;

    const switchTab = (tab) => {
        TABS.forEach(t => {
            const tabEl = document.getElementById('tab-' + t);
            const panelEl = document.getElementById('panel-' + t);
            const isActive = t === tab;

            tabEl.classList.toggle('border-primary', isActive);
            tabEl.classList.toggle('text-primary', isActive);
            tabEl.classList.toggle('border-transparent', !isActive);
            tabEl.classList.toggle('text-secondary-foreground', !isActive);
            panelEl.style.display = isActive ? 'block' : 'none';
        });
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            const { initialTab } = window.pageConfig || {};
            if (initialTab && initialTab !== 'qrcode') {
                switchTab(initialTab);
            }
        }
    };
})();

export default Upload;

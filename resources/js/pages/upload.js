export default function init() {
    const { initialTab } = window.pageConfig || {};

    window.switchTab = (tab) => {
        const tabs = ['xml', 'qrcode'];
        tabs.forEach(t => {
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

    if (initialTab && initialTab !== 'xml') {
        window.switchTab(initialTab);
    }
}

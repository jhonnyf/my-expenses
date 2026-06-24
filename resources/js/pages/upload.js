export default function init() {
    var config = window.pageConfig || {};

    window.switchTab = function (tab) {
        var tabs = ['xml', 'qrcode'];
        tabs.forEach(function (t) {
            var tabEl = document.getElementById('tab-' + t);
            var panelEl = document.getElementById('panel-' + t);
            if (t === tab) {
                tabEl.classList.add('border-primary', 'text-primary');
                tabEl.classList.remove('border-transparent', 'text-secondary-foreground');
                panelEl.style.display = 'block';
            } else {
                tabEl.classList.remove('border-primary', 'text-primary');
                tabEl.classList.add('border-transparent', 'text-secondary-foreground');
                panelEl.style.display = 'none';
            }
        });
    };

    if (config.initialTab && config.initialTab !== 'xml') {
        window.switchTab(config.initialTab);
    }
}

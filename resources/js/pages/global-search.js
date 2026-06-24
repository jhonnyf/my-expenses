export default function init() {
    var config = window.pageConfig || {};
    var searchUrl = config.searchUrl || '';

    var gsInput = document.getElementById('globalSearchInput');
    var gsResults = document.getElementById('globalSearchResults');
    if (!gsInput || !gsResults) return;

    var gsTimeout = null;

    gsInput.addEventListener('input', function () {
        clearTimeout(gsTimeout);
        var q = this.value.trim();
        if (q.length < 2) {
            gsResults.classList.add('hidden');
            gsResults.innerHTML = '';
            return;
        }
        gsTimeout = setTimeout(function () { gsSearch(q); }, 300);
    });

    function gsSearch(q) {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var html = '';
            var sections = [
                { key: 'emissores', label: 'Emissores', icon: 'ki-filled ki-shop' },
                { key: 'notas_fiscais', label: 'Notas Fiscais', icon: 'ki-filled ki-document' },
                { key: 'produtos', label: 'Produtos', icon: 'ki-filled ki-basket' },
            ];

            var hasResults = false;
            sections.forEach(function (sec) {
                var items = data[sec.key] || [];
                if (items.length === 0) return;
                hasResults = true;
                html += '<div class="px-3 py-2 bg-accent/40 text-xs font-semibold text-secondary-foreground uppercase tracking-wide flex items-center gap-1.5">' +
                    '<i class="' + sec.icon + ' text-primary"></i> ' + sec.label +
                    '</div>';
                items.forEach(function (item) {
                    html += '<a href="' + item.url + '" class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/30 transition-colors cursor-pointer">' +
                        '<div class="min-w-0 flex-1">' +
                        '<p class="text-sm font-medium text-foreground truncate">' + item.title + '</p>' +
                        '<p class="text-xs text-secondary-foreground truncate">' + item.subtitle + '</p>' +
                        '</div></a>';
                });
            });

            if (!hasResults) {
                html = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum resultado encontrado.</div>';
            }

            gsResults.innerHTML = html;
            gsResults.classList.remove('hidden');
        });
    }

    document.addEventListener('click', function (e) {
        if (!document.getElementById('globalSearchWrapper').contains(e.target)) {
            gsResults.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            gsResults.classList.add('hidden');
            gsInput.blur();
        }
    });
}

import Utils from '../utils';

const GlobalSearch = (() => {
    let initialized = false;
    let searchUrl, searchInput, searchResults;
    let debounceTimer = null;

    const search = (query) => {
        Utils.http(`${searchUrl}?q=${encodeURIComponent(query)}`)
            .then(data => {
                const sections = [
                    { key: 'emissores', label: 'Emissores', icon: 'ki-filled ki-shop' },
                    { key: 'notas_fiscais', label: 'Notas Fiscais', icon: 'ki-filled ki-document' },
                    { key: 'produtos', label: 'Produtos', icon: 'ki-filled ki-basket' },
                ];

                let html = '';
                let hasResults = false;

                for (const sec of sections) {
                    const items = data[sec.key] || [];
                    if (items.length === 0) continue;
                    hasResults = true;

                    html += `<div class="px-3 py-2 bg-accent/40 text-xs font-semibold text-secondary-foreground uppercase tracking-wide flex items-center gap-1.5">
                        <i class="${sec.icon} text-primary"></i> ${sec.label}
                    </div>`;

                    for (const item of items) {
                        html += `<a href="${item.url}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/30 transition-colors cursor-pointer">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-foreground truncate">${Utils.escapeHtml(item.title)}</p>
                                <p class="text-xs text-secondary-foreground truncate">${Utils.escapeHtml(item.subtitle)}</p>
                            </div>
                        </a>`;
                    }
                }

                if (!hasResults) {
                    html = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum resultado encontrado.</div>';
                }

                searchResults.innerHTML = html;
                searchResults.classList.remove('hidden');
            });
    };

    const handleInput = () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => search(query), 300);
    };

    const handleDocumentClick = (e) => {
        if (!document.getElementById('globalSearchWrapper').contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    };

    const handleKeydown = (e) => {
        if (e.key === 'Escape') {
            searchResults.classList.add('hidden');
            searchInput.blur();
        }
    };

    return {
        init: () => {
            if (initialized) return;

            searchUrl = window.pageConfig?.globalSearchUrl || '';
            searchInput = document.getElementById('globalSearchInput');
            searchResults = document.getElementById('globalSearchResults');
            if (!searchInput || !searchResults) return;

            initialized = true;

            searchInput.addEventListener('input', handleInput);
            document.addEventListener('click', handleDocumentClick);
            document.addEventListener('keydown', handleKeydown);
        }
    };
})();

export default GlobalSearch;

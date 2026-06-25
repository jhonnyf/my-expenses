import { http } from '../utils';

export default function init() {
    const searchUrl = window.pageConfig?.globalSearchUrl || '';
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    if (!searchInput || !searchResults) return;

    let debounceTimer = null;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => search(query), 300);
    });

    function search(query) {
        http(`${searchUrl}?q=${encodeURIComponent(query)}`)
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
                                <p class="text-sm font-medium text-foreground truncate">${item.title}</p>
                                <p class="text-xs text-secondary-foreground truncate">${item.subtitle}</p>
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
    }

    document.addEventListener('click', (e) => {
        if (!document.getElementById('globalSearchWrapper').contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchResults.classList.add('hidden');
            searchInput.blur();
        }
    });
}

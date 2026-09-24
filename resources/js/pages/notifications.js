import Utils from '../utils';

const LEVEL_ICONS = {
    info: { icon: 'ki-price-tag', color: 'text-primary' },
    warning: { icon: 'ki-information-2', color: 'text-yellow-600' },
    danger: { icon: 'ki-information-2', color: 'text-destructive' },
};

const RELATIVE_UNITS = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

const Notifications = (() => {
    let initialized = false;
    let notificationsUrl, notificationsReadUrl, unreadCountUrl, readAllUrl;
    let toggle, badge, list, readAllButton;

    const relativeTime = (isoDate) => {
        const seconds = Math.round((new Date(isoDate).getTime() - Date.now()) / 1000);
        if (Math.abs(seconds) < 60) return 'agora';

        const formatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
        const [unit, size] = RELATIVE_UNITS.find(([, size]) => Math.abs(seconds) >= size);

        // Passado das últimas semanas vira data; "há 45 dias" não ajuda a achar o aviso.
        return Math.abs(seconds) >= 7 * 86400
            ? new Date(isoDate).toLocaleDateString('pt-BR')
            : formatter.format(Math.trunc(seconds / size), unit);
    };

    const setUnread = (count) => {
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.classList.toggle('hidden', count === 0);
        badge.setAttribute('aria-label', `${count} notificações não lidas`);
        readAllButton.classList.toggle('hidden', count === 0);
    };

    const itemHtml = (n) => {
        const { icon, color } = LEVEL_ICONS[n.level] ?? LEVEL_ICONS.info;
        const unread = !n.read_at;
        const body = `
            <p class="text-xs text-foreground leading-4.5">${Utils.escapeHtml(n.message)}</p>
            <p class="text-[11px] text-secondary-foreground mt-0.5">${relativeTime(n.created_at)}</p>`;

        return `
            <div class="flex items-start gap-2 px-3 py-2.5 ${unread ? 'bg-accent/30' : ''}">
                <i class="ki-filled ${icon} ${color} text-sm mt-0.5 shrink-0" aria-hidden="true"></i>
                ${n.url
                    ? `<a href="${Utils.escapeHtml(n.url)}" class="min-w-0 flex-1 hover:underline" data-notification-link="${n.id}" data-unread="${unread}">${body}</a>`
                    : `<div class="min-w-0 flex-1">${body}</div>`}
                ${unread ? `<button type="button" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm shrink-0" data-mark-read="${n.id}" title="Marcar como lida" aria-label="Marcar como lida"><i class="ki-filled ki-check text-xs" aria-hidden="true"></i></button>` : ''}
            </div>`;
    };

    const render = ({ unread_count: unreadCount, notifications }) => {
        setUnread(unreadCount);
        list.innerHTML = notifications.length === 0
            ? '<div class="px-3 py-4 text-xs text-secondary-foreground text-center">Nenhuma notificação.</div>'
            : notifications.map(itemHtml).join('');
    };

    const showError = () => {
        list.innerHTML = '<div class="px-3 py-4 text-xs text-destructive text-center">Não foi possível carregar as notificações.</div>';
    };

    const loadList = () => Utils.http(notificationsUrl).then(render).catch(showError);

    const loadCount = () => Utils.http(unreadCountUrl)
        .then(({ unread_count: count }) => setUnread(count))
        .catch(() => {}); // sem contador o sino só fica sem a bolinha

    const markAsRead = (id) => Utils.http(`${notificationsReadUrl}/${id}/read`, { method: 'POST' });

    const handleListClick = async (e) => {
        const markButton = e.target.closest('[data-mark-read]');
        if (markButton) {
            await markAsRead(markButton.dataset.markRead).catch(() => {});
            loadList();
            return;
        }

        // Link da notificação: marca como lida antes de sair, mas nunca bloqueia a navegação.
        const link = e.target.closest('[data-notification-link]');
        if (link && link.dataset.unread === 'true') {
            e.preventDefault();
            await markAsRead(link.dataset.notificationLink).catch(() => {});
            location.href = link.href;
        }
    };

    const handleReadAll = async () => {
        await Utils.http(readAllUrl, { method: 'POST' }).catch(() => {});
        loadList();
    };

    return {
        init: () => {
            if (initialized) return;

            ({ notificationsUrl, notificationsReadUrl, unreadCountUrl, readAllUrl } = window.pageConfig ?? {});
            toggle = document.getElementById('notificationsToggle');
            badge = document.getElementById('notificationBadge');
            list = document.getElementById('notificationsList');
            readAllButton = document.getElementById('notificationsReadAll');

            if (!notificationsUrl || !toggle) return;

            initialized = true;

            list.addEventListener('click', handleListClick);
            readAllButton.addEventListener('click', handleReadAll);
            toggle.addEventListener('click', loadList);
            loadCount();
        },
    };
})();

export default Notifications;

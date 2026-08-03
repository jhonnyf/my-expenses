import Utils from '../utils';

const MESSAGE_BUILDERS = {
    'App\\Notifications\\FavoriteProductPriceDropped': (data) =>
        `<strong>${Utils.escapeHtml(data.product_name)}</strong> caiu para R$ ${Utils.formatCurrency(data.new_price)} em ${Utils.escapeHtml(data.issuer_name)} (${Utils.escapeHtml(data.city)}/${Utils.escapeHtml(data.state)}).`,
};

const Notifications = (() => {
    let initialized = false;
    let notificationsUrl, notificationsReadUrl;
    let toggle, badge, list;

    const buildMessage = (notification) => {
        const builder = MESSAGE_BUILDERS[notification.type];
        return builder ? builder(notification.data) : 'Você tem uma nova notificação.';
    };

    const render = ({ unread_count: unreadCount, notifications }) => {
        badge.classList.toggle('hidden', unreadCount === 0);

        if (notifications.length === 0) {
            list.innerHTML = '<div class="px-3 py-4 text-xs text-secondary-foreground text-center">Nenhuma notificação.</div>';
            return;
        }

        list.innerHTML = notifications.map(n => `
            <div class="flex items-start gap-2 px-3 py-2.5 ${!n.read_at ? 'bg-accent/30' : ''}" data-notification-id="${n.id}">
                <i class="ki-filled ki-price-tag text-primary text-sm mt-0.5 shrink-0"></i>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-foreground leading-4.5">${buildMessage(n)}</p>
                    <p class="text-[11px] text-secondary-foreground mt-0.5">${new Date(n.created_at).toLocaleDateString('pt-BR')}</p>
                </div>
                ${!n.read_at ? `<button type="button" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm shrink-0" data-mark-read="${n.id}" title="Marcar como lida"><i class="ki-filled ki-check text-xs"></i></button>` : ''}
            </div>`).join('');
    };

    const load = () => {
        Utils.http(notificationsUrl).then(render);
    };

    const markAsRead = async (id) => {
        await Utils.http(`${notificationsReadUrl}/${id}/read`, { method: 'POST' });
        load();
    };

    const handleListClick = (e) => {
        const btn = e.target.closest('[data-mark-read]');
        if (btn) markAsRead(btn.dataset.markRead);
    };

    return {
        init: () => {
            if (initialized) return;

            notificationsUrl = window.pageConfig?.notificationsUrl;
            notificationsReadUrl = window.pageConfig?.notificationsReadUrl;
            toggle = document.getElementById('notificationsToggle');
            badge = document.getElementById('notificationBadge');
            list = document.getElementById('notificationsList');

            if (!notificationsUrl || !toggle) return;

            initialized = true;

            list.addEventListener('click', handleListClick);
            toggle.addEventListener('click', load);
            load();
        }
    };
})();

export default Notifications;

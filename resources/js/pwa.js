const Pwa = (() => {
    // Em dev, o service worker cacheia agressivamente (cache-first) e sobrevive
    // a rebuilds — atrapalha ver mudanças de JS/CSS no navegador. Só faz
    // sentido em staging/production.
    const isLocalEnv = () => document.body.dataset.appEnv === 'local';

    const unregisterServiceWorker = () => {
        if (!('serviceWorker' in navigator)) return;

        window.addEventListener('load', () => {
            navigator.serviceWorker.getRegistrations().then((registrations) => {
                registrations.forEach((registration) => registration.unregister());
            });

            if ('caches' in window) {
                caches.keys().then((keys) => keys.forEach((key) => caches.delete(key)));
            }
        });
    };

    const registerServiceWorker = () => {
        if (!('serviceWorker' in navigator)) return;

        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((error) => {
                console.error('Falha ao registrar o service worker:', error);
            });
        });
    };

    const init = () => {
        if (isLocalEnv()) {
            unregisterServiceWorker();
            return;
        }

        registerServiceWorker();
    };

    return { init };
})();

export default Pwa;

const Pwa = (() => {
    const registerServiceWorker = () => {
        if (!('serviceWorker' in navigator)) return;

        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((error) => {
                console.error('Falha ao registrar o service worker:', error);
            });
        });
    };

    const init = () => {
        registerServiceWorker();
    };

    return { init };
})();

export default Pwa;

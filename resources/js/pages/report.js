const Report = (() => {
    let initialized = false;
    let generateUrl;

    const submitTo = (url) => {
        const form = document.getElementById('reportForm');
        form.action = url;
        form.submit();
        form.action = generateUrl;
    };

    const handleClick = (e) => {
        const btn = e.target.closest('[data-action="submit-report"]');
        if (btn) submitTo(btn.dataset.url);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ generateUrl } = window.pageConfig);

            document.addEventListener('click', handleClick);
        }
    };
})();

export default Report;

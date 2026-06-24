export default function init() {
    const { generateUrl } = window.pageConfig;

    window.submitTo = (url) => {
        const form = document.getElementById('reportForm');
        form.action = url;
        form.submit();
        form.action = generateUrl;
    };
}

export default function init() {
    var config = window.pageConfig;

    window.submitTo = function (url) {
        var form = document.getElementById('reportForm');
        form.action = url;
        form.submit();
        form.action = config.generateUrl;
    };
}

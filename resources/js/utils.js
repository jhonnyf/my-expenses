import axios from 'axios';

const Utils = (() => {
    const http = (url, { method = 'GET', body } = {}) => {
        return axios({ url, method, data: body }).then(r => r.data);
    };

    const formatCurrency = (value) => {
        return parseFloat(value).toFixed(2).replace('.', ',');
    };

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    return { http, formatCurrency, escapeHtml };
})();

export default Utils;

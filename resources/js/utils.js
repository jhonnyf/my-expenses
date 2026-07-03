import axios from 'axios';

const Utils = (() => {
    const http = (url, { method = 'GET', body } = {}) => {
        return axios({ url, method, data: body }).then(r => r.data);
    };

    const formatCurrency = (value) => {
        return parseFloat(value).toFixed(2).replace('.', ',');
    };

    return { http, formatCurrency };
})();

export default Utils;

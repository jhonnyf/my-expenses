import axios from 'axios';

export function http(url, { method = 'GET', body } = {}) {
    return axios({ url, method, data: body }).then(r => r.data);
}

export function formatCurrency(value) {
    return parseFloat(value).toFixed(2).replace('.', ',');
}

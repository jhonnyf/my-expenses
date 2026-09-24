import Utils from '../utils';

const IssuerDetail = (() => {
    let initialized = false;

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            Utils.renderMonthlyBars('issuerMonthlyChart', window.pageConfig?.issuerMonthly);
        }
    };
})();

export default IssuerDetail;

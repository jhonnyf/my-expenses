import Utils from '../utils';

const CategoryDetail = (() => {
    let initialized = false;

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            Utils.initPeriodFilter();
            Utils.renderMonthlyBars('categoryMonthlyChart', window.pageConfig?.categoryMonthly);
        }
    };
})();

export default CategoryDetail;

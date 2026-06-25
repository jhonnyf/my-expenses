import './bootstrap';

import budgetInit from './pages/budget';
import categoryInit from './pages/category';
import dashboardInit from './pages/dashboard';
import globalSearchInit from './pages/global-search';
import invoiceDetailInit from './pages/invoice-detail';
import issuerFavoriteInit from './pages/issuer-favorite';
import priceHistoryInit from './pages/price-history';
import recurringPurchaseInit from './pages/recurring-purchase';
import reportInit from './pages/report';
import shoppingListInit from './pages/shopping-list';
import uploadInit from './pages/upload';

const pages = {
    'budget': budgetInit,
    'category': categoryInit,
    'dashboard': dashboardInit,
    'global-search': globalSearchInit,
    'invoice-detail': invoiceDetailInit,
    'issuer-favorite': issuerFavoriteInit,
    'price-history': priceHistoryInit,
    'recurring-purchase': recurringPurchaseInit,
    'report': reportInit,
    'shopping-list': shoppingListInit,
    'upload': uploadInit,
};

document.addEventListener('DOMContentLoaded', () => {
    globalSearchInit();

    const pageAttr = document.body.dataset.page;
    if (!pageAttr) return;

    pageAttr.split(',').forEach(name => {
        const initFn = pages[name.trim()];
        if (initFn) initFn();
    });
});

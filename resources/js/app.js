import './bootstrap';

import Account from './pages/account';
import Budget from './pages/budget';
import Category from './pages/category';
import Dashboard from './pages/dashboard';
import GlobalSearch from './pages/global-search';
import InvoiceDetail from './pages/invoice-detail';
import IssuerDetail from './pages/issuer-detail';
import IssuerFavorite from './pages/issuer-favorite';
import IssuerList from './pages/issuer-list';
import IssuerNickname from './pages/issuer-nickname';
import MyPurchases from './pages/my-purchases';
import Notifications from './pages/notifications';
import PriceComparison from './pages/price-comparison';
import PriceHistory from './pages/price-history';
import ProductAlias from './pages/product-alias';
import RecurringPurchase from './pages/recurring-purchase';
import Report from './pages/report';
import ShoppingList from './pages/shopping-list';
import Upload from './pages/upload';
import Pwa from './pwa';
import Utils from './utils';

const pages = {
    'account': Account,
    'budget': Budget,
    'category': Category,
    'dashboard': Dashboard,
    'global-search': GlobalSearch,
    'invoice-detail': InvoiceDetail,
    'issuer-detail': IssuerDetail,
    'issuer-favorite': IssuerFavorite,
    'issuer-list': IssuerList,
    'issuer-nickname': IssuerNickname,
    'my-purchases': MyPurchases,
    'price-comparison': PriceComparison,
    'price-history': PriceHistory,
    'product-alias': ProductAlias,
    'recurring-purchase': RecurringPurchase,
    'report': Report,
    'shopping-list': ShoppingList,
    'upload': Upload,
};

document.addEventListener('DOMContentLoaded', () => {
    Pwa.init();
    GlobalSearch.init();
    Notifications.init();

    if (window.pageConfig?.favoriteProductToggleUrl) {
        Utils.initFavoriteProduct(window.pageConfig.favoriteProductToggleUrl);
    }

    if (window.pageConfig?.captureLocationUrl) {
        Utils.initLocationCapture(window.pageConfig.captureLocationUrl);
    }

    const pageAttr = document.body.dataset.page;
    if (!pageAttr) return;

    pageAttr.split(',').forEach(name => {
        pages[name.trim()]?.init();
    });
});

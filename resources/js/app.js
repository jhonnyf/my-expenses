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
import MyPurchases from './pages/my-purchases';
import PriceHistory from './pages/price-history';
import RecurringPurchase from './pages/recurring-purchase';
import Report from './pages/report';
import ShoppingList from './pages/shopping-list';
import Upload from './pages/upload';
import Pwa from './pwa';

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
    'my-purchases': MyPurchases,
    'price-history': PriceHistory,
    'recurring-purchase': RecurringPurchase,
    'report': Report,
    'shopping-list': ShoppingList,
    'upload': Upload,
};

document.addEventListener('DOMContentLoaded', () => {
    Pwa.init();
    GlobalSearch.init();

    const pageAttr = document.body.dataset.page;
    if (!pageAttr) return;

    pageAttr.split(',').forEach(name => {
        pages[name.trim()]?.init();
    });
});

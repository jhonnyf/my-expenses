import './bootstrap';

import Budget from './pages/budget';
import Category from './pages/category';
import Dashboard from './pages/dashboard';
import GlobalSearch from './pages/global-search';
import InvoiceDetail from './pages/invoice-detail';
import IssuerFavorite from './pages/issuer-favorite';
import MyPurchases from './pages/my-purchases';
import PriceHistory from './pages/price-history';
import RecurringPurchase from './pages/recurring-purchase';
import Report from './pages/report';
import ShoppingList from './pages/shopping-list';
import Upload from './pages/upload';

const pages = {
    'budget': Budget,
    'category': Category,
    'dashboard': Dashboard,
    'global-search': GlobalSearch,
    'invoice-detail': InvoiceDetail,
    'issuer-favorite': IssuerFavorite,
    'my-purchases': MyPurchases,
    'price-history': PriceHistory,
    'recurring-purchase': RecurringPurchase,
    'report': Report,
    'shopping-list': ShoppingList,
    'upload': Upload,
};

document.addEventListener('DOMContentLoaded', () => {
    GlobalSearch.init();

    const pageAttr = document.body.dataset.page;
    if (!pageAttr) return;

    pageAttr.split(',').forEach(name => {
        pages[name.trim()]?.init();
    });
});

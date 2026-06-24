import { http } from '../utils';

export default function init() {
    const { assignCategoryUrl } = window.pageConfig || {};

    window.assignCategory = (itemId, categoryId) => {
        http(assignCategoryUrl, {
            method: 'POST',
            body: { item_id: itemId, category_id: categoryId || null },
        });
    };
}

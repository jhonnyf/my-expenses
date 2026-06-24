export default function init() {
    var config = window.pageConfig || {};
    var assignCategoryUrl = config.assignCategoryUrl || '';
    var csrfToken = config.csrfToken || '';

    window.assignCategory = function (itemId, categoryId) {
        fetch(assignCategoryUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                category_id: categoryId || null,
            }),
        });
    };
}

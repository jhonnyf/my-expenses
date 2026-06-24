export default function init() {
    var CSRF = window.pageConfig.csrfToken;
    var ADD_URL = window.pageConfig.addToListUrl;

    window.toggleDropdown = function (btn) {
        var dropdown = btn.nextElementSibling;
        document.querySelectorAll('.absolute.end-0').forEach(function (d) {
            if (d !== dropdown) d.classList.add('hidden');
        });
        dropdown.classList.toggle('hidden');
    };

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.relative')) {
            document.querySelectorAll('.absolute.end-0').forEach(function (d) {
                d.classList.add('hidden');
            });
        }
    });

    window.addToList = function (listId, description, unitPrice, issuerId, unit, btn) {
        fetch(ADD_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                shopping_list_id: listId,
                description: description,
                unit_price: unitPrice,
                issuer_id: issuerId,
                unit: unit || null,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var wrapper = btn.closest('.relative');
                var dd = wrapper.querySelector('div:not(.hidden)');
                if (dd) dd.classList.add('hidden');
                var check = document.createElement('span');
                check.className = 'text-green-500 text-sm';
                check.innerHTML = '<i class="ki-filled ki-check"></i>';
                wrapper.parentElement.appendChild(check);
            }
        });
    };
}

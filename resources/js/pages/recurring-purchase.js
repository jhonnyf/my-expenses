import { http } from '../utils';

export default function init() {
    const { addToListUrl } = window.pageConfig;

    window.toggleDropdown = (btn) => {
        const dropdown = btn.nextElementSibling;
        document.querySelectorAll('.absolute.end-0').forEach(d => {
            if (d !== dropdown) d.classList.add('hidden');
        });
        dropdown.classList.toggle('hidden');
    };

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.relative')) {
            document.querySelectorAll('.absolute.end-0').forEach(d => d.classList.add('hidden'));
        }
    });

    window.addToList = (listId, description, unitPrice, issuerId, unit, btn) => {
        http(addToListUrl, {
            method: 'POST',
            body: {
                shopping_list_id: listId,
                description,
                unit_price: unitPrice,
                issuer_id: issuerId,
                unit: unit || null,
            },
        }).then(data => {
            if (!data.success) return;

            const wrapper = btn.closest('.relative');
            const dd = wrapper.querySelector('div:not(.hidden)');
            if (dd) dd.classList.add('hidden');

            const check = document.createElement('span');
            check.className = 'text-green-500 text-sm';
            check.innerHTML = '<i class="ki-filled ki-check"></i>';
            wrapper.parentElement.appendChild(check);
        });
    };
}

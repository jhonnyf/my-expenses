const Upload = (() => {
    let initialized = false;

    // Delega para o próprio toggle da aba (data-kt-tab-toggle), que já cuida de
    // mostrar/esconder o painel e aplicar as classes de estado ativo via KTUI.
    const switchTab = (tab) => {
        document.querySelector(`[data-kt-tab-toggle="#panel-${tab}"]`)?.click();
    };

    const clearFieldError = (form, field) => {
        form.querySelector(`[name="${field}"]`)?.removeAttribute('aria-invalid');
        form.querySelector('[data-js-error]')?.remove();
    };

    // O CSS do kt-form-message só fica visível quando o .kt-form-item ancestral
    // contém um campo com aria-invalid="true" (regra :has() em styles.css) —
    // sem isso, o elemento existe no DOM mas continua com display:none.
    const showFieldError = (form, field, message) => {
        clearFieldError(form, field);

        const input = form.querySelector(`[name="${field}"]`);
        input?.setAttribute('aria-invalid', 'true');

        const container = input?.closest('.kt-form-item') ?? form;
        const el = document.createElement('div');
        el.dataset.jsError = 'true';
        el.className = 'kt-form-message text-destructive';
        el.textContent = message;
        container.appendChild(el);
    };

    // Submete via fetch para não recarregar a página quando a importação falha
    // (chave inválida, nota duplicada, SEFAZ fora do ar etc.) — o erro aparece
    // no próprio formulário. Sem JS, o form ainda funciona via POST clássico.
    const initQrCodeForm = () => {
        const form = document.querySelector('#panel-qrcode form');
        const field = 'qrcode_url';
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearFieldError(form, field);

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Importando...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                });

                const data = await response.json();

                if (!response.ok) {
                    const message = data.errors?.[field]?.[0] ?? data.message ?? 'Não foi possível importar a nota.';
                    showFieldError(form, field, message);
                    return;
                }

                window.location.href = data.redirect;
            } catch (error) {
                showFieldError(form, field, 'Erro de conexão. Tente novamente.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            const { initialTab } = window.pageConfig || {};
            if (initialTab && initialTab !== 'qrcode') {
                switchTab(initialTab);
            }

            initQrCodeForm();
        }
    };
})();

export default Upload;

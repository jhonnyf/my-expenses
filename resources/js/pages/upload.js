import QrScanner from 'qr-scanner';

const QR_ERROR_MESSAGES = {
    NotAllowedError: 'Permissão de câmera negada. Habilite o acesso à câmera nas configurações do navegador.',
    NotFoundError: 'Nenhuma câmera foi encontrada neste dispositivo.',
    NotReadableError: 'Não foi possível acessar a câmera. Ela pode estar em uso por outro aplicativo.',
};

const Upload = (() => {
    let initialized = false;
    let scanner = null;
    let distanceHintTimer = null;

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
    // Reutilizado tanto pelo submit manual quanto pelo callback da leitura por câmera.
    const submitQrCodeForm = async (form) => {
        const field = 'qrcode_url';
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
                return false;
            }

            window.location.href = data.redirect;
            return true;
        } catch (error) {
            showFieldError(form, field, 'Erro de conexão. Tente novamente.');
            return false;
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    };

    const initQrCodeForm = () => {
        const form = document.querySelector('#panel-qrcode form');
        if (!form) return;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitQrCodeForm(form);
        });
    };

    const clearDistanceHintTimer = () => {
        clearTimeout(distanceHintTimer);
        distanceHintTimer = null;
    };

    const resetScannerUi = () => {
        clearDistanceHintTimer();

        document.getElementById('qrScannerError').classList.add('hidden');
        document.getElementById('qrScannerFocusControl').classList.add('hidden');
        document.getElementById('qrScannerDistanceHint').classList.add('hidden');
        document.getElementById('qrScannerCaptureBtn').classList.add('hidden');
        document.getElementById('qrScannerCaptureFeedback').classList.add('hidden');

        const statusEl = document.getElementById('qrScannerStatus');
        statusEl.classList.remove('hidden');
        statusEl.textContent = 'Aponte a câmera para o QR Code do cupom fiscal.';
    };

    // QR Codes de cupom fiscal costumam falhar por falta de foco, principalmente
    // no iPhone (Safari não expõe nenhum controle de foco via Web API). Depois de
    // alguns segundos sem sucesso, sugerimos afastar o código da câmera — foco
    // "macro" (bem de perto) costuma ser o modo menos confiável nas câmeras.
    const scheduleDistanceHint = () => {
        clearDistanceHintTimer();
        distanceHintTimer = setTimeout(() => {
            document.getElementById('qrScannerDistanceHint').classList.remove('hidden');
        }, 6000);
    };

    const showScannerError = (message) => {
        document.getElementById('qrScannerStatus').classList.add('hidden');

        const errorEl = document.getElementById('qrScannerError');
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    };

    const handleQrDecoded = async (result) => {
        clearDistanceHintTimer();
        scanner?.stop();

        document.getElementById('qrScannerStatus').textContent = 'QR Code detectado, importando...';

        const form = document.querySelector('#panel-qrcode form');
        document.getElementById('qrcode_url').value = result.data;

        const ok = await submitQrCodeForm(form);

        // Em sucesso, submitQrCodeForm já redireciona a página. Em erro, fecha
        // o modal para o usuário ver a mensagem no formulário por trás.
        if (!ok) {
            window.KTModal?.getInstance(document.getElementById('qrScannerModal'))?.hide();
        }
    };

    // Se o dispositivo expõe controle manual de foco com faixa de distância
    // (focusDistance em getCapabilities — hoje praticamente só Chrome/Android),
    // mostra um slider para o usuário ajustar o foco na mão. Isso ajuda quando
    // o cupom fica muito perto da lente e o foco automático/macro não converge.
    const setupVariableFocus = (video, track, capabilities) => {
        const control = document.getElementById('qrScannerFocusControl');
        const range = document.getElementById('qrScannerFocusRange');
        const focusDistance = capabilities?.focusDistance;

        if (!capabilities?.focusMode?.includes('manual') || !focusDistance) {
            control.classList.add('hidden');
            return;
        }

        range.min = focusDistance.min;
        range.max = focusDistance.max;
        range.step = focusDistance.step ?? 'any';
        range.value = track.getSettings?.().focusDistance ?? focusDistance.min;

        range.oninput = () => {
            track.applyConstraints({
                advanced: [{ focusMode: 'manual', focusDistance: Number(range.value) }],
            }).catch(() => {});
        };

        control.classList.remove('hidden');
    };

    // QR Codes de cupom fiscal costumam ser lidos bem de perto; a câmera
    // traseira do celular normalmente foca longe por padrão, então tentamos
    // travar o foco em modo macro/contínuo assim que a track fica disponível.
    // getCapabilities()/focusMode ainda são experimentais (suporte real só em
    // Chrome/Android), por isso cada tentativa é isolada e silenciosa.
    const enableCloseFocus = async (video) => {
        const track = video.srcObject?.getVideoTracks?.()[0];
        const capabilities = track?.getCapabilities?.();
        const focusModes = capabilities?.focusMode ?? [];

        setupVariableFocus(video, track, capabilities);

        if (focusModes.includes('macro')) {
            try {
                await track.applyConstraints({ advanced: [{ focusMode: 'macro' }] });
                return;
            } catch (error) {
                // segue para o fallback abaixo
            }
        }

        if (focusModes.includes('continuous')) {
            try {
                await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
            } catch (error) {
                // sem suporte real do dispositivo, mantém o foco automático padrão
            }
        }
    };

    // Pede uma resolução maior à câmera depois que o stream já está ativo — a lib
    // não expõe essa opção no construtor (só preferredCamera). Quadros maiores
    // ajudam o decoder com QR Codes pequenos mesmo sem foco perfeito. Melhor
    // esforço: se o dispositivo não suportar, mantém a resolução já negociada.
    const enableHigherResolution = async (video) => {
        const track = video.srcObject?.getVideoTracks?.()[0];
        if (!track) return;

        try {
            await track.applyConstraints({ width: { ideal: 1920 }, height: { ideal: 1080 } });
        } catch (error) {
            // silencioso, mesma convenção usada em enableCloseFocus
        }
    };

    const showCaptureFeedback = (message) => {
        const feedback = document.getElementById('qrScannerCaptureFeedback');
        feedback.textContent = message;
        feedback.classList.remove('hidden');
        setTimeout(() => feedback.classList.add('hidden'), 4000);
    };

    // Tira uma foto parada em vez de usar o frame de vídeo contínuo. ImageCapture
    // dispara um ciclo de autofoco+captura igual ao app de câmera nativo — ao
    // contrário de grabFrame(), que só pega o frame atual do buffer sem refocar.
    // É o principal ganho esperado no iPhone, onde a Web API não dá nenhum
    // controle de foco sobre o preview de vídeo contínuo.
    const capturePhoto = async (video) => {
        const btn = document.getElementById('qrScannerCaptureBtn');
        const track = video.srcObject?.getVideoTracks?.()[0];
        if (!track || btn.disabled) return;

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-loading animate-spin"></i> Focando...';

        try {
            const imageCapture = new ImageCapture(track);
            const blob = await imageCapture.takePhoto();
            const result = await QrScanner.scanImage(blob, { returnDetailedScanResult: true });

            await handleQrDecoded(result);
        } catch (error) {
            // Cobre tanto QrScanner.NO_QR_CODE_FOUND (string) quanto falhas reais
            // de takePhoto(). Não paramos o scanner contínuo, que segue tentando.
            showCaptureFeedback('Não conseguimos identificar o QR Code na foto. Tente novamente ou ajuste a distância.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    };

    const setupPhotoCapture = (video) => {
        const btn = document.getElementById('qrScannerCaptureBtn');

        if (!window.ImageCapture) {
            btn.classList.add('hidden');
            return;
        }

        btn.classList.remove('hidden');
        btn.onclick = () => capturePhoto(video);
    };

    const startCamera = async () => {
        resetScannerUi();

        const video = document.getElementById('qrScannerVideo');
        scanner = new QrScanner(video, handleQrDecoded, {
            preferredCamera: 'environment',
            highlightScanRegion: true,
            highlightCodeOutline: true,
        });

        try {
            await scanner.start();
            await enableCloseFocus(video);
            await enableHigherResolution(video);
            setupPhotoCapture(video);
            scheduleDistanceHint();
        } catch (error) {
            showScannerError(QR_ERROR_MESSAGES[error?.name] ?? 'Não foi possível iniciar a câmera. Tente novamente.');
        }
    };

    const stopCamera = () => {
        clearDistanceHintTimer();
        scanner?.stop();
        scanner?.destroy();
        scanner = null;
    };

    const initQrScannerModal = () => {
        const modal = document.getElementById('qrScannerModal');
        const triggerBtn = document.getElementById('qrScannerTriggerBtn');
        if (!modal || !triggerBtn) return;

        // Sem suporte a getUserMedia (browser antigo ou contexto inseguro fora
        // de https/localhost): esconde o botão da câmera, mantém o input manual.
        if (!navigator.mediaDevices?.getUserMedia) {
            triggerBtn.remove();
            return;
        }

        modal.addEventListener('shown', startCamera);
        modal.addEventListener('hidden', stopCamera);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            initQrCodeForm();
            initQrScannerModal();
        }
    };
})();

export default Upload;

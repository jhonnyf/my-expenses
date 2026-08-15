import { BrowserQRCodeReader, BrowserCodeReader } from '@zxing/browser';
import { NotFoundException, ChecksumException, FormatException } from '@zxing/library';

const QR_ERROR_MESSAGES = {
    NotAllowedError: 'Permissão de câmera negada. Habilite o acesso à câmera nas configurações do navegador.',
    NotFoundError: 'Nenhuma câmera foi encontrada neste dispositivo.',
    NotReadableError: 'Não foi possível acessar a câmera. Ela pode estar em uso por outro aplicativo.',
};

const Upload = (() => {
    let initialized = false;
    let codeReader = null;
    let controls = null;
    let distanceHintTimer = null;
    let currentDeviceId = null;
    let videoInputDevices = [];
    let overlayCtx = null;

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
        document.getElementById('qrScannerSwitchCameraBtn').classList.add('hidden');

        const statusEl = document.getElementById('qrScannerStatus');
        statusEl.classList.remove('hidden');
        statusEl.textContent = 'Aponte a câmera para o QR Code do cupom fiscal.';

        clearOverlay();
    };

    // Redimensiona o canvas de overlay para casar com o tamanho CSS exibido do
    // vídeo (não a resolução do stream) e reobserva mudanças de layout do modal.
    const setupOverlayCanvas = (video) => {
        const canvas = document.getElementById('qrScannerOverlay');
        overlayCtx = canvas?.getContext('2d') ?? null;
        if (!canvas) return;

        const resize = () => {
            canvas.width = video.clientWidth;
            canvas.height = video.clientHeight;
        };
        resize();

        video._qrResizeObserver?.disconnect();
        video._qrResizeObserver = new ResizeObserver(resize);
        video._qrResizeObserver.observe(video);
    };

    // result.getResultPoints() vem em pixels da resolução real do stream
    // (video.videoWidth/videoHeight), não do tamanho CSS exibido. Como o vídeo
    // usa object-cover dentro de um container quadrado, é preciso replicar
    // manualmente a matemática de crop do object-cover para mapear os pontos
    // para as coordenadas do canvas exibido.
    const drawOverlay = (video, points) => {
        if (!overlayCtx || !points?.length) return;

        const canvas = overlayCtx.canvas;
        overlayCtx.clearRect(0, 0, canvas.width, canvas.height);

        const videoW = video.videoWidth;
        const videoH = video.videoHeight;
        if (!videoW || !videoH) return;

        const elW = canvas.width;
        const elH = canvas.height;
        const videoRatio = videoW / videoH;
        const elRatio = elW / elH;

        let scale;
        let offsetX = 0;
        let offsetY = 0;

        if (videoRatio > elRatio) {
            scale = elH / videoH;
            offsetX = (videoW * scale - elW) / 2;
        } else {
            scale = elW / videoW;
            offsetY = (videoH * scale - elH) / 2;
        }

        overlayCtx.strokeStyle = '#22c55e';
        overlayCtx.lineWidth = 3;
        overlayCtx.beginPath();
        points.forEach((point, index) => {
            const x = point.getX() * scale - offsetX;
            const y = point.getY() * scale - offsetY;
            index === 0 ? overlayCtx.moveTo(x, y) : overlayCtx.lineTo(x, y);
        });
        overlayCtx.closePath();
        overlayCtx.stroke();
    };

    const clearOverlay = () => {
        if (!overlayCtx) return;
        overlayCtx.clearRect(0, 0, overlayCtx.canvas.width, overlayCtx.canvas.height);
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
        controls?.stop();
        controls = null;
        clearOverlay();

        document.getElementById('qrScannerStatus').textContent = 'QR Code detectado, importando...';

        const form = document.querySelector('#panel-qrcode form');
        document.getElementById('qrcode_url').value = result.getText();

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
            const bitmap = await createImageBitmap(blob);
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            canvas.getContext('2d').drawImage(bitmap, 0, 0);

            const result = codeReader.decodeFromCanvas(canvas);

            await handleQrDecoded(result);
        } catch (error) {
            // decodeFromCanvas lança de forma síncrona quando não acha QR na
            // imagem (NotFoundException e afins) — é o caso normal aqui, não um
            // erro real. Não paramos o scanner contínuo, que segue tentando.
            const isNoQrFound = error instanceof NotFoundException
                || error instanceof ChecksumException
                || error instanceof FormatException;

            if (!isNoQrFound) {
                console.error('[upload] erro ao capturar foto do QR Code:', error);
            }

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

    // ZXing chama o callback a cada frame, não só em sucesso. NotFoundException/
    // ChecksumException/FormatException disparam sem QR válido no frame — é
    // ruído esperado durante o scan contínuo, não um erro real (por isso não
    // viram console.error, ao contrário de qualquer outra exceção inesperada).
    const startDecoding = async (video, deviceIdOrConstraints) => {
        const onResult = (result, error, scanControls) => {
            controls = scanControls;

            if (result) {
                drawOverlay(video, result.getResultPoints());
                handleQrDecoded(result);
                return;
            }

            clearOverlay();

            const isNoQrFound = error instanceof NotFoundException
                || error instanceof ChecksumException
                || error instanceof FormatException;

            if (error && !isNoQrFound) {
                console.error('[upload] erro inesperado ao decodificar frame do QR Code:', error);
            }
        };

        controls = typeof deviceIdOrConstraints === 'string'
            ? await codeReader.decodeFromVideoDevice(deviceIdOrConstraints, video, onResult)
            : await codeReader.decodeFromConstraints(deviceIdOrConstraints, video, onResult);

        currentDeviceId = video.srcObject?.getVideoTracks?.()[0]?.getSettings?.().deviceId ?? null;
    };

    // Só faz sentido alternar câmera se o dispositivo expõe mais de uma. Em
    // iOS Safari e alguns navegadores, listVideoInputDevices() pode falhar ou
    // devolver uma lista incompleta sem prompt de permissão — nesse caso o
    // botão simplesmente permanece escondido, sem afetar o scanner contínuo.
    const setupCameraSwitch = async (video) => {
        const btn = document.getElementById('qrScannerSwitchCameraBtn');

        try {
            videoInputDevices = await BrowserCodeReader.listVideoInputDevices();
            if (videoInputDevices.length < 2) {
                btn.classList.add('hidden');
                return;
            }
        } catch (error) {
            btn.classList.add('hidden');
            return;
        }

        btn.classList.remove('hidden');
        btn.onclick = () => switchCamera(video);
    };

    // ZXing não tem equivalente a setCamera() num stream vivo: trocar de
    // câmera exige parar a decodificação atual e reiniciar apontando para o
    // próximo deviceId da lista (índice circular). Como o stream é recriado
    // do zero, os ajustes que dependem da track (foco macro, resolução,
    // captura por foto) precisam ser refeitos para a nova câmera.
    const switchCamera = async (video) => {
        const btn = document.getElementById('qrScannerSwitchCameraBtn');
        if (videoInputDevices.length < 2 || btn.disabled) return;

        const currentIndex = videoInputDevices.findIndex((device) => device.deviceId === currentDeviceId);
        const nextDevice = videoInputDevices[(currentIndex + 1) % videoInputDevices.length];
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-loading animate-spin"></i> Alternando...';

        try {
            controls?.stop();
            controls = null;
            clearOverlay();

            await startDecoding(video, nextDevice.deviceId);

            await enableCloseFocus(video);
            await enableHigherResolution(video);
            setupPhotoCapture(video);
        } catch (error) {
            // Dispositivo pode não ter de fato a câmera seguinte disponível
            // (ex.: listVideoInputDevices() contou uma câmera virtual/duplicada).
            // A tentativa já parou o stream anterior, então voltamos para a
            // câmera original em vez de deixar o modal sem nenhum stream ativo.
            showCaptureFeedback('Não foi possível alternar a câmera neste dispositivo.');

            try {
                await startDecoding(video, currentDeviceId ?? { video: { facingMode: { ideal: 'environment' } } });
                await enableCloseFocus(video);
                await enableHigherResolution(video);
                setupPhotoCapture(video);
            } catch (restoreError) {
                console.error('[upload] falha ao restaurar câmera após troca malsucedida:', restoreError);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    };

    // Tudo aqui dentro do try, incluindo resetScannerUi e a criação do
    // BrowserQRCodeReader: startCamera roda como callback do evento 'shown' do
    // modal, sem ninguém para dar await/catch nela — um erro fora do try vira
    // uma rejeição de Promise não tratada, silenciosa (tela preta sem nenhum
    // feedback). decodeFromConstraints (não decodeFromVideoDevice) é usado na
    // primeira chamada porque os labels de MediaDeviceInfo só ficam disponíveis
    // depois da primeira concessão de permissão — não dá pra escolher a câmera
    // traseira por deviceId/heurística de label antes disso.
    const startCamera = async () => {
        const video = document.getElementById('qrScannerVideo');

        try {
            resetScannerUi();
            setupOverlayCanvas(video);

            codeReader = new BrowserQRCodeReader();
            await startDecoding(video, { video: { facingMode: { ideal: 'environment' } } });

            await enableCloseFocus(video);
            await enableHigherResolution(video);
            setupPhotoCapture(video);
            await setupCameraSwitch(video);
            scheduleDistanceHint();
        } catch (error) {
            console.error('[upload] erro ao iniciar câmera do QR Code:', error);
            showScannerError(QR_ERROR_MESSAGES[error?.name] ?? 'Não foi possível iniciar a câmera. Tente novamente.');
        }
    };

    const stopCamera = () => {
        clearDistanceHintTimer();
        controls?.stop();
        controls = null;
        codeReader = null;
        currentDeviceId = null;
        videoInputDevices = [];
        clearOverlay();
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

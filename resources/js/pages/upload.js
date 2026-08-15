import { BrowserQRCodeReader, BrowserCodeReader } from '@zxing/browser';
import { NotFoundException, ChecksumException, FormatException, DecodeHintType } from '@zxing/library';

const QR_ERROR_MESSAGES = {
    NotAllowedError: 'Permissão de câmera negada. Habilite o acesso à câmera nas configurações do navegador.',
    NotFoundError: 'Nenhuma câmera foi encontrada neste dispositivo.',
    NotReadableError: 'Não foi possível acessar a câmera. Ela pode estar em uso por outro aplicativo.',
};

const SCAN_INTERVAL_MS = 300;

// iOS Safari expõe labels descritivos por lente ("Back Camera" = principal,
// "Back Ultra Wide Camera"/"Back Telephoto Camera" = as outras) — dá pra
// acertar com certeza. Android geralmente só indica o lado (ex.: "camera2 0,
// facing back" / "camera2 1, facing front"), sem indicar qual lente — o
// máximo que dá pra fazer ali é confirmar que é traseira e evitar uma lente
// que o próprio label já denuncia como ultra-wide/telephoto.
const FRONT_CAMERA_LABEL = /front|user|frontal/i;
const BACK_CAMERA_HINT_LABEL = /back|rear|traseira|environment/i;
const MAIN_BACK_CAMERA_LABEL = /^back camera$/i;
const EXCLUDED_LENS_LABEL = /ultra[\s-]?wide|wide[\s-]?angle|telephoto|\btele\b|periscope/i;

const Upload = (() => {
    let initialized = false;
    let codeReader = null;
    let mediaStream = null;
    let scanLoopHandle = null;
    let scanCanvas = null;
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

    // video.videoWidth/videoHeight (resolução real do stream) quase nunca bate
    // com o tamanho CSS exibido, e o vídeo usa object-cover dentro de um
    // container quadrado — é preciso replicar manualmente a matemática de crop
    // do object-cover para converter entre os dois sistemas de coordenadas.
    // Usado tanto pra desenhar o overlay quanto pra calcular a região de recorte.
    const getVideoToCanvasTransform = (video, canvas) => {
        const videoW = video.videoWidth;
        const videoH = video.videoHeight;
        if (!videoW || !videoH) return null;

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

        return { scale, offsetX, offsetY };
    };

    // points vêm em pixels do RECORTE decodificado (não do frame inteiro), por
    // isso somamos cropRegion.x/y antes de converter para coordenadas do canvas
    // de overlay (que cobre o vídeo inteiro).
    const drawOverlay = (video, points, cropRegion) => {
        const canvas = overlayCtx?.canvas;
        if (!overlayCtx || !canvas || !points?.length) return;

        overlayCtx.clearRect(0, 0, canvas.width, canvas.height);

        const transform = getVideoToCanvasTransform(video, canvas);
        if (!transform) return;
        const { scale, offsetX, offsetY } = transform;

        overlayCtx.strokeStyle = '#22c55e';
        overlayCtx.lineWidth = 3;
        overlayCtx.beginPath();
        points.forEach((point, index) => {
            const videoX = point.getX() + (cropRegion?.x ?? 0);
            const videoY = point.getY() + (cropRegion?.y ?? 0);
            const x = videoX * scale - offsetX;
            const y = videoY * scale - offsetY;
            index === 0 ? overlayCtx.moveTo(x, y) : overlayCtx.lineTo(x, y);
        });
        overlayCtx.closePath();
        overlayCtx.stroke();
    };

    const clearOverlay = () => {
        if (!overlayCtx) return;
        overlayCtx.clearRect(0, 0, overlayCtx.canvas.width, overlayCtx.canvas.height);
    };

    // Lê a posição real da caixa estática (#qrScannerScanBox) em vez de embutir
    // o inset-8 do Tailwind como número mágico no JS — se o layout mudar, não
    // precisa lembrar de sincronizar os dois lugares.
    const getScanRegionInVideoSpace = (video) => {
        const canvas = overlayCtx?.canvas;
        if (!canvas) return null;

        const transform = getVideoToCanvasTransform(video, canvas);
        if (!transform) return null;
        const { scale, offsetX, offsetY } = transform;

        const videoRect = video.getBoundingClientRect();
        const boxRect = document.getElementById('qrScannerScanBox').getBoundingClientRect();
        const boxX = boxRect.left - videoRect.left;
        const boxY = boxRect.top - videoRect.top;

        return {
            x: (boxX + offsetX) / scale,
            y: (boxY + offsetY) / scale,
            width: boxRect.width / scale,
            height: boxRect.height / scale,
        };
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
        stopCameraCapture();
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

    // Os labels só ficam populados depois de uma permissão já concedida nesta
    // origem. Na primeiríssima vez, abrimos um stream só pra liberar os labels
    // e paramos na hora — ele nunca é anexado ao <video>, então o usuário nunca
    // chega a ver a lente errada antes da troca.
    const resolveBackCameraDeviceId = async () => {
        let devices = await BrowserCodeReader.listVideoInputDevices();

        if (devices.length && devices.every((device) => !device.label)) {
            const probe = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
            probe.getTracks().forEach((track) => track.stop());
            devices = await BrowserCodeReader.listVideoInputDevices();
        }

        videoInputDevices = devices;

        // Só considera candidatas as que o label afirma serem traseiras. Sem
        // essa checagem, um label genérico (ex.: Android sem info de lente)
        // podia "passar" no filtro de exclusão de ultra-wide/tele mesmo sendo
        // a câmera frontal — foi exatamente o que causou a frontal abrir por
        // padrão num Samsung Android.
        const backCandidates = devices.filter((device) => device.label
            && BACK_CAMERA_HINT_LABEL.test(device.label)
            && !FRONT_CAMERA_LABEL.test(device.label));

        if (!backCandidates.length) {
            // Nenhum label indicando o lado — não dá pra garantir que não é a
            // frontal. Melhor deixar facingMode:environment decidir (nunca
            // escolhe a frontal) do que arriscar abrir a câmera errada.
            return null;
        }

        const exact = backCandidates.find((device) => MAIN_BACK_CAMERA_LABEL.test(device.label.trim()));
        if (exact) return exact.deviceId;

        const notExcluded = backCandidates.find((device) => !EXCLUDED_LENS_LABEL.test(device.label));
        return notExcluded?.deviceId ?? backCandidates[0].deviceId;
    };

    const openCameraStream = async (video, deviceId) => {
        const constraints = {
            video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } },
        };

        mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = mediaStream;
        await video.play();

        currentDeviceId = mediaStream.getVideoTracks()[0]?.getSettings?.().deviceId ?? deviceId ?? null;
    };

    // Recorta o frame na área do overlay visível antes de decodificar (em vez
    // de analisar o vídeo inteiro, que é o que decodeFromVideoDevice/
    // decodeFromConstraints fariam) — reduz fundo/ruído na imagem analisada e
    // aumenta a proporção do QR nela, ajudando bastante a taxa de acerto do
    // ZXing mesmo com o QR já bem focado.
    const attemptDecode = (video) => {
        if (video.readyState < video.HAVE_CURRENT_DATA) return;

        const region = getScanRegionInVideoSpace(video);
        if (!region || region.width <= 0 || region.height <= 0) return;

        if (!scanCanvas) scanCanvas = document.createElement('canvas');
        scanCanvas.width = Math.round(region.width);
        scanCanvas.height = Math.round(region.height);
        scanCanvas.getContext('2d').drawImage(
            video,
            region.x, region.y, region.width, region.height,
            0, 0, scanCanvas.width, scanCanvas.height,
        );

        try {
            const result = codeReader.decodeFromCanvas(scanCanvas);
            drawOverlay(video, result.getResultPoints(), region);
            handleQrDecoded(result);
        } catch (error) {
            clearOverlay();

            const isNoQrFound = error instanceof NotFoundException
                || error instanceof ChecksumException
                || error instanceof FormatException;

            if (!isNoQrFound) {
                console.error('[upload] erro inesperado ao decodificar frame do QR Code:', error);
            }
        }
    };

    const startScanLoop = (video) => {
        stopScanLoop();
        scanLoopHandle = setInterval(() => attemptDecode(video), SCAN_INTERVAL_MS);
    };

    const stopScanLoop = () => {
        clearInterval(scanLoopHandle);
        scanLoopHandle = null;
    };

    const stopMediaStream = () => {
        mediaStream?.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    };

    const stopCameraCapture = () => {
        stopScanLoop();
        stopMediaStream();
    };

    // Só faz sentido alternar câmera se o dispositivo expõe mais de uma — a
    // lista já foi resolvida em resolveBackCameraDeviceId, então aqui só
    // decide se mostra o botão.
    const setupCameraSwitch = (video) => {
        const btn = document.getElementById('qrScannerSwitchCameraBtn');
        if (videoInputDevices.length < 2) {
            btn.classList.add('hidden');
            return;
        }
        btn.classList.remove('hidden');
        btn.onclick = () => switchCamera(video);
    };

    // ZXing não tem equivalente a setCamera() num stream vivo: trocar de
    // câmera exige parar o stream atual e abrir um novo apontando para o
    // próximo deviceId da lista (índice circular). Como o stream é recriado
    // do zero, os ajustes que dependem da track (foco macro, resolução,
    // captura por foto) precisam ser refeitos para a nova câmera.
    const switchCamera = async (video) => {
        const btn = document.getElementById('qrScannerSwitchCameraBtn');
        if (videoInputDevices.length < 2 || btn.disabled) return;

        const currentIndex = videoInputDevices.findIndex((device) => device.deviceId === currentDeviceId);
        const nextDevice = videoInputDevices[(currentIndex + 1) % videoInputDevices.length];
        const previousDeviceId = currentDeviceId;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ki-filled ki-loading animate-spin"></i> Alternando...';

        try {
            stopCameraCapture();
            clearOverlay();

            await openCameraStream(video, nextDevice.deviceId);
            startScanLoop(video);

            await enableCloseFocus(video);
            await enableHigherResolution(video);
            setupPhotoCapture(video);
        } catch (error) {
            // Dispositivo pode não ter de fato a câmera seguinte disponível
            // (ex.: listVideoInputDevices() contou uma câmera virtual/duplicada).
            // A tentativa já parou o stream anterior, então tentamos voltar
            // para a câmera original em vez de deixar o modal sem stream ativo.
            showCaptureFeedback('Não foi possível alternar a câmera neste dispositivo.');

            try {
                await openCameraStream(video, previousDeviceId);
                startScanLoop(video);
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

    const buildHints = () => {
        const hints = new Map();
        hints.set(DecodeHintType.TRY_HARDER, true);
        return hints;
    };

    // Tudo aqui dentro do try, incluindo resetScannerUi e a criação do
    // BrowserQRCodeReader: startCamera roda como callback do evento 'shown' do
    // modal, sem ninguém para dar await/catch nela — um erro fora do try vira
    // uma rejeição de Promise não tratada, silenciosa (tela preta sem nenhum
    // feedback).
    const startCamera = async () => {
        const video = document.getElementById('qrScannerVideo');

        try {
            resetScannerUi();
            setupOverlayCanvas(video);

            codeReader = new BrowserQRCodeReader(buildHints());

            const deviceId = await resolveBackCameraDeviceId();
            await openCameraStream(video, deviceId);

            await enableCloseFocus(video);
            await enableHigherResolution(video);
            setupPhotoCapture(video);
            setupCameraSwitch(video);
            scheduleDistanceHint();

            startScanLoop(video);
        } catch (error) {
            console.error('[upload] erro ao iniciar câmera do QR Code:', error);
            showScannerError(QR_ERROR_MESSAGES[error?.name] ?? 'Não foi possível iniciar a câmera. Tente novamente.');
        }
    };

    const stopCamera = () => {
        clearDistanceHintTimer();
        stopCameraCapture();
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

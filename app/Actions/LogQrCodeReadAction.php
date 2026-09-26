<?php

namespace App\Actions;

use App\Models\QrCodeRead;

class LogQrCodeReadAction
{
    private const KEY_PREFIX_LENGTH = 20;

    public function execute(
        ?int $userId,
        string $qrcodeUrl,
        bool $success,
        ?string $errorMessage = null,
        ?int $invoiceId = null,
    ): QrCodeRead {
        return QrCodeRead::create([
            'user_id' => $userId,
            'qrcode_url' => $this->redact($qrcodeUrl),
            'status' => $success ? 'success' : 'error',
            'error_message' => $errorMessage,
            'invoice_id' => $invoiceId,
        ]);
    }

    /**
     * A URL do QR carrega a chave de acesso inteira (identifica uma compra de uma pessoa). O log guarda só o
     * suficiente para depurar um portal: host, caminho e o início da chave (UF, mês, CNPJ do emitente).
     */
    private function redact(string $qrcodeUrl): string
    {
        $parts = parse_url($qrcodeUrl);
        $base = isset($parts['host'])
            ? ($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? '')
            : 'url-invalida';

        return preg_match('/\d{44}/', urldecode($parts['query'] ?? ''), $key)
            ? $base.'?chave='.substr($key[0], 0, self::KEY_PREFIX_LENGTH).'…'
            : $base;
    }
}

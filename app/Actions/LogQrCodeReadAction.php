<?php

namespace App\Actions;

use App\Models\QrCodeRead;

class LogQrCodeReadAction
{
    public function execute(
        ?int $userId,
        string $qrcodeUrl,
        bool $success,
        ?string $errorMessage = null,
        ?int $invoiceId = null,
    ): QrCodeRead {
        return QrCodeRead::create([
            'user_id' => $userId,
            'qrcode_url' => $qrcodeUrl,
            'status' => $success ? 'success' : 'error',
            'error_message' => $errorMessage,
            'invoice_id' => $invoiceId,
        ]);
    }
}

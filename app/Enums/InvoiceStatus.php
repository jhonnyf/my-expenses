<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Authorized = 'authorized';
    case Pending = 'pending';
    case Expired = 'expired';

    public const PENDING_EXPIRATION_DAYS = 7;

    public function label(): string
    {
        return match ($this) {
            self::Authorized => 'Autorizada',
            self::Pending => 'Pendente de autorização',
            self::Expired => 'Não confirmada',
        };
    }

    /** Frase curta, para tooltip de badge. */
    public function hint(): ?string
    {
        return match ($this) {
            self::Authorized => null,
            self::Pending => 'Nota em contingência: aguardando autorização da SEFAZ. Não entra nos totais.',
            self::Expired => 'Não conseguimos confirmar esta nota. Não entra nos totais.',
        };
    }

    /** Explicação completa, para a tela de detalhe e para a API. */
    public function description(): ?string
    {
        return match ($this) {
            self::Authorized => null,
            self::Pending => 'Nota em contingência. Quando o sistema da SEFAZ está fora do ar ou a loja está sem internet, '
                .'o estabelecimento emite a nota mesmo assim e a envia para autorização depois. '
                .'Por isso ainda não temos os itens e valores confirmados. '
                .'Assim que ela for autorizada, completamos os dados automaticamente — você não precisa fazer nada. '
                .'Enquanto isso, ela não entra nos seus totais.',
            self::Expired => 'Nota não confirmada. Não conseguimos confirmar esta nota em '.self::PENDING_EXPIRATION_DAYS.' dias. '
                .'Você pode tentar importar de novo pelo QR Code ou pelo XML quando ela estiver autorizada.',
        };
    }
}

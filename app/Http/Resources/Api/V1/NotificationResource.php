<?php

namespace App\Http\Resources\Api\V1;

use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...app(NotificationPresenter::class)->present($this->resource),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            // Compatibilidade com o app publicado, que ainda monta a mensagem a partir do tipo e dos dados.
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}

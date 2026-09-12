<?php

namespace App\Observers;

use App\Models\UserProfile;

class UserProfileObserver
{
    /**
     * cpf/cnpj usam o cast `encrypted` (IV aleatório, não determinístico), então
     * não dá para checar duplicidade neles diretamente — cpf_hash/cnpj_hash
     * (HMAC-SHA256, determinístico) cumprem esse papel.
     */
    public function saving(UserProfile $profile): void
    {
        if ($profile->isDirty('cpf')) {
            $profile->cpf_hash = $this->hash($profile->cpf);
        }

        if ($profile->isDirty('cnpj')) {
            $profile->cnpj_hash = $this->hash($profile->cnpj);
        }
    }

    private function hash(?string $document): ?string
    {
        return $document === null ? null : hash_hmac('sha256', $document, config('app.key'));
    }
}

<?php

return [
    /*
     * Único usuário autorizado a promover/rebaixar manualmente a assinatura
     * de outros usuários, enquanto não existe um gateway de pagamento real.
     */
    'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'jhonnyf7@gmail.com'),
];

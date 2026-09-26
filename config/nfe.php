<?php

return [
    'cnpj' => env('NFE_CNPJ'),
    'razao_social' => env('NFE_RAZAO_SOCIAL'),
    'uf' => env('NFE_UF', 'SP'),
    'ambiente' => env('NFE_AMBIENTE', 2), // 1=Produção | 2=Homologação
    'certificado_path' => env('NFE_CERTIFICADO_PATH', storage_path('app/private/certificado.pfx')),
    'certificado_senha' => env('NFE_CERTIFICADO_SENHA'),
    'csc' => env('NFE_CSC'),
    'csc_id' => env('NFE_CSC_ID'),

    // Portais fora do domínio da própria UF que também atendem NFC-e (ex.: SVRS atende vários estados).
    // Lista separada por vírgula em NFE_PORTAIS_COMPARTILHADOS: um portal legítimo rejeitado aparece no log ("Portal SEFAZ rejeitado").
    'portais_compartilhados' => array_filter(array_map('trim', explode(',', env('NFE_PORTAIS_COMPARTILHADOS', 'svrs.rs.gov.br,fazenda.gov.br')))),
];

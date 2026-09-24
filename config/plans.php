<?php

/*
 * Recursos por plano: fonte única da página de planos, da API (`GET /api/v1/subscription/plans`) e das
 * mensagens de paywall. A chave é a mesma usada em `pro:<chave>` nas rotas e em ProFeatureRequiredException.
 * `paywall` é a mensagem mostrada quando um usuário Grátis tenta usar o recurso.
 */
return [
    'features' => [
        'general_budget' => [
            'label' => 'Orçamento geral (sem categoria)',
            'free' => true,
        ],
        'multiple_budgets' => [
            'label' => 'Múltiplos orçamentos por categoria',
            'free' => false,
            'paywall' => 'Orçamentos por categoria são exclusivos do plano Pro. No plano Grátis você pode ter um orçamento geral.',
        ],
        'report_csv' => [
            'label' => 'Exportação de relatórios em CSV',
            'free' => false,
            'paywall' => 'A exportação de relatórios em CSV é exclusiva do plano Pro.',
        ],
        'report_pdf' => [
            'label' => 'Exportação de relatórios em PDF',
            'free' => false,
            'paywall' => 'A exportação de relatórios em PDF é exclusiva do plano Pro.',
        ],
        'report_email' => [
            'label' => 'Envio de relatórios por e-mail e agendamento recorrente',
            'free' => false,
            'paywall' => 'Enviar e agendar relatórios por e-mail é exclusivo do plano Pro.',
        ],
        'ai_suggestions' => [
            'label' => 'Sugestões via Inteligência Artificial (categorias e nomes de produto)',
            'free' => false,
            'paywall' => 'As sugestões por Inteligência Artificial são exclusivas do plano Pro.',
        ],
        'ai_categorization' => [
            'label' => 'Categorização de itens por Inteligência Artificial (automática e sob demanda)',
            'free' => false,
            'paywall' => 'A categorização por Inteligência Artificial é exclusiva do plano Pro.',
        ],
        'price_history' => [
            'label' => 'Histórico de preços por produto',
            'free' => false,
            'paywall' => 'O histórico de preços é exclusivo do plano Pro.',
        ],
        'price_comparison' => [
            'label' => 'Comparação de preços entre lojas e cidades',
            'free' => false,
            'paywall' => 'A comparação de preços entre lojas e cidades é exclusiva do plano Pro.',
        ],
        'recurring_purchases' => [
            'label' => 'Detecção de compras recorrentes',
            'free' => false,
            'paywall' => 'A detecção de compras recorrentes é exclusiva do plano Pro.',
        ],
    ],
];

<?php

return [
    /*
     * Permissões de sistema (usadas pelas policies e protegidas contra rename/delete).
     * Mantemos os nomes exatamente como estão hoje para compatibilidade com Spatie + Gate.
     */
    'system_permissions' => [
        ['name' => 'view any terrenos', 'module' => 'terrenos', 'action' => 'view_any'],
        ['name' => 'view terrenos', 'module' => 'terrenos', 'action' => 'view'],
        ['name' => 'create terrenos', 'module' => 'terrenos', 'action' => 'create'],
        ['name' => 'update terrenos', 'module' => 'terrenos', 'action' => 'update'],
        ['name' => 'delete terrenos', 'module' => 'terrenos', 'action' => 'delete'],
        ['name' => 'restore terrenos', 'module' => 'terrenos', 'action' => 'restore'],
        ['name' => 'export terrenos', 'module' => 'terrenos', 'action' => 'export'],

        ['name' => 'view any documentos', 'module' => 'documentos', 'action' => 'view_any'],
        ['name' => 'view documentos', 'module' => 'documentos', 'action' => 'view'],
        ['name' => 'create documentos', 'module' => 'documentos', 'action' => 'create'],
        ['name' => 'update documentos', 'module' => 'documentos', 'action' => 'update'],
        ['name' => 'delete documentos', 'module' => 'documentos', 'action' => 'delete'],

        ['name' => 'view any produtos', 'module' => 'produtos', 'action' => 'view_any'],
        ['name' => 'view produtos', 'module' => 'produtos', 'action' => 'view'],
        ['name' => 'create produtos', 'module' => 'produtos', 'action' => 'create'],
        ['name' => 'update produtos', 'module' => 'produtos', 'action' => 'update'],
        ['name' => 'delete produtos', 'module' => 'produtos', 'action' => 'delete'],
        ['name' => 'restore produtos', 'module' => 'produtos', 'action' => 'restore'],

        ['name' => 'view any proprietarios', 'module' => 'proprietarios', 'action' => 'view_any'],
        ['name' => 'view proprietarios', 'module' => 'proprietarios', 'action' => 'view'],
        ['name' => 'create proprietarios', 'module' => 'proprietarios', 'action' => 'create'],
        ['name' => 'update proprietarios', 'module' => 'proprietarios', 'action' => 'update'],
        ['name' => 'delete proprietarios', 'module' => 'proprietarios', 'action' => 'delete'],
        ['name' => 'restore proprietarios', 'module' => 'proprietarios', 'action' => 'restore'],

        ['name' => 'view any regionais', 'module' => 'regionais', 'action' => 'view_any'],
        ['name' => 'view regionais', 'module' => 'regionais', 'action' => 'view'],
        ['name' => 'create regionais', 'module' => 'regionais', 'action' => 'create'],
        ['name' => 'update regionais', 'module' => 'regionais', 'action' => 'update'],
        ['name' => 'delete regionais', 'module' => 'regionais', 'action' => 'delete'],
        ['name' => 'restore regionais', 'module' => 'regionais', 'action' => 'restore'],

        ['name' => 'view any corretores externos', 'module' => 'corretores_externos', 'action' => 'view_any'],
        ['name' => 'view corretores externos', 'module' => 'corretores_externos', 'action' => 'view'],
        ['name' => 'create corretores externos', 'module' => 'corretores_externos', 'action' => 'create'],
        ['name' => 'update corretores externos', 'module' => 'corretores_externos', 'action' => 'update'],
        ['name' => 'delete corretores externos', 'module' => 'corretores_externos', 'action' => 'delete'],
        ['name' => 'restore corretores externos', 'module' => 'corretores_externos', 'action' => 'restore'],

        ['name' => 'view any viabilidades', 'module' => 'viabilidades', 'action' => 'view_any'],
        ['name' => 'view viabilidades', 'module' => 'viabilidades', 'action' => 'view'],
        ['name' => 'create viabilidades', 'module' => 'viabilidades', 'action' => 'create'],
        ['name' => 'update viabilidades', 'module' => 'viabilidades', 'action' => 'update'],
        ['name' => 'delete viabilidades', 'module' => 'viabilidades', 'action' => 'delete'],
        ['name' => 'request approval viabilidades', 'module' => 'viabilidades', 'action' => 'request_approval'],
        ['name' => 'approve viabilidades', 'module' => 'viabilidades', 'action' => 'approve'],
        ['name' => 'restore viabilidades', 'module' => 'viabilidades', 'action' => 'restore'],
        ['name' => 'activate viabilidades', 'module' => 'viabilidades', 'action' => 'activate'],
        ['name' => 'duplicate viabilidades', 'module' => 'viabilidades', 'action' => 'duplicate'],
        ['name' => 'compare viabilidades', 'module' => 'viabilidades', 'action' => 'compare'],
        ['name' => 'generate dre viabilidades', 'module' => 'viabilidades', 'action' => 'generate_dre'],
        ['name' => 'recalculate viabilidades', 'module' => 'viabilidades', 'action' => 'recalculate'],
        ['name' => 'export viabilidades', 'module' => 'viabilidades', 'action' => 'export'],

        ['name' => 'view any projetos', 'module' => 'projetos', 'action' => 'view_any'],
        ['name' => 'view projetos', 'module' => 'projetos', 'action' => 'view'],
        ['name' => 'create projetos', 'module' => 'projetos', 'action' => 'create'],
        ['name' => 'update projetos', 'module' => 'projetos', 'action' => 'update'],
        ['name' => 'cancel projetos', 'module' => 'projetos', 'action' => 'cancel'],
        ['name' => 'mark ready projetos', 'module' => 'projetos', 'action' => 'mark_ready'],

        ['name' => 'view any terreno produtos', 'module' => 'terreno_produtos', 'action' => 'view_any'],
        ['name' => 'view terreno produtos', 'module' => 'terreno_produtos', 'action' => 'view'],
        ['name' => 'create terreno produtos', 'module' => 'terreno_produtos', 'action' => 'create'],
        ['name' => 'update terreno produtos', 'module' => 'terreno_produtos', 'action' => 'update'],
        ['name' => 'delete terreno produtos', 'module' => 'terreno_produtos', 'action' => 'delete'],
        ['name' => 'restore terreno produtos', 'module' => 'terreno_produtos', 'action' => 'restore'],

        ['name' => 'view any terreno status', 'module' => 'terreno_status', 'action' => 'view_any'],
        ['name' => 'view terreno status', 'module' => 'terreno_status', 'action' => 'view'],
        ['name' => 'create terreno status', 'module' => 'terreno_status', 'action' => 'create'],
        ['name' => 'update terreno status', 'module' => 'terreno_status', 'action' => 'update'],
        ['name' => 'delete terreno status', 'module' => 'terreno_status', 'action' => 'delete'],
        ['name' => 'restore terreno status', 'module' => 'terreno_status', 'action' => 'restore'],

        ['name' => 'view any legalizacoes', 'module' => 'legalizacoes', 'action' => 'view_any'],
        ['name' => 'view legalizacoes', 'module' => 'legalizacoes', 'action' => 'view'],
        ['name' => 'create legalizacoes', 'module' => 'legalizacoes', 'action' => 'create'],
        ['name' => 'update legalizacoes', 'module' => 'legalizacoes', 'action' => 'update'],
        ['name' => 'delete legalizacoes', 'module' => 'legalizacoes', 'action' => 'delete'],
        ['name' => 'sync gantt legalizacoes', 'module' => 'legalizacoes', 'action' => 'sync_gantt'],
        ['name' => 'recalcular progresso legalizacoes', 'module' => 'legalizacoes', 'action' => 'recalculate_progress'],

        ['name' => 'view any legalizacao etapas', 'module' => 'legalizacao_etapas', 'action' => 'view_any'],
        ['name' => 'view legalizacao etapas', 'module' => 'legalizacao_etapas', 'action' => 'view'],
        ['name' => 'create legalizacao etapas', 'module' => 'legalizacao_etapas', 'action' => 'create'],
        ['name' => 'update legalizacao etapas', 'module' => 'legalizacao_etapas', 'action' => 'update'],
        ['name' => 'delete legalizacao etapas', 'module' => 'legalizacao_etapas', 'action' => 'delete'],
        ['name' => 'reorder legalizacao etapas', 'module' => 'legalizacao_etapas', 'action' => 'reorder'],
    ],

    /*
     * Permissões legadas (mantidas por compatibilidade de dados; não são usadas pelas policies atuais).
     */
    'deprecated_permissions' => [
        'terrenos.view',
        'terrenos.create',
        'terrenos.edit',
        'terrenos.delete',
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',
        'viability.view',
        'viability.create',
        'viability.edit',
        'viability.delete',
        'reports.view',
        'reports.export',
        'settings.view',
        'settings.edit',
    ],
];

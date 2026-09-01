<?php
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '3.7.0');
    define('APP_VERSION_DATE', '01/09/2026');

    $appChangelog = [
        [
            'version' => '3.7.0',
            'date' => '01/09/2026',
            'title' => 'Novo visual institucional, catálogo de responsáveis técnicos e monitoramento',
            'badge' => 'Grande atualização',
            'badge_color' => '#0d5433',
            'badge_bg' => '#def2e6',
            'highlights' => [
                [
                    'icon' => 'fa-swatchbook',
                    'title' => 'Novo visual institucional',
                    'desc' => 'Formulário público e painel admin redesenhados, com identificação única para todos os serviços.',
                ],
                [
                    'icon' => 'fa-id-card-clip',
                    'title' => 'Catálogo de responsáveis técnicos',
                    'desc' => 'Perfil individual de cada engenheiro/arquiteto, com as obras vinculadas.',
                ],
                [
                    'icon' => 'fa-clipboard-list',
                    'title' => 'Nova tela de requerimento',
                    'desc' => 'Pendências, notas internas e histórico reunidos num só lugar.',
                ],
                [
                    'icon' => 'fa-magnifying-glass',
                    'title' => 'Busca ampliada',
                    'desc' => 'Agora encontra também por proprietário e responsável técnico.',
                ],
                [
                    'icon' => 'fa-gauge-high',
                    'title' => 'Dashboard reorganizado',
                    'desc' => 'Paginação de fila e histórico de concluídos no acervo.',
                ],
                [
                    'icon' => 'fa-bell',
                    'title' => 'Monitoramento automático',
                    'desc' => 'Erros de produção agora avisam a equipe por e-mail.',
                ],
            ],
            'more' => [
                'Prévia do conteúdo direto na listagem de requerimentos, no mesmo padrão da listagem de denúncias',
                'Denúncia com checklist de ocorrência específico por área (Meio Ambiente x Obras e Serviços Urbanos)',
                'Desmembramento: lote regular ou irregular, área total com opção "não sei", coluna de confrontações',
                'Responsável técnico com opção CTF além de CREA/CAU; habite-se com padrão popular e Corpo de Bombeiros',
                'Editor de documentos com histórico de versões de modelos e autosave de rascunho',
                'E-mails com prévia, reenvio seguro, validação de destinatário e evidência de envio/recebimento no log',
                'Aviso do remetente oficial de e-mail para prevenir golpes em nome da SEMA',
            ],
            'fixes' => [
                'Token CSRF agora é exigido em todo o fluxo de assinatura, recusa e coassinatura',
                'Carimbo de assinatura não sobrepõe mais o texto em documentos de várias páginas',
                'Exclusão de nota interna não reportava sucesso quando outro admin tentava excluir',
                'Barra superior do portal mais responsiva em telas pequenas',
                'Denúncia agora exige CPF e orienta a consulta ao final do envio',
                'Dados registrais do desmembramento voltaram a ser opcionais',
            ],
        ],
        [
            'version' => '3.0.0',
            'date' => '21/05/2026',
            'title' => 'Fluxo multi-setor, co-assinatura e documentos finais',
            'badge' => 'Grande atualização',
            'badge_color' => '#0d5433',
            'badge_bg' => '#def2e6',
            'changes' => [
                'Fluxo de processos dividido em 3 setores: Triagem (S1), Fiscalização de Obras (S2) e Revisão do Secretário (S3)',
                'Cada perfil (analista, fiscal, secretário) vê apenas a fila do seu setor',
                'Co-assinatura de documentos: múltiplos administradores podem assinar o mesmo documento',
                'Documentos finais enviados diretamente ao cidadão por e-mail com link seguro',
                'Cidadão pode visualizar e baixar o documento final sem precisar de login',
                'Modal de solicitação de assinatura entre setores com campo de mensagem',
                'Modos de assinatura: assinatura digital SEMA, linha manual ou sem assinatura',
                'Novos dashboards diferenciados para Fiscal e Secretário com painel de assinaturas pendentes',
                'Obrigatoriedade de troca de senha no primeiro acesso de novos usuários',
            ],
        ],
        [
            'version' => '2.1.0',
            'date' => '22/04/2026',
            'title' => 'Barra lateral modernizada + sistema de versões',
            'badge' => 'Novo',
            'badge_color' => '#0d5433',
            'badge_bg' => '#def2e6',
            'changes' => [
                'Design da barra lateral atualizado com novo visual',
                'Sistema de versão e changelog integrado ao painel admin',
                'Modal de novidades exibido uma vez por usuário por versão',
                'Histórico completo de atualizações acessível no menu lateral',
            ],
        ],
        [
            'version' => '2.0.0',
            'date' => '10/04/2026',
            'title' => 'Redesign da página de pagamento',
            'badge' => 'Destaque',
            'badge_color' => '#1e429f',
            'badge_bg' => '#e8effd',
            'changes' => [
                'Nova interface de pagamento alinhada com identidade visual SEMA',
                'Email de boleto com layout profissional renovado',
                'Notificação de pagamento movida para após o commit no banco',
            ],
        ],
        [
            'version' => '1.9.0',
            'date' => '05/04/2026',
            'title' => 'Ajustes no painel administrativo',
            'badge' => 'Admin',
            'badge_color' => '#7a5b00',
            'badge_bg' => '#fdf5d7',
            'changes' => [
                'Fluxo de papéis extras desabilitado temporariamente',
                'Aviso de release adicionado ao painel',
                'Telas de dados e relatórios simplificadas',
            ],
        ],
        [
            'version' => '1.8.0',
            'date' => '20/03/2026',
            'title' => 'Sistema de notificações e requerimentos',
            'badge' => 'Feature',
            'badge_color' => '#1e429f',
            'badge_bg' => '#e8effd',
            'changes' => [
                'Requerimentos e notificações refatorados',
                'Nova página de notificações do admin com central de eventos',
                'Contadores de não lidos exibidos na barra lateral',
            ],
        ],
    ];
}

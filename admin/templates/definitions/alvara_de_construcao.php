<?php
/**
 * Definição: Alvará de Construção
 *
 * Este array descreve a estrutura do documento. O DocumentBuilder percorre
 * os blocos e gera HTML com estilos inline via Components.
 *
 * Variáveis {{campo}} são preenchidas pelo ParecerService::preencherDados().
 */

return [
    'label'     => 'Alvará de Construção',
    'descricao' => 'Autorização para execução de obras de construção civil.',
    'icone'     => 'fa-hard-hat',
    'badge'     => 'Construção',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'ALVARÁ DE CONSTRUÇÃO',
            'subtexto' => 'Nº {{numero_documento_ano}}',
        ],

        // ── Seção 1: Proprietário ────────────────────────────
        [
            'tipo'  => 'secao',
            'texto' => '1. IDENTIFICAÇÃO DO PROPRIETÁRIO',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Nome',     '{{nome_proprietario}}'],
                ['CPF/CNPJ', '{{cpf_cnpj_proprietario}}'],
            ],
        ],

        // ── Seção 2: Responsável Técnico ─────────────────────
        [
            'tipo'  => 'secao',
            'texto' => '2. RESPONSABILIDADE TÉCNICA',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Responsável Técnico', '{{responsavel_tecnico_nome}}'],
                ['Registro',            'N° {{responsavel_tecnico_registro}}'],
            ],
        ],

        // ── Seção 3: Dados da Obra ───────────────────────────
        [
            'tipo'  => 'secao',
            'texto' => '3. DADOS DA OBRA',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Endereço da Obra',     '{{endereco_objetivo}}'],
                ['Protocolo',            '{{protocolo}}'],
                ['Área Construída',      '{{area_construida}} m²'],
                ['Cadastro Imobiliário', '{{detalhes_imovel}}'],
                ['ART',                  '{{responsavel_tecnico_tipo_documento}} Nº {{responsavel_tecnico_numero}}'],
            ],
        ],

        // ── Seção 4: Especificação ───────────────────────────
        [
            'tipo'  => 'secao',
            'texto' => '4. ESPECIFICAÇÃO',
        ],
        [
            'tipo'     => 'texto',
            'conteudo' => '<p>{{especificacao}}</p>',
        ],

        // ── Condicionantes ───────────────────────────────────
        [
            'tipo'   => 'condicionantes',
            'titulo' => 'CONDICIONANTES:',
            'itens'  => [
                'A obra deve seguir os padrões básicos da construção civil, respeitando as boas práticas da engenharia.',
                'Executar vergas e contravergas sob e sobre todos os vãos de portas e janelas.',
                'Impermeabilização das fundações e reservatórios.',
                'Os resíduos da construção civil devem ter destinação final ambientalmente adequada.',
            ],
        ],

        // ── Fechamento ───────────────────────────────────────
        ['tipo' => 'data_local'],
    ],
];

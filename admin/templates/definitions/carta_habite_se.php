<?php
return [
    'label'     => 'Carta de Habite-se',
    'descricao' => 'Certificado de conclusão e habitabilidade da edificação.',
    'icone'     => 'fa-home',
    'badge'     => 'Habite-se',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'CARTA DE HABITE-SE',
            'subtexto' => 'N° {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Nº PROCESSO: {{protocolo}}',
        ],

        [
            'tipo'  => 'secao',
            'texto' => '1. IDENTIFICAÇÃO DO PROPRIETÁRIO',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Proprietário do Imóvel', '{{nome_proprietario}}'],
                ['CPF/CNPJ',              '{{cpf_cnpj_proprietario}}'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => '2. DADOS DO IMÓVEL E DA OBRA',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Endereço da Obra',       '{{endereco_objetivo}}'],
                ['Área Construída',        '{{area_construida}} m²'],
                ['Cadastro Imobiliário',   '{{detalhes_imovel}}'],
                ['Alvará de Construção',   'N° {{numero_documento_ano}}'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => '3. RESPONSABILIDADE TÉCNICA',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Responsável Técnico',    '{{responsavel_tecnico_nome}}'],
                ['Registro Profissional',  '{{responsavel_tecnico_registro}}'],
                ['ART/RRT/TRT',            '{{responsavel_tecnico_tipo_documento}} Nº {{responsavel_tecnico_numero}}'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => '4. ESPECIFICAÇÃO / LAUDO DO ENGENHEIRO TÉCNICO',
        ],
        [
            'tipo'     => 'texto',
            'conteudo' => '<p>{{especificacao}}</p>',
        ],

        ['tipo' => 'data_local', 'data' => '{{data_atual}}'],
        ['tipo' => 'assinatura'],
    ],
];

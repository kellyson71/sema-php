<?php
return [
    'label'     => 'Alvará de Desmembramento',
    'descricao' => 'Autorização para desmembramento de lote urbano.',
    'icone'     => 'fa-vector-square',
    'badge'     => 'Desmembramento',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'ÁLVARÁ DE DESMEMBRAMENTO',
            'subtexto' => 'N° {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'PROTOCOLO: {{protocolo}}',
        ],
        [
            'tipo'     => 'texto',
            'conteudo' => '<p>Fica autorizado o DESMEMBRAMENTO conforme descrito abaixo, localizado em {{endereco_objetivo}}, pertencente a <strong>{{nome_proprietario}}</strong>, CPF: {{cpf_cnpj_proprietario}}.</p>',
        ],

        [
            'tipo'  => 'secao',
            'texto' => '1. DADOS DO IMÓVEL',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Proprietário',            '{{nome_proprietario}}'],
                ['CPF/CNPJ',                '{{cpf_cnpj_proprietario}}'],
                ['Endereço / Localização',   '{{endereco_objetivo}}'],
                ['Descrição do Lote',        '{{detalhes_imovel}}'],
                ['ART de Desmembramento',    '{{responsavel_tecnico_tipo_documento}} Nº {{responsavel_tecnico_numero}}'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => '2. FUNDAMENTAÇÃO LEGAL E CONDIÇÕES',
        ],
        [
            'tipo'     => 'texto',
            'conteudo' => '<p>O desmembramento é autorizado em conformidade com a Lei N° 6.766 de 19 de dezembro de 1979 e parecer técnico da Secretaria de Meio Ambiente deste município.</p><p>{{especificacao}}</p>',
        ],

        ['tipo' => 'data_local'],
        ['tipo' => 'assinatura', 'cargo' => 'SECRETÁRIO MUNICIPAL DE MEIO AMBIENTE – SEMA.<br>PORTARIA 10/2025'],
    ],
];

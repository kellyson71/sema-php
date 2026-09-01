<?php
return [
    'label'     => 'Parecer Técnico Ambiental — Alvará de Construção',
    'descricao' => 'Parecer técnico ambiental para emissão de alvará de construção.',
    'icone'     => 'fa-leaf',
    'badge'     => 'Construção',

    'blocos' => [
        [
            'tipo'  => 'titulo',
            'texto' => 'Parecer Técnico Ambiental para Alvará de Construção',
        ],

        [
            'tipo'  => 'dados_inline',
            'dados' => [
                ['Interessado',        '{{nome_proprietario}}'],
                ['CPF/CNPJ',           '{{cpf_cnpj_proprietario}}'],
                ['Endereço do Imóvel', '{{endereco_objetivo}}'],
                ['Objeto',             'Requerimento de Alvará de Construção'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'I – RELATÓRIO',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'O presente parecer tem como objetivo a análise técnica ambiental para emissão de Alvará de Construção, de uma edificação residencial unifamiliar de pavimento térreo com <strong>{{area_construida}}</strong> m² de área a ser construída, de acordo com <strong>ART. Nº {{art_numero}}</strong>.',
                'Trata-se de projeto de edificação a ser implantado em zona urbana consolidada do Município de Pau dos Ferros/RN, cujas documentações e informações técnicas foram devidamente protocoladas junto à Prefeitura.',
                'A análise visa aferir a conformidade ambiental da obra pretendida com base na legislação federal, estadual e municipal vigente, em especial no que diz respeito ao uso e ocupação do solo, regularidade ambiental e ausência de impacto significativo ao meio ambiente.',
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'II – CONCLUSÃO',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'A obra proposta não se insere entre aquelas de impacto ambiental regional ou estadual, não havendo necessidade de submissão ao IDEMA (órgão ambiental estadual), conforme selecionado.',
                'No que diz respeito à Lei 016/2021, Plano Diretor de Pau dos Ferros, uma edificação do tipo residencial, situa-se no bairro indicado no endereço, caracterizado por expansões de empreendimentos. Nesta perspectiva, em relação à localização da construção, atesta-se que esta insere-se em perímetro urbano, fora de área de preservação permanente, inexistindo entraves à edificação.',
                'No âmbito sanitário, destaca-se, mediante análise de planta conforme construído, que estas encontram-se dentro das recomendações da NBR 7229/1992 que regulamenta projeto, construção e operação do sistema de tanques sépticos.',
                'Diante do exposto, verifica-se que a construção atende integralmente aos critérios da legislação federal, estadual e municipal, estando em conformidade com as disposições legais e técnicas expressas para fins de habitabilidade.',
                'Assim, opina-se favoravelmente pelo <strong>DEFERIMENTO</strong> do Alvará de <strong>CONSTRUÇÃO</strong>, conforme requerimento do interessado, autorizando a ocupação e uso regular do imóvel nos termos da legislação vigente.',
            ],
        ],
    ],
];

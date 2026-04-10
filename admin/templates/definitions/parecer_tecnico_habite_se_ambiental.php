<?php
return [
    'label'     => 'Parecer Técnico Ambiental — Habite-se',
    'descricao' => 'Parecer técnico ambiental para emissão de habite-se.',
    'icone'     => 'fa-leaf',
    'badge'     => 'Habite-se',

    'blocos' => [
        [
            'tipo'  => 'titulo',
            'texto' => 'Parecer Técnico Ambiental para Alvará de Habite-se',
        ],

        [
            'tipo'  => 'dados_inline',
            'dados' => [
                ['Interessado(a)',     '{{nome_proprietario}}'],
                ['CPF/CNPJ',          '{{cpf_cnpj_proprietario}}'],
                ['Endereço do Imóvel', '{{endereco_objetivo}}'],
                ['Objeto',             'Requerimento de Habite-se'],
                ['Município',          'Pau dos Ferros/RN'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'I – RELATÓRIO',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'O presente parecer tem por objetivo a análise técnica ambiental da solicitação de emissão de alvará de habite-se para uma edificação residencial de pavimento térreo e de <strong>{{area_construida}}</strong> m² de área construída, situado no endereço supracitado, conforme ART Nº <strong>{{art_numero}}</strong>.',
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'II – CONCLUSÃO',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'O empreendimento em análise não se enquadra nas tipologias que exigem licenciamento ambiental estadual, conforme verificado junto ao IDEMA (Instituto de Desenvolvimento Sustentável e Meio Ambiente do Rio Grande do Norte), tratando-se de obra de impacto local, cuja competência para análise e aprovação é municipal.',
                'No que concerne à Lei 016/2021, Plano Diretor de Pau dos Ferros, a edificação do tipo residencial situa-se no bairro indicado no endereço, este caracterizado por expansões de empreendimentos dessa natureza. Nesta perspectiva, em relação à localização da residência, atesta-se que esta se insere em perímetro urbano, fora de área de preservação permanente, inexistindo entraves à edificação.',
                'No âmbito sanitário, destaca-se que, mediante análise de planta as built, estas encontram-se dentro das recomendações da NBR 7229/1992 que regulamenta projeto, construção e operação de sistemas de tanques sépticos.',
                'A edificação possui área construída de <strong>{{area_construida}}</strong> m², constituído por {{especificacao}}.',
                'Diante do exposto, verifica-se que o imóvel atende integralmente às exigências da legislação federal, estadual e municipal, estando em conformidade com os parâmetros legais e técnicos exigidos para fins de habitabilidade.',
                'Assim, opina-se favoravelmente pelo <strong>DEFERIMENTO</strong> do requerimento de habite-se, conforme requerimento do interessado, autorizando a ocupação e uso regular do imóvel nos termos da legislação vigente.',
            ],
        ],
    ],
];

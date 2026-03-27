<?php
return [
    'label'     => 'Parecer Técnico — Licença de Atividade Econômica',
    'descricao' => 'Parecer técnico de viabilidade ambiental para licença de atividade econômica.',
    'icone'     => 'fa-store',
    'badge'     => 'Licença',

    'blocos' => [
        [
            'tipo'  => 'titulo',
            'texto' => 'Parecer Técnico<br>Viabilidade Ambiental para Fins de Licença de Atividade Econômica',
        ],

        [
            'tipo'  => 'dados_inline',
            'dados' => [
                ['Interessado', '{{nome_interessado}}'],
                ['CPF',         '{{cpf_interessado}}'],
                ['Atividade',   '{{atividade}}'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'I – DOS FATOS',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Trata-se de solicitação de análise quanto à viabilidade ambiental para fins de emissão de licença de atividade econômica, conforme requerido por <strong>{{nome_interessado}}</strong>, CPF {{cpf_interessado}}, para o exercício da atividade com {{cnae_descricao}}, {{endereco_objetivo}}.',
                'A atividade proposta foi avaliada à luz da legislação municipal vigente, especialmente a Lei Municipal nº 311, de 30 de dezembro de 1972, que dispõe sobre a obrigatoriedade do alvará de funcionamento de atividades econômicas no município de Pau dos Ferros-RN, bem como a compatibilidade da atividade com o zoneamento estabelecido e os impactos ambientais decorrentes de sua operação.',
                'Após análise do local pretendido e da natureza da atividade, verifica-se que:',
                'A atividade está classificada como de baixo impacto ambiental;',
                'O local encontra-se em zona urbana compatível com a atividade pretendida, conforme previsto na referida Lei;',
                'Não há indícios de passivos ambientais ou restrições legais ao funcionamento do empreendimento no local indicado.',
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'II – DA ANÁLISE TÉCNICA',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Considerando a documentação apresentada, conclui-se que a atividade proposta não representa risco significativo ao meio ambiente urbano, estando em conformidade com os critérios definidos na Lei Municipal nº 311/1972.',
                'Não foram identificadas incompatibilidades entre a atividade pretendida e a zona de uso onde se insere, tampouco a necessidade de licenciamento ambiental complementar em esfera estadual ou federal.',
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'III – DA CONCLUSÃO',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Diante do exposto, este parecer técnico <strong>OPINA FAVORAVELMENTE</strong> ao <strong>DEFERIMENTO</strong> do pedido de <strong>LICENÇA DE ATIVIDADE ECONÔMICA</strong>, por entender que a atividade pretendida é ambientalmente viável e está em conformidade com a legislação municipal, em especial com a Lei nº 311/1972.',
                'Recomenda-se, contudo, a observância contínua das normas ambientais e urbanísticas vigentes, bem como a adoção de boas práticas operacionais para garantir a não geração de impactos ao entorno.',
            ],
        ],
    ],
];

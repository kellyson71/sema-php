<?php
return [
    'label'     => 'Parecer Técnico Ambiental — Desmembramento',
    'descricao' => 'Parecer técnico ambiental para desmembramento de lote.',
    'icone'     => 'fa-leaf',
    'badge'     => 'Desmembramento',

    'blocos' => [
        [
            'tipo'  => 'titulo',
            'texto' => 'Parecer Técnico Ambiental',
        ],

        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Trata o presente parecer técnico sobre o <strong>REQUERIMENTO DE DESMEMBRAMENTO</strong> de um lote de <strong>{{area_lote}}</strong> m² de uma porção maior, localizados na <strong>{{endereco_objetivo}}</strong>, Pau dos Ferros – RN. Pertencente a <strong>{{nome_proprietario}}</strong>, CPF {{cpf_cnpj_proprietario}}, conforme TRT <strong>{{responsavel_tecnico_registro}}</strong>.',
                'O imóvel foi submetido à apreciação da Fiscal de meio ambiente, para análise e emissão de PARECER acerca das diretrizes que orientam e que regulamentam as edificações no âmbito da LEGISLAÇÃO AMBIENTAL no município de Pau dos Ferros RN, onde foi constatado que está em conformidade.',
                'Posto isto, cumpre-nos opinar pelo prosseguimento do processo de <strong>EXPEDIÇÃO DE ALVARÁ DE DESMEMBRAMENTO</strong>, por revestir-se de sustentação técnica legal.',
            ],
        ],
    ],
];

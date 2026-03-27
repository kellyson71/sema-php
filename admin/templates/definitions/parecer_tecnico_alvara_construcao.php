<?php
return [
    'label'     => 'Parecer Técnico — Alvará de Construção',
    'descricao' => 'Parecer técnico para emissão de alvará de construção.',
    'icone'     => 'fa-file-signature',
    'badge'     => 'Construção',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'PARECER TÉCNICO DE ALVARÁ DE CONSTRUÇÃO',
            'subtexto' => 'Nº {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Fundamentação Legal: Lei Municipal nº 017/2022 (Plano Diretor); 2117/2025 (Código Municipal de Obras); NBR 12721 e Instrução Normativa RFB nº 2.021/2021.',
        ],

        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Trata o presente parecer técnico sobre o <strong>REQUERIMENTO DE ALVARÁ DE CONSTRUÇÃO</strong> de uma edificação residencial unifamiliar de pavimento térreo e de <strong>{{area_construida}} m²</strong> de área que será construída, localizada na <strong>{{endereco_objetivo}}</strong> (Lote:, quadra:) bairro:, Pau dos Ferros/RN. Pertencente ao(à) <strong>{{nome_proprietario}}</strong> <strong>CPF/CNPJ ({{cpf_cnpj_proprietario}})</strong>, conforme <strong>{{responsavel_tecnico_tipo_documento}} Nº {{responsavel_tecnico_numero}}</strong>.',
                'O projeto para construção da edificação supracitada foi submetido à apreciação desta Assessoria Técnica para análise e emissão do PARECER acerca das diretrizes que orientam e que regulamentam as edificações no Município de Pau dos Ferros – RN.',
                'Após a análise de praxe, pude constatar que a referida edificação, encontra-se em conformidade com a legislação vigente no MUNICÍPIO DE PAU DOS FERROS – RN.',
                'De acordo com a NBR 12721 – Avaliação de custos de construção para incorporação imobiliária e outras disposições para condomínios edilícios, o projeto em análise se assemelha ao tipo Padrão Residencial – Baixo.',
                'De acordo com a INSTRUÇÃO NORMATIVA RFB Nº 2.021, DE 16 DE ABRIL DE 2021, inciso IX do caput do art. 7º, o projeto em análise assemelha-se à destinação Casa Popular.',
                'Isto posto, cumpre-me opinar pelo prosseguimento do processo de <strong>EXPEDIÇÃO DE ALVARÁ DE CONSTRUÇÃO</strong>, por revestir-se de sustentação técnica legal.',
            ],
        ],
    ],
];

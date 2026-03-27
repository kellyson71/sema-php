<?php
return [
    'label'     => 'Parecer Técnico — Habite-se',
    'descricao' => 'Parecer técnico para emissão de habite-se.',
    'icone'     => 'fa-file-signature',
    'badge'     => 'Habite-se',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'PARECER TÉCNICO DE HABITE-SE',
            'subtexto' => 'Nº {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Nº PROCESSO: {{protocolo}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Fundamentação Legal: Lei Municipal nº 017/2022 (Plano Diretor); 2117/2025 (Código Municipal de Obras); NBR 12721 e Instrução Normativa RFB nº 2.021/2021.',
        ],

        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Trata o presente parecer técnico sobre o <strong>REQUERIMENTO DE HABITE-SE</strong>, de uma edificação residencial com um pavimento térreo e de {{area}}m² de área construída localizado na {{endereco_objetivo}} Pau dos Ferros/RN. Pertencente ao (à) {{nome_proprietario}} CPF/CNPJ: {{cpf_cnpj_proprietario}}, conforme {{responsavel_tecnico_tipo_documento}} Nº {{responsavel_tecnico_numero}}.',
                'A edificação supracitada foi submetida à apreciação desta Assessoria Técnica para análise e emissão do PARECER acerca das diretrizes que orientam e que regulamentam as edificações no Município de Pau dos Ferros – RN.',
                'Após a análise de praxe, pude constatar que a referida edificação, encontra-se em conformidade com a legislação vigente no Município de Pau dos Ferros – RN e PROJETO APROVADO.',
                'De acordo com a NBR 12721 – Avaliação de custos de construção para incorporação imobiliária e outras disposições para condomínios edilícios, o projeto em análise se assemelha ao tipo Padrão Residencial - Baixo com {{area}}m².',
                'De acordo com a INSTRUÇÃO NORMATIVA RFB Nº 2.021, DE 16 DE ABRIL DE 2021, inciso IX do caput do art. 7º, o projeto em análise assemelha-se a destinação Casa Popular.',
                'Isto posto, cumpre-me opinar pelo prosseguimento do processo de <strong>EXPEDIÇÃO DE HABITE-SE</strong>, por revestir-se de sustentação técnica legal.',
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'DESCRIÇÃO DO IMÓVEL',
        ],
        [
            'tipo'     => 'texto',
            'conteudo' => '<p>A edificação possui área construída de {{area}} m² constituído por {{detalhes_imovel}}</p>',
        ],
    ],
];

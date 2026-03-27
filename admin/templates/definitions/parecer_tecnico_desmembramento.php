<?php
return [
    'label'     => 'Parecer Técnico — Desmembramento',
    'descricao' => 'Parecer técnico para emissão de alvará de desmembramento.',
    'icone'     => 'fa-file-signature',
    'badge'     => 'Desmembramento',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'PARECER TÉCNICO DE DESMEMBRAMENTO',
            'subtexto' => 'Nº {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Nº PROCESSO: {{protocolo}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Fundamentação Legal: Lei Municipal nº 017/2022 (Plano Diretor); Lei Federal nº 6.766/1979 e demais normas urbanísticas vigentes.',
        ],

        [
            'tipo'  => 'secao',
            'texto' => 'PARECER TÉCNICO',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Trata o presente parecer técnico sobre o <strong>REQUERIMENTO DE DESMEMBRAMENTO</strong>, de um lote de {{area_lote}} m² de uma porção maior, localizado na {{endereco_objetivo}} Pau dos Ferros/RN, pertencente {{nome_proprietario}}, CPF/CNPJ: {{cpf_cnpj_proprietario}}, conforme {{responsavel_tecnico_tipo_documento}} Nº {{responsavel_tecnico_numero}}.',
                'O requerimento em questão foi submetida à apreciação desta Assessoria Técnica para análise e emissão do PARECER acerca das diretrizes que orientam e que regulamentam as edificações no Município de Pau dos Ferros – RN.',
                'Após a análise de praxe, constatou-se que o referido terreno, encontra-se em conformidade com a legislação vigente no MUNICÍPIO DE PAU DOS FERROS – RN.',
                'Isto posto, cumpre-me opinar pelo prosseguimento do processo de <strong>EXPEDIÇÃO DE DESMEMBRAMENTO</strong>, por revestir-se de sustentação técnica legal.',
            ],
        ],
    ],
];

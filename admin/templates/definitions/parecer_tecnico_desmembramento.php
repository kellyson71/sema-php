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
                'Trata o presente parecer técnico sobre o <strong>REQUERIMENTO DE DESMEMBRAMENTO</strong> dos lotes Nº <strong>{{desmembramento_lotes_numeros}}</strong>, com área total desmembrada de <strong>{{desmembramento_area_lotes}} m²</strong> de uma porção maior de <strong>{{area_total_terreno}} m²</strong> (área remanescente de {{area_remanescente}} m²), no imóvel situado em <strong>{{endereco_objetivo}}</strong>{{desmembramento_matricula_texto}}, pertencente a <strong>{{nome_proprietario}}</strong>, CPF/CNPJ <strong>{{cpf_cnpj_proprietario}}</strong>, conforme <strong>{{responsavel_tecnico_rotulo}} Nº {{responsavel_tecnico_numero}}</strong>.',
                'O requerimento em questão foi submetida à apreciação desta Assessoria Técnica para análise e emissão do PARECER acerca das diretrizes que orientam e que regulamentam as edificações no Município de Pau dos Ferros – RN.',
                'Após a análise de praxe, constatou-se que o referido terreno, encontra-se em conformidade com a legislação vigente no MUNICÍPIO DE PAU DOS FERROS – RN.',
                'Isto posto, cumpre-me opinar pelo prosseguimento do processo de <strong>EXPEDIÇÃO DE DESMEMBRAMENTO</strong>, por revestir-se de sustentação técnica legal.',
            ],
        ],
    ],
];

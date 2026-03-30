<?php
return [
    'label'     => 'Licença Prévia de Projeto de Obra',
    'descricao' => 'Aprovação prévia do projeto para edificação.',
    'icone'     => 'fa-drafting-compass',
    'badge'     => 'Licença',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'LICENÇA PRÉVIA DE PROJETO DE OBRA',
            'subtexto' => 'Nº {{numero_documento_ano}} — Nº PROCESSO: {{protocolo}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'Fundamentação Legal: Lei Municipal nº 017/2022 (Plano Diretor); 2117/2025 (Código Municipal de Obras); 020/2023 (Política Municipal de Resíduos Sólidos); Lei Federal nº 6.938/1981 e Resoluções do CONAMA.',
        ],

        [
            'tipo'  => 'secao',
            'texto' => '1. IDENTIFICAÇÃO DO PROPRIETÁRIO',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Nome/Razão Social', '{{nome_requerente}}'],
                ['CPF/CNPJ',          '{{cpf_cnpj_requerente}}'],
            ],
        ],

        [
            'tipo'  => 'secao',
            'texto' => '2. DADOS DO EMPREENDIMENTO/OBRA',
        ],
        [
            'tipo'   => 'tabela',
            'linhas' => [
                ['Endereço da Obra', '{{endereco_objetivo}}'],
                ['Tipo de Alvará',   '{{tipo_alvara}}'],
                ['Área construída',  '{{area_construida}} m²'],
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
            'texto' => '4. PARECER TÉCNICO',
        ],
        [
            'tipo'     => 'texto',
            'conteudo' => '<p><strong>DESCRIÇÃO:</strong> {{especificacao}}</p><p>Trata-se da aprovação prévia do projeto para a edificação residencial com {{area_construida}} m² de acordo com as diretrizes que orientam e que regulamentam as edificações no município de Pau dos Ferros – RN. A obra deve seguir os padrões básicos da construção civil, respeitando as boas práticas da engenharia, bem como atender à legislação municipal, sobretudo as orientações constantes na Lei nº 017/2022; 2117/2025 e 020/2023. CONCLUSÃO: PROJETO APROVADO.</p>',
        ],

        [
            'tipo'   => 'condicionantes',
            'titulo' => 'CONDICIONANTES E RESTRIÇÕES:',
            'itens'  => [
                'Esta licença tem validade de <strong>90 (noventa) dias</strong>.',
                'Os resíduos da construção civil devem ter destinação final ambientalmente adequada.',
                'Esta licença não substitui a necessidade de alvará de construção.',
            ],
        ],

        ['tipo' => 'data_local'],
    ],
];

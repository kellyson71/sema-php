<?php
/**
 * Definição: Carta de Habite-se
 *
 * Layout alinhado ao documento original da SEMA: tabela de linha única com
 * bordas (sem cabeçalhos de seção cinza), campos por célula.
 */

return [
    'label'     => 'Carta de Habite-se',
    'descricao' => 'Certificado de conclusão e habitabilidade da edificação.',
    'icone'     => 'fa-home',
    'badge'     => 'Habite-se',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'CARTA DE HABITE-SE',
            'subtexto' => 'N° {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => '{{protocolo}}',
        ],

        [
            'tipo'   => 'tabela',
            'linhas' => [
                [
                    'PROPRIETÁRIO DO IMÓVEL:',
                    '{{nome_proprietario}}<br><strong>CPF/CNPJ:</strong> {{cpf_cnpj_proprietario}}',
                    ['colspan' => true],
                ],
                [
                    'ENDEREÇO DA OBRA:',
                    '{{endereco_objetivo}}<br><strong>CIDADE:</strong> Pau dos Ferros - RN.',
                    ['colspan' => true],
                ],
                [
                    'RESPONSÁVEL TÉCNICO:',
                    '{{responsavel_tecnico_nome}}<br><strong>REGISTRO:</strong> N° {{responsavel_tecnico_registro}}',
                    ['colspan' => true],
                ],
                [
                    'ALVARÁ:',
                    '{{alvara_construcao_numero}}'
                        . '<br><strong>CADASTRO IMOBILIÁRIO (SEQUENCIAL):</strong> {{cadastro_imobiliario}}'
                        . '<br><strong>ART:</strong> N° {{responsavel_tecnico_numero}}'
                        . '<br><strong>PERÍODO DA OBRA:</strong> INÍCIO {{inicio_obra}}, TÉRMINO {{termino_obra}}',
                    ['colspan' => true],
                ],
                [
                    'ESPECIFICAÇÃO / LAUDO DO ENGENHEIRO TÉCNICO E FISCAL DE OBRAS:',
                    '<br><strong>PARECER TÉCNICO DADO PELO:</strong> ENG.º CIVIL: {{eng_fiscal_nome}}. CREA: {{eng_fiscal_registro}}.'
                        . '<br><br><strong><em>CARACTERÍSTICAS:</em></strong>'
                        . '<br><em>{{especificacao}}</em>',
                    ['colspan' => true],
                ],
            ],
        ],

        ['tipo' => 'data_local'],
    ],
];

<?php
/**
 * Definição: Alvará de Desmembramento
 *
 * Layout alinhado ao documento original da SEMA: texto 100% corrido (sem tabela
 * de dados), com área desmembrada, área total, área remanescente e cadastro.
 */

return [
    'label'     => 'Alvará de Desmembramento',
    'descricao' => 'Autorização para desmembramento de lote urbano.',
    'icone'     => 'fa-vector-square',
    'badge'     => 'Desmembramento',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'ALVARÁ DE DESMEMBRAMENTO',
            'subtexto' => 'N° {{numero_documento_ano}}',
        ],
        [
            'tipo'  => 'subtitulo',
            'texto' => 'PROCESSO: {{protocolo_oficial}}',
        ],

        [
            'tipo'     => 'texto',
            'conteudo' => '<p style="text-align:justify;">'
                . 'FICA AUTORIZADO O DESMEMBRAMENTO DOS LOTES Nº {{desmembramento_lotes_numeros}}, '
                . 'COM ÁREA TOTAL DESMEMBRADA DE {{desmembramento_area_lotes}} M², DE UMA PORÇÃO MAIOR DE '
                . '{{area_total_terreno}} M², FICANDO ÁREA REMANESCENTE DE {{area_remanescente}} M². '
                . 'LOCALIZADOS EM {{endereco_objetivo}}{{desmembramento_matricula_texto}}, PERTENCENTES A <strong>{{nome_proprietario}}</strong>, '
                . 'CPF/CNPJ {{cpf_cnpj_proprietario}}.'
                . '</p>{{desmembramento_lotes_html}}'
                . '<p style="text-align:justify;">ESTE DESMEMBRAMENTO É AUTORIZADO EM CONFORMIDADE COM A LEI Nº 6.766 '
                . 'DE 19 DE DEZEMBRO DE 1979, PARECER TÉCNICO DA SECRETARIA MUNICIPAL DE MEIO AMBIENTE – SEMA E '
                . 'CONFORME A {{responsavel_tecnico_rotulo}} DE DESMEMBRAMENTO Nº {{responsavel_tecnico_numero}}.</p>',
        ],

        ['tipo' => 'data_local'],
    ],
];

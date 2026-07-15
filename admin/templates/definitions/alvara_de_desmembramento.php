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
            'texto' => 'PROCESSO: {{protocolo}}',
        ],

        [
            'tipo'     => 'texto',
            'conteudo' => '<p style="text-align:justify;">'
                . 'Fica autorizado o desmembramento de um lote de {{area_lote}} m² de um total de '
                . '{{area_total_terreno}} m², ficando de área remanescente o total de {{area_remanescente}} m². '
                . 'Localizado em {{endereco_objetivo}}, pertencente a <strong>{{nome_proprietario}}</strong>, '
                . 'CPF: {{cpf_cnpj_proprietario}}. '
                . 'Descrição do lote com cadastro {{cadastro_imobiliario}}: {{especificacao}} '
                . 'Este desmembramento é autorizado em conformidade com a Lei N° 6.766 de 19 de dezembro de 1979, '
                . 'parecer técnico da Secretaria Municipal de Meio Ambiente – SEMA e conforme a ART de desmembramento '
                . 'ART {{responsavel_tecnico_numero}}.'
                . '</p>',
        ],

        ['tipo' => 'data_local'],
    ],
];

<?php
/*
 * Parecer Técnico de Pendências (processos ambientais) — reunião de 25/09/2026.
 * O título vira "PARECER TÉCNICO FINAL" a partir do 2º parecer emitido no mesmo
 * processo (DocumentoRegras::tituloParecerPendencias). A tabela sai com uma linha
 * por documento anexado; a equipe de análise vem de admin/assinantes_modelos.php.
 */
return [
    'label'     => 'Parecer Técnico Ambiental — Pendências',
    'descricao' => 'Análise documento a documento do processo ambiental; o 2º parecer do processo sai como Parecer Técnico Final.',
    'icone'     => 'fa-list-check',
    'badge'     => 'Parecer',

    'blocos' => [
        [
            'tipo'     => 'html',
            'conteudo' =>
                '<p style="font-weight:bold; text-decoration:underline; font-size:12pt;">{{titulo_parecer_pendencias}} – PROCESSO {{protocolo}}</p><br>'
                . '<p style="font-weight:bold; font-size:12pt;"><u>SETOR</u>: ENG. AMBIENTAL/FISCALIZAÇÃO AMBIENTAL<br>'
                . '<u>ASSUNTO DO PARECER</u>: PENDÊNCIAS DOCUMENTAIS DO PROCESSO {{nome_licenca}} – {{nome_empreendedor}}.</p><br>'
                . '<p style="font-weight:bold; font-size:12pt;"><u>EQUIPE DE ANÁLISE</u>:<br>'
                . '{{assinante_eng_ambiental_rotulo}}: {{assinante_eng_ambiental_nome}}<br>'
                . '{{assinante_fiscal_ambiental_rotulo}}: {{assinante_fiscal_ambiental_nome}}</p><br>',
        ],

        [
            'tipo'     => 'html',
            'conteudo' => '<p style="font-weight:bold;"><u>DO OBJETO</u>:</p>',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Trata o presente parecer técnico das pendências documentais do Processo {{protocolo}} referente à {{nome_licenca}} de {{nome_empreendedor}}, situado em {{endereco_empreendimento}}',
            ],
        ],

        [
            'tipo'     => 'html',
            'conteudo' => '<p style="font-weight:bold;"><u>DA ANÁLISE DOS DOCUMENTOS ANEXADOS</u></p>{{tabela_analise_documentos_html}}<br><br>',
        ],

        [
            'tipo'     => 'html',
            'conteudo' => '<p style="font-weight:bold;"><u>CONSIDERAÇÕES DO PARECER</u></p>',
        ],
        [
            'tipo'   => 'paragrafos',
            'textos' => [
                'Do exposto, fica a continuidade do processo condicionada ao atendimento das pendências acima elencadas.',
            ],
        ],

        ['tipo' => 'data_local'],

        [
            'tipo'     => 'html',
            'conteudo' =>
                '<table nobr="true" width="100%" border="0" cellpadding="2" cellspacing="0" style="font-size:11pt; border:none;">'
                . '<tr>'
                . '<td width="50%" style="text-align:center; border:none;"><br><br>______________________________<br>'
                . '<strong>{{assinante_eng_ambiental_nome}}</strong><br>{{assinante_eng_ambiental_cargo_linha}}</td>'
                . '<td width="50%" style="text-align:center; border:none;"><br><br>______________________________<br>'
                . '<strong>{{assinante_fiscal_ambiental_nome}}</strong><br>{{assinante_fiscal_ambiental_cargo_linha}}</td>'
                . '</tr></table>',
        ],
    ],
];

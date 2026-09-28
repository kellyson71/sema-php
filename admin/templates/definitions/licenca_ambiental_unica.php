<?php
/*
 * Licença Ambiental Única (LAU) — modelo pedido na reunião de 25/09/2026.
 * Setor responsável é texto fixo; validade = recebimento do processo + 5 anos
 * (ParecerService::preencherDados). Assinantes vêm de admin/assinantes_modelos.php.
 */
$faixa = static fn(string $texto): string =>
    '<tr><td colspan="2" bgcolor="#2f5597" style="background-color:#2f5597; color:#ffffff; font-weight:bold; text-align:center; font-size:12pt; padding:4px;">'
    . $texto . '</td></tr>';
$celula = 'border:1px solid #000; padding:4px 6px; font-size:11pt; vertical-align:top;';

return [
    'label'     => 'Licença Ambiental Única (LAU)',
    'descricao' => 'Licença ambiental única com condicionantes, validade de 5 anos e assinaturas da equipe ambiental.',
    'icone'     => 'fa-leaf',
    'badge'     => 'Licença',

    'blocos' => [
        [
            'tipo'     => 'html',
            'conteudo' =>
                '<table width="100%" border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse; font-family:Arial,Helvetica,sans-serif;">'
                . $faixa('LICENÇA AMBIENTAL ÚNICA – LAU')
                . '<tr>'
                . '<td width="60%" style="' . $celula . '">Data de recebimento do processo: {{data_recebimento_processo}}<br>'
                . 'Setor responsável pela análise:<br>Fiscalização Ambiental/Eng.Ambiental</td>'
                . '<td width="40%" style="' . $celula . '"><strong>Nº {{protocolo}}</strong><br><br>'
                . '<strong>Data de validade: {{data_validade_licenca}}</strong></td>'
                . '</tr>'
                . $faixa('IDENTIFICAÇÃO DO EMPREENDEDOR E DO EMPREENDIMENTO')
                . '<tr><td colspan="2" style="' . $celula . ' line-height:1.7;">'
                . '<strong>1. Identificação do Requerente (Empreendedor):</strong><br>'
                . '<strong>Nome (pessoa física ou jurídica):</strong> <u>{{nome_empreendedor}}</u><br>'
                . 'CNPJ/CPF: <u>{{cpf_cnpj_empreendedor}}</u><br>'
                . 'Endereço do Empreendedor: <u>{{endereco_empreendedor}}</u><br>'
                . 'Endereço do Empreendimento: <u>{{endereco_empreendimento}}</u><br>'
                . 'Caracterização do Empreendimento: <u>{{caracterizacao_empreendimento}}</u>'
                . '</td></tr>'
                . $faixa('FUNDAMENTAÇÃO LEGAL/SÍNTESE DO PARECER TÉCNICO AMBIENTAL')
                . '<tr><td colspan="2" style="' . $celula . ' text-align:justify; line-height:1.7;">'
                . '2. Trata-se da análise do processo nº{{protocolo}} que se refere à Licença Ambiental Única (LAU) do empreendimento '
                . '“{{nome_empreendedor}}”. A LAU consiste no procedimento de licenciamento ambiental preconizado pelo código municipal '
                . 'de meio ambiente (Lei nº2116/2025) para empreendimentos enquadrados em médio potencial poluidor. '
                . '[Descrever o enquadramento do empreendimento e a síntese do parecer técnico ambiental.]'
                . '</td></tr>'
                . $faixa('CONDICIONANTES')
                . '<tr><td colspan="2" style="' . $celula . ' text-align:justify; line-height:1.6;">'
                . '<ol style="padding-left:22px; margin:0;">'
                . '<li><span style="color:#c00000;">CONDICIONANTE:</span> [Descrever a condicionante.]</li>'
                . '<li><span style="color:#c00000;">CONDICIONANTE:</span> [Descrever a condicionante.]</li>'
                . '<li><span style="color:#c00000;">CONDICIONANTE:</span> [Descrever a condicionante.]</li>'
                . '</ol><br>'
                . '<strong style="font-size:9pt;">O efetivo atendimento às condicionantes acima será acompanhado pelo setor de fiscalização ambiental da SEMA.</strong>'
                . '</td></tr>'
                . $faixa('CONSIDERAÇÕES')
                . '<tr><td colspan="2" style="' . $celula . ' text-align:justify;">'
                . '<strong>A SEMA, com embasamento legal na Lei Federal nº140/2011 e no código municipal de meio ambiente (Lei Municipal nº2116/2025), '
                . 'emite a presente LICENÇA AMBIENTAL ÚNICA (LAU) para o empreendimento {{nome_empreendedor}}, situado em {{endereco_empreendimento}}</strong>'
                . '</td></tr>'
                . '</table>',
        ],

        [
            'tipo'     => 'html',
            'conteudo' =>
                '<br><table nobr="true" width="100%" border="0" cellpadding="2" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif; font-size:10pt; text-align:center; border:none;">'
                . '<tr>'
                . '<td width="50%" style="text-align:center; border:none;"><br><br>______________________________<br>'
                . '<strong>{{assinante_eng_ambiental_nome}}</strong><br>{{assinante_eng_ambiental_cargo_linha}}</td>'
                . '<td width="50%" style="text-align:center; border:none;"><br><br>______________________________<br>'
                . '<strong>{{assinante_fiscal_ambiental_nome}}</strong><br>{{assinante_fiscal_ambiental_cargo_linha}}</td>'
                . '</tr>'
                . '<tr><td colspan="2" style="text-align:center; border:none;"><br><br>______________________________<br>'
                . '<strong>{{assinante_secretario_nome}}</strong><br>{{assinante_secretario_cargo_linha}}</td></tr>'
                . '</table>',
        ],
    ],
];

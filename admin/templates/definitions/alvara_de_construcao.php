<?php
/**
 * Definição: Alvará de Construção
 *
 * Layout alinhado ao documento original da SEMA: texto corrido com rótulos em
 * negrito (sem seções numeradas nem caixa de condicionantes).
 *
 * Variáveis {{campo}} são preenchidas pelo ParecerService::preencherDados().
 */

return [
    'label'     => 'Alvará de Construção',
    'descricao' => 'Autorização para execução de obras de construção civil.',
    'icone'     => 'fa-hard-hat',
    'badge'     => 'Construção',

    'blocos' => [
        [
            'tipo'     => 'titulo',
            'texto'    => 'ALVARÁ DE CONSTRUÇÃO',
            'subtexto' => 'Nº {{numero_documento_ano}}',
        ],

        [
            'tipo'     => 'texto',
            'conteudo' => '<strong>PROPRIETÁRIO:</strong><br>'
                . '<strong>NOME:</strong> {{nome_proprietario}}<br>'
                . '<strong>CPF/CNPJ:</strong> {{cpf_cnpj_proprietario}}',
        ],

        [
            'tipo'     => 'texto',
            'conteudo' => '<strong>RESPONSÁVEL TÉCNICO:</strong><br>'
                . '{{responsavel_tecnico_nome}}<br>'
                . '<strong>REGISTRO:</strong> N° {{responsavel_tecnico_registro}}',
        ],

        [
            'tipo'     => 'texto',
            'conteudo' => '<strong>ENDEREÇO DA OBRA:</strong><br>{{endereco_objetivo}}',
        ],

        [
            'tipo'     => 'texto',
            'conteudo' => '<strong>ESPECIFICAÇÃO:</strong><br>{{especificacao}} '
                . 'A obra deve seguir os padrões básicos da construção civil, respeitando as boas '
                . 'práticas da engenharia. Executar impermeabilização das fundações e reservatórios. '
                . 'Os projetos complementares de água fria, esgoto e instalações elétricas deverão ser '
                . 'seguidos, mantendo assim o bom funcionamento das instalações prediais.',
        ],

        [
            'tipo'  => 'dados_inline',
            'dados' => [
                ['PROTOCOLO', '{{protocolo}}'],
                ['ÁREA CONSTRUÍDA', '{{area_construida}} m²'],
                ['CADASTRO IMOBILIÁRIO', '{{cadastro_imobiliario}}'],
                ['ART', '{{responsavel_tecnico_numero}}'],
                ['PREVISÃO DA OBRA', 'Início {{inicio_obra}}, Término {{termino_obra}}'],
            ],
        ],

        ['tipo' => 'data_local'],
    ],
];

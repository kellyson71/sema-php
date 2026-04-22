<?php
/**
 * Tabela de enquadramento ambiental — Resolução CONEMA nº 04/2009 (versão outubro/2011)
 *
 * Estrutura: categorias (títulos dos selects) → atividades (opções dentro de cada categoria).
 * Referência: Anexo Único da Resolução CONEMA 04/2009, com base na Resolução CONEMA 03/2008.
 *
 * Potencial poluidor: P = Pequeno, M = Médio, G = Grande
 */
$enquadramento_conema = [
    'agricultura_criacao_animais' => [
        'titulo' => 'Agricultura e Criação de Animais',
        'atividades' => [
            'agricultura_nao_irrigada'      => ['nome' => 'Agricultura não Irrigada', 'potencial' => 'P'],
            'avicultura'                    => ['nome' => 'Avicultura', 'potencial' => 'M'],
            'bovinocultura_extensiva'       => ['nome' => 'Bovinocultura Extensiva', 'potencial' => 'M'],
            'bovinocultura_intensiva'       => ['nome' => 'Bovinocultura Intensiva', 'potencial' => 'M'],
            'caprinovinocultura_extensiva'  => ['nome' => 'Caprinovinocultura Extensiva', 'potencial' => 'M'],
            'caprinovinocultura_intensiva'  => ['nome' => 'Caprinovinocultura Intensiva', 'potencial' => 'M'],
            'criacao_cavalos_jumentos'      => ['nome' => 'Criação de cavalos, jumentos, mulas e similares', 'potencial' => 'M'],
            'suinocultura'                  => ['nome' => 'Suinocultura', 'potencial' => 'M'],
            'packing_houses'               => ['nome' => 'Packing-houses, unidades de pré-beneficiamento de produtos de origem animal e vegetal', 'potencial' => 'M'],
        ]
    ],
    'aquicultura' => [
        'titulo' => 'Aquicultura',
        'atividades' => [
            'aquicultura_organica'     => ['nome' => 'Aquicultura Orgânica', 'potencial' => 'P'],
            'carcinicultura'           => ['nome' => 'Carcinicultura (fora do estuário e sem captação de água ou lançamento de efluentes líquidos diretamente nesse ecossistema estuarino)', 'potencial' => 'M'],
            'piscicultura_tanque_rede' => ['nome' => 'Piscicultura em Tanque-Rede / Gaiola', 'potencial' => 'M'],
            'piscicultura_viveiro'     => ['nome' => 'Piscicultura em Viveiro', 'potencial' => 'M'],
            'ranicultura'              => ['nome' => 'Ranicultura', 'potencial' => 'P'],
        ]
    ],
    'extracao_bens_minerais' => [
        'titulo' => 'Atividades de Extração e Pesquisa de Bens Minerais',
        'atividades' => [
            'extracao_areia_argila'       => ['nome' => 'Extração de areia, argila, cascalho, piçarro, saibro, caulim, diatomita e similares', 'potencial' => 'M'],
            'extracao_gemas'              => ['nome' => 'Extração de Gemas (águas-marinhas, turmalina, etc.)', 'potencial' => 'M'],
            'extracao_envase_agua_mineral' => ['nome' => 'Extração, Envase e Gasificação de Água Mineral', 'potencial' => 'P'],
        ]
    ],
    'infraestrutura' => [
        'titulo' => 'Infraestrutura',
        'atividades' => [
            'aerodromos'              => ['nome' => 'Aeródromos (pistas de pouso e decolagem)', 'potencial' => 'M'],
            'atracadouros_pieres'     => ['nome' => 'Atracadouros e Píeres em águas interiores, excluindo-se as áreas estuarinas e marinhas', 'potencial' => 'M'],
            'estradas_ferrovias'      => ['nome' => 'Estradas e Ferrovias', 'potencial' => 'M'],
            'acessos'                 => ['nome' => 'Acessos', 'potencial' => 'M'],
            'pontes_viadutos_tuneis'  => ['nome' => 'Pontes, Viadutos, Túneis', 'potencial' => 'P'],
            'adutoras_canais_aducao'  => ['nome' => 'Adutoras, Canais de Adução', 'potencial' => 'P'],
            'penitenciarias'          => ['nome' => 'Penitenciárias', 'potencial' => 'P'],
        ]
    ],
    'construcao_civil' => [
        'titulo' => 'Construção Civil',
        'atividades' => [
            'barragens_acudes'          => ['nome' => 'Barragens e Açudes', 'potencial' => 'M'],
            'casas_espetaculos_shows'   => ['nome' => 'Casas de Espetáculos/Shows', 'potencial' => 'M'],
            'ginasios_esportes'         => ['nome' => 'Ginásios de Esportes', 'potencial' => 'M'],
            'centros_pesquisa_escolas'  => ['nome' => 'Centros de Pesquisa e Escolas', 'potencial' => 'P'],
            'condominios'              => ['nome' => 'Condomínios', 'potencial' => 'M'],
            'conjuntos_habitacionais'   => ['nome' => 'Conjuntos Habitacionais', 'potencial' => 'M'],
            'supermercados_shopping'    => ['nome' => 'Supermercados e Shopping Centers', 'potencial' => 'M'],
            'dragagem_desassoreamento'  => ['nome' => 'Dragagem/Desassoreamento em águas interiores, excluindo-se as áreas estuarinas e marinhas', 'potencial' => 'M'],
            'terraplenagem'            => ['nome' => 'Terraplenagem (em áreas que não objetivem licenciamento ambiental imediato)', 'potencial' => 'M'],
            'obras_contencao_erosao'   => ['nome' => 'Obras de Contenção de Erosão', 'potencial' => 'M'],
            'parques_exposicao_vaquejada' => ['nome' => 'Parques de Exposição, Parques de Vaquejada', 'potencial' => 'M'],
            'clubes_camping'           => ['nome' => 'Clubes (inclusive de camping)', 'potencial' => 'P'],
            'loteamentos_desmembramentos' => ['nome' => 'Loteamentos e Desmembramentos', 'potencial' => 'M'],
            'empreendimentos_urbanizacao' => ['nome' => 'Empreendimentos de Urbanização', 'potencial' => 'P'],
            'estadio_futebol'          => ['nome' => 'Estádio de Futebol', 'potencial' => 'M'],
            'centro_treinamento_esportivo' => ['nome' => 'Centro de Treinamento Esportivo, Vila Olímpica', 'potencial' => 'M'],
            'centro_convencoes'        => ['nome' => 'Centro de Convenções', 'potencial' => 'P'],
        ]
    ],
    'empreendimentos_turisticos' => [
        'titulo' => 'Empreendimentos Turísticos',
        'atividades' => [
            'resorts_complexos_turisticos' => ['nome' => 'Resorts, Complexos Turísticos e Imobiliários', 'potencial' => 'M'],
            'terminais_turisticos_parques' => ['nome' => 'Terminais Turísticos, Parques Temáticos, Estruturas de Lazer e similares', 'potencial' => 'P'],
            'pousadas'                     => ['nome' => 'Pousadas', 'potencial' => 'P'],
            'hoteis_flats'                 => ['nome' => 'Hotéis e Flats', 'potencial' => 'P'],
        ]
    ],
    'servicos' => [
        'titulo' => 'Serviços',
        'atividades' => [
            'postos_combustiveis_liquidos'      => ['nome' => 'Postos de Revenda ou Abastecimento de Combustíveis Líquidos', 'potencial' => 'G'],
            'postos_combustiveis_liquidos_gnv'   => ['nome' => 'Postos de Revenda ou Abastecimento de Combustíveis Líquidos e GNV', 'potencial' => 'G'],
            'postos_gnv'                        => ['nome' => 'Postos de Revenda ou Abastecimento de GNV', 'potencial' => 'M'],
            'limpeza_fossas_sumidouros'          => ['nome' => 'Sistemas de Limpeza de Fossas e Sumidouros e Destinação Final de Efluentes Domésticos', 'potencial' => 'M'],
            'coleta_pilhas_baterias'            => ['nome' => 'Posto de coleta e armazenamento de pilhas, baterias e afins, para destinação final', 'potencial' => 'M'],
            'armazenamento_glp'                 => ['nome' => 'Armazenamento e Revenda de Recipientes Transportáveis de Gás Liquefeito de Petróleo (GLP)', 'potencial' => 'M'],
            'lavagem_lubrificacao_veiculos'      => ['nome' => 'Serviços de lavagem, lubrificação e de troca de óleo de veículos', 'potencial' => 'M'],
        ]
    ],
    'saneamento_basico' => [
        'titulo' => 'Atividades de Saneamento Básico',
        'atividades' => [
            'sistemas_abastecimento_agua' => ['nome' => 'Sistemas de Abastecimento d\'Água', 'potencial' => 'P'],
            'sistemas_esgotos_sanitarios' => ['nome' => 'Sistemas de Esgotos Sanitários', 'potencial' => 'M'],
            'sistemas_drenagem_pluviais'  => ['nome' => 'Sistemas de Drenagem de Águas Pluviais', 'potencial' => 'P'],
        ]
    ],
    'telecomunicacoes_energia' => [
        'titulo' => 'Telecomunicações e Energia Elétrica',
        'atividades' => [
            'subestacoes_energia'              => ['nome' => 'Subestações de Energia Elétrica', 'potencial' => 'P'],
            'linhas_transmissao_energia'        => ['nome' => 'Linhas de Transmissão e Subtransmissão de Energia Elétrica', 'potencial' => 'P'],
            'geracao_energia_eolica_solar'      => ['nome' => 'Geração de Energia Elétrica (eólica e solar)', 'potencial' => 'P'],
            'geracao_energia_termoeletrica'     => ['nome' => 'Geração de Energia Elétrica (termoelétrica a gás natural, bagaço de cana-de-açúcar ou outro vegetal)', 'potencial' => 'M'],
            'estacoes_radiocomunicacao'         => ['nome' => 'Estações de Radiocomunicação', 'potencial' => 'P'],
            'cubiculos_medicao_protecao'        => ['nome' => 'Cubículos de Medição e Proteção', 'potencial' => 'P'],
        ]
    ],
    'tratamento_residuos' => [
        'titulo' => 'Tratamento de Resíduos Sólidos e Líquidos',
        'atividades' => [
            'aterros_residuos_construcao'          => ['nome' => 'Aterros de Resíduos da Construção Civil', 'potencial' => 'M'],
            'crematorios'                          => ['nome' => 'Crematórios', 'potencial' => 'M'],
            'tratamento_efluentes_liquidos'         => ['nome' => 'Sistemas de Tratamento de Efluentes Líquidos Sanitários', 'potencial' => 'M'],
            'emissario_efluentes_liquidos'          => ['nome' => 'Emissário de Efluentes Líquidos (trecho terrestre)', 'potencial' => 'P'],
            'estacao_transbordo'                   => ['nome' => 'Estação de Transbordo', 'potencial' => 'M'],
        ]
    ],
    'empreendimentos_diversos' => [
        'titulo' => 'Atividades/Empreendimentos Diversos',
        'atividades' => [
            'readequacao_efluentes_sanitarios'  => ['nome' => 'Readequação e/ou Modificações de Sistemas de Controle de Efluentes Líquidos Sanitários', 'potencial' => 'M'],
            'comercio_madeira'                 => ['nome' => 'Comércio de Madeira (sem beneficiamento)', 'potencial' => 'P'],
            'assentamentos_reforma_agraria'    => ['nome' => 'Assentamentos de Reforma Agrária (sem a atividade de Agricultura Irrigada)', 'potencial' => 'M'],
            'jateamento_sem_pintura'           => ['nome' => 'Jateamento sem Pintura', 'potencial' => 'P'],
        ]
    ],
    'atividades_industriais' => [
        'titulo' => 'Atividades Industriais de Transformação',
        'atividades' => [
            'padaria_confeitaria_pastelaria'    => ['nome' => 'Fabricação de Produtos de Padaria, Confeitaria e Pastelaria, Massas Alimentícias e Biscoitos', 'potencial' => 'P'],
            'madeiras_serraria'                => ['nome' => 'Madeiras (desdobramento, fabricação de artefatos, serraria)', 'potencial' => 'P'],
            'mobiliario'                       => ['nome' => 'Mobiliário (fabricação de móveis de madeira, vime, bambu e similares)', 'potencial' => 'P'],
        ]
    ],
];

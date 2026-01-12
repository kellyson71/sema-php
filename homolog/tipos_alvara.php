<?php
// Array com todos os tipos de alvará e seus documentos necessários
$tipos_alvara = [
    'construcao' => [
        'nome' => 'ALVARÁ DE CONSTRUÇÃO, REFORMA E/OU AMPLIAÇÃO',
        'documentos' => [
            '1. Documento pessoal com foto e CPF/CNPJ do requerente;',
            '2. Documento pessoal com foto e CPF/CNPJ do proprietário;',
            '3. Comprovante de residência do proprietário e do requerente;',
            '4. Documento do terreno;',
            '5. Cadastro imobiliário;',
            '6. ART ou RRT do projeto e execução (assinada pelo responsável técnico e contratante);',
            '7. Projetos arquitetônicos: (assinados pelo responsável técnico e proprietário)
               - Planta de situação com coordenada geográfica indicando os nomes das ruas e distância das esquinas mais próximas;
               - Planta de locação da construção no terreno e coberta;
               - Planta baixa e cortes.',
            '8. Projetos complementares: (assinados pelo responsável técnico e proprietário)
               - Projeto sanitário com locação da fossa e sumidouro com cotas legíveis. Caso exista coleta pública de esgoto na rua, é necessário apresentar um documento que comprove (conta de água ou declaração de viabilidade técnica);',
        ],
        'documentos_opcionais' => [
            '9. Cópia do Atestado de Vistoria do Corpo de Bombeiros (para construções acima de 930 m²);',
            '10. Licenciamento ambiental junto ao IDEMA (nos casos de acordo com a Resolução nº 02/2014 do Conselho Estadual do Meio Ambiente – CONEMA);',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'obras_publicas' => [
            'ART ou RRT do projeto, execução e fiscalização (assinada pelo responsável técnico e contratante);',
            'Ordem de serviço e contrato.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'habite_se' => [
        'nome' => 'ALVARÁ DE HABITE-SE E LEGALIZAÇÃO',
        'documentos' => [
            '1. Documento pessoal com foto e CPF/CNPJ do requerente;',
            '2. Documento pessoal com foto e CPF/CNPJ do proprietário;',
            '3. Comprovante de residência do proprietário e do requerente;',
            '4. Documento do terreno e Cadastro imobiliário;',
            '5. ART ou RRT do projetos e execução (assinada pelo responsável técnico e contratante);',
            '6. Projetos arquitetônicos: (assinados pelo responsável técnico e proprietário)
               - Planta de situação com coordenada geográfica indicando os nomes das ruas e distância das esquinas mais próximas;
               - Planta de locação da construção no terreno e coberta;
               - Planta baixa e cortes.',
            '7. Projetos complementares: (assinados pelo responsável técnico e proprietário)
               - Projeto sanitário com locação da fossa e sumidouro com cotas legíveis. Caso exista coleta pública de esgoto na rua, é necessário apresentar um documento que comprove (conta de água ou declaração de viabilidade técnica);',
            '8. Cópia do Atestado de Vistoria do Corpo de Bombeiros (para construções acima de 930 m²);',
            '9. Cópia da Licença solicitada junto ao IDEMA (nos casos de acordo com a Resolução nº 02/2014 do Conselho Estadual do Meio Ambiente – CONEMA).',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'habite_se_simples' => [
        'nome' => 'ALVARÁ DE HABITE-SE',
        'documentos' => [
            '1. Documento pessoal com foto e CPF/CNPJ do requerente;',
            '2. Documento pessoal com foto e CPF/CNPJ do proprietário;',
            '3. Comprovante de residência do proprietário e do requerente;',
            '4. Documento do terreno e Cadastro imobiliário;',
            '5. Alvará de construção vigente;',
            '6. ART ou RRT do projetos e execução (assinada pelo responsável técnico e contratante);',
            '7. Projetos arquitetônicos: (assinados pelo responsável técnico e proprietário)
               - Planta de situação com coordenada geográfica indicando os nomes das ruas e distância das esquinas mais próximas;
               - Planta de locação da construção no terreno e coberta;
               - Planta baixa e cortes.',
            '8. Projetos complementares: (assinados pelo responsável técnico e proprietário)
               - Projeto sanitário com locação da fossa e sumidouro com cotas legíveis. Caso exista coleta pública de esgoto na rua, é necessário apresentar um documento que comprove (conta de água ou declaração de viabilidade técnica);',
            '9. Cópia do Atestado de Vistoria do Corpo de Bombeiros (para construções acima de 930 m²);',
            '10. Cópia da Licença solicitada junto ao IDEMA (de acordo com a Resolução nº 02/2014 do CONEMA);',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'funcionamento' => [
        'nome' => 'ALVARÁ DE FUNCIONAMENTO',
        'pessoa_fisica' => [
            '1. RG e CPF/CNPJ do solicitante e do responsável pelo empreendimento;',
            '2. Comprovante de endereço pessoal;',
            '3. Comprovante de endereço da empresa;',
            '4. Autorização do Corpo de Bombeiros (dependendo do empreendimento, se houver alto risco);',
            '5. Licença junto ao IDEMA (dependendo do empreendimento, se houver alto risco).',
        ],
        'pessoa_juridica' => [
            '1. CNPJ;',
            '2. Certificado de microempreendedor individual;',
            '3. Contrato social (quando for sociedade);',
            '4. RG e CPF/CNPJ;',
            '5. Comprovante de endereço pessoal;',
            '6. Comprovante de endereço da empresa;',
            '7. Autorização do Corpo de Bombeiros (dependendo do empreendimento, se houver alto risco);',
            '8. Licença junto ao IDEMA (dependendo do empreendimento, se houver alto risco).',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'desmembramento' => [
        'nome' => 'ALVARÁ DE DESMEMBRAMENTO E REMEMBRAMENTO',
        'documentos' => [
            '1. Documento pessoal com foto e CPF/CNPJ do requerente;',
            '2. Documento pessoal com foto e CPF/CNPJ do proprietário;',
            '3. Comprovante de residência do proprietário e do requerente;',
            '4. Documento do terreno;',
            '5. Certidão atualizada de gleba;',
            '6. Cadastro imobiliário;',
            '7. ART ou RRT do projeto (assinada pelo responsável técnico e contratante);',
            '8. Planta de situação atual (área total) e da área a ser desmembrada/remembrada com coordenada geográfica. (assinada pelo responsável técnico e contratante).',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'demolicao' => [
        'nome' => 'ALVARÁ DE DEMOLIÇÃO',
        'documentos' => [
            '1. ART ou RRT da demolição (assinada pelo responsável técnico e contratante);',
            '2. Projetos arquitetônicos:
               - Planta de locação da construção no terreno e coberta (assinada pelo responsável técnico e contratante);',
            '3. Documento do terreno;',
            '4. Cadastro imobiliário;',
            '5. Documento pessoal com foto e CPF/CNPJ;',
            '6. Comprovante de residência do proprietário e do requerente.',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'transporte' => [
        'nome' => 'RELAÇÃO DE DOCUMENTOS PARA LICENCIAMENTO DE TRANSPORTE ALTERNATIVO E TRANSPORTE ESCOLAR',
        'documentos' => [
            '1. Requerimento assinado pelo interessado;',
            '2. Certidão negativa de débitos municipais;',
            '3. Certidão negativa de débitos estaduais (RN);',
            '4. Certidão negativa de débitos federais;',
            '5. Certidão negativa de débitos do FGTS;',
            '6. Certidão negativa de débitos trabalhistas;',
            '7. Comprovante de residência em Pau dos Ferros;',
            '8. Certidão de registro de distribuição criminal da justiça do RN;',
            '9. Certidão de registro de distribuição criminal da justiça federal;',
            '10. Cópia do CRLV do veículo solicitado;',
            '11. Cópia da CNH do solicitante;',
            '12. Curso de capacitação para condutor.',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'loteamento' => [
        'nome' => 'ALVARÁ DE LOTEAMENTO',
        'documentos' => [
            '1. Planta geral atual indicando:
               - terreno (s) a ser (em) submetido (s) ao loteamento, indicando área (s), limites, ângulos e dimensões;
               - identificação dos lotes, das quadras e das vias com meios-fios;
               - confinantes;
               - norte magnético ou verdadeiro;
               - faixas de domínio em rodovias e áreas não edificantes quando exigidas por leis;
               - porcentagem de vias de circulação ou arruamento, áreas verdes e equipamentos públicos comunitários;
               - indicação das áreas de preservação permanentes (APPs).',
            '2. Planta resultante do processo de loteamento:
               - terreno (s) resultante do processo de loteamento, indicando área (s), limites, ângulos e dimensões;
               - identificação dos lotes, das quadras e das vias com meios-fios.',
            '3. Projeto de córregos e rios, se for o caso, indicando forma de prevenção dos efeitos da erosão e da poluição.',
            '4. Anotação de responsabilidade técnica do projeto.',
            '5. Memorial descritivo indicando as características e condições urbanísticas do loteamento.',
            '6. Cronograma físico.',
            '7. Cópia do Registro do Loteamento.',
            '8. Licença emitida pelo Instituto de Desenvolvimento Sustentável e Meio Ambiente do Rio Grande do Norte (IDEMA).',
            '9. Cópia de contrato de prestação de serviço público de energia elétrica pela COSERN de uma unidade situada no loteamento.',
            '10. Cópia de Registro de Atendimento da CAERN comprovando viabilidade para abastecimento na área.',
            '11. Cópia da lei do bairro, onde se situa o loteamento.',
            '12. Cópias das leis de criação das ruas do loteamento.',
        ],
        'observacoes' => [
            'A qualquer momento a Prefeitura Municipal de Pau dos Ferros poderá exigir a apresentação de documentos adicionais, para melhor instrumentalizar o processo de análise e avaliação do projeto.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'uso_solo' => [
        'nome' => 'CERTIDÃO DE USO E OCUPAÇÃO DO SOLO PARA FINS DE LICENCIAMENTO NO IDEMA',
        'documentos' => [
            '1. ART ou RRT do projeto e execução (assinada pelo responsável técnico e contratante);',
            '2. Projetos arquitetônicos:(assinada pelo responsável técnico e contratante);',
            '3. Planta de baixa com coordenada geográfica indicando os nomes das ruas e a distância das esquinas mais próximas; (assinada pelo responsável técnico e contratante);',
            '4. Documento do terreno;',
            '5. Cadastro imobiliário;',
            '6. Documento pessoal com foto e CPF/CNPJ;',
            '7. Documento pessoal com foto e CPF/CNPJ do proprietário (caso for CNPJ, enviar o contrato social)',
            '8. Comprovante de residência do proprietário e do requerente.',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'parques_circos' => [
        'nome' => 'ALVARÁ PROVISÓRIO PARA PARQUES DE DIVERSÕES E CIRCOS',
        'documentos' => [
            '1. Documento pessoal com foto e CPF/CNPJ do proprietário;',
            '2. Comprovante de residência do proprietário;',
            '3. Contrato de aluguel do terreno;',
            '4. ART de projeto e execução de instalações mecânicas;',
            '5. ART de projeto e execução de instalação e prevenção de combate a incêndios;',
            '6. ART de projeto e execução elétrica;',
            '7. Laudo técnico (assinado pelo responsável técnico);',
            '8. Atestado do Corpo de Bombeiros;',
            '9. Projeto de combate a incêndio (assinado pelo responsável técnico e contratante);',
            '10. Planta baixa (assinado pelo responsável técnico e contratante).',
            '11. Certidão negativa de débitos municipais.',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_previa_obras' => [
        'nome' => 'LICENÇA PRÉVIA DE OBRAS (NÃO AMBIENTAL)',
        'documentos' => [
            '1. Documento pessoal com foto e CPF/CNPJ do requerente;',
            '2. Documento pessoal com foto e CPF/CNPJ do proprietário;',
            '3. Comprovante de residência do proprietário e do requerente;',
            '4. Documento do terreno (quando se tratar de escritura particular autenticada em cartório);',
            '5. ART ou RRT do projeto e execução (assinada pelo responsável técnico e contratante);',
            '6. Projetos arquitetônicos: (assinados pelo responsável técnico e proprietário)
               - Planta de situação com coordenada geográfica indicando os nomes das ruas e distância das esquinas mais próximas;
               - Planta de locação da construção no terreno e coberta;
               - Planta baixa e cortes.',
            '7. Projetos complementares: (assinados pelo responsável técnico e proprietário)
               - Projeto sanitário com locação da fossa e sumidouro com cotas legíveis. Caso exista coleta pública de esgoto na rua, é necessário apresentar um documento que comprove (conta de água ou declaração de viabilidade técnica);',
        ],
        'observacoes' => [
            'Documentações complementares podem ser exigidas pela secretaria do meio ambiente (SEMA) caso ache pertinente para o andamento do processo.',
            'O arquivo enviado não pode ultrapassar 10MB.',
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_previa_ambiental' => [
        'nome' => 'LP — LICENÇA PRÉVIA AMBIENTAL',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Contrato de arrendamento (se houver);',
            '5. Certidão municipal válida por até 2 anos ou alvará equivalente;',
            '6. Certidão DNIT/DER-RN (se aplicável);',
            '7. Memorial descritivo do empreendimento;',
            '8. Planta georreferenciada (digital e impressa);',
            '9. Descrição e classificação de resíduos;',
            '10. ARTs/RRTs de todos os projetos apresentados;',
            '11. Publicação em Diário Oficial;',
            '12. Comprovante de pagamento;',
            '13. Estudo ambiental (EIA/RIMA, PCA ou equivalente quando exigido);'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_previa_instalacao' => [
        'nome' => 'LP/LI — LICENÇA PRÉVIA + INSTALAÇÃO',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Contrato de arrendamento (se houver);',
            '5. Certidão municipal válida por 2 anos OU alvará municipal;',
            '6. Certidão DNIT/DER-RN (se aplicável);',
            '7. Memorial descritivo do empreendimento;',
            '8. Planta georreferenciada (digital e impressa);',
            '9. Outorga preventiva (quando exigida);',
            '10. Descrição e classificação de resíduos;',
            '11. Cronograma físico das instalações;',
            '12. ARTs de todos os projetos;',
            '13. Publicação em Diário Oficial;',
            '14. Comprovante de pagamento;',
            '15. Estudo ambiental (EIA/RIMA, PCA ou equivalente quando exigido);'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_instalacao_operacao' => [
        'nome' => 'LI/LO — LICENÇA DE INSTALAÇÃO + OPERAÇÃO',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Contrato de arrendamento (se houver);',
            '5. Certidão municipal válida por 2 anos OU alvará municipal;',
            '6. Certidão DNIT/DER-RN (se aplicável);',
            '7. Memorial descritivo e projeto executivo;',
            '8. Planta georreferenciada (digital e impressa);',
            '9. Outorga preventiva/definitiva (quando exigida);',
            '10. Descrição e classificação de resíduos e efluentes;',
            '11. Cronograma físico da obra/implantação;',
            '12. ARTs/RRTs de todos os projetos e execuções;',
            '13. Licença anterior (quando houver fase prévia);',
            '14. Relatório de atendimento às condicionantes anteriores (se houver);',
            '15. Publicação em Diário Oficial;',
            '16. Comprovante de pagamento;',
            '17. Estudo ambiental (EIA/RIMA, PCA ou equivalente quando exigido);'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_operacao' => [
        'nome' => 'LO — LICENÇA DE OPERAÇÃO',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Procuração (quando houver representante);',
            '4. Licença anterior do empreendimento;',
            '5. Relatório de atendimento às condicionantes;',
            '6. Cadastro Técnico Federal (CTF);',
            '7. Publicação em Diário Oficial;',
            '8. Comprovante de pagamento;',
            '9. ARTs/RRTs e validações técnicas pertinentes.'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_ambiental_unica' => [
        'nome' => 'LAU — LICENÇA AMBIENTAL ÚNICA',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Contrato de arrendamento (se houver);',
            '5. Certidão municipal válida por 2 anos OU alvará municipal;',
            '6. Certidão DNIT/DER-RN (se aplicável);',
            '7. Memorial descritivo e projeto executivo;',
            '8. Planta georreferenciada (digital e impressa);',
            '9. Outorga preventiva/definitiva (quando exigida);',
            '10. Descrição e classificação de resíduos e efluentes;',
            '11. Cronograma físico de implantação/operacão;',
            '12. ARTs/RRTs de projetos e execução;',
            '13. Cadastro Técnico Federal (CTF);',
            '14. Publicação em Diário Oficial;',
            '15. Comprovante de pagamento;',
            '16. Estudo ambiental (EIA/RIMA, PCA ou equivalente);'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_ampliacao' => [
        'nome' => 'LA — LICENÇA DE AMPLIAÇÃO',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Certidão municipal válida por 2 anos OU alvará municipal;',
            '5. Licença anterior e relatório das condicionantes atendidas;',
            '6. Memorial descritivo da ampliação proposta;',
            '7. Projeto/Planta georreferenciada da ampliação (digital e impressa);',
            '8. Outorga preventiva/definitiva para novos usos (quando exigida);',
            '9. Descrição e classificação de resíduos e efluentes da ampliação;',
            '10. Cronograma físico da ampliação;',
            '11. ARTs/RRTs de projetos e execução;',
            '12. Cadastro Técnico Federal (CTF);',
            '13. Publicação em Diário Oficial;',
            '14. Comprovante de pagamento;',
            '15. Estudo ambiental atualizado ou complementar (quando exigido);'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'licenca_operacional_corretiva' => [
        'nome' => 'LOC — LICENÇA OPERACIONAL CORRETIVA',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Certidão municipal válida por 2 anos OU alvará municipal;',
            '5. Diagnóstico ambiental/corretivo do empreendimento;',
            '6. Memorial descritivo e plano de correção;',
            '7. Planta georreferenciada (digital e impressa);',
            '8. Outorga preventiva/definitiva (quando exigida);',
            '9. Descrição e classificação de resíduos e efluentes;',
            '10. Cronograma físico das ações corretivas;',
            '11. ARTs/RRTs de projetos e execução;',
            '12. Cadastro Técnico Federal (CTF);',
            '13. Publicação em Diário Oficial;',
            '14. Comprovante de pagamento;',
            '15. Estudo ambiental ou relatório técnico de correção (quando exigido);'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'autorizacao_supressao' => [
        'nome' => 'AUTORIZAÇÃO DE SUPRESSÃO VEGETAL',
        'documentos' => [
            '1. Requerimento assinado;',
            '2. Documentos pessoais PF/PJ (RG/CPF ou contrato social);',
            '3. Documento legal da área com firma reconhecida;',
            '4. Procuração/autorização do proprietário (quando aplicável);',
            '5. Inventário/levantamento florestal com ART;',
            '6. Planta georreferenciada com indicação das áreas de supressão;',
            '7. Laudo fotográfico e memorial descritivo;',
            '8. Outorga ou anuência do órgão competente quando exigida;',
            '9. Cronograma de supressão e plano de reposição/compensação;',
            '10. Publicação em Diário Oficial;',
            '11. Comprovante de pagamento.'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ],
    'outros' => [
        'nome' => 'OUTROS ALVARÁS',
        'documentos' => [
            'Entre em contato com a Secretaria de Meio Ambiente para obter informações sobre a documentação necessária para o alvará desejado.'
        ],
        'contato' => [
            'Dúvidas ou informações pelo WhatsApp 99668-6413.',
            'Envio de documentação para fiscalizacaosemapdf@gmail.com',
        ]
    ]
];

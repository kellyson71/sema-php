<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/documento_regras.php';

final class DocumentoRegrasTest extends TestCase
{
    public function testMontaEnderecoComLoteQuadraENumero(): void
    {
        $endereco = DocumentoRegras::formatarEndereco([
            'obra_logradouro' => 'Rua Adelino Aires',
            'obra_lote' => '11',
            'obra_quadra' => 'Única',
            'obra_numero' => '25',
            'obra_bairro' => 'Riacho do Meio',
        ]);

        self::assertSame(
            'RUA ADELINO AIRES, (LOTE 11, QUADRA ÚNICA), 25, BAIRRO RIACHO DO MEIO, PAU DOS FERROS/RN.',
            $endereco
        );
    }

    public function testEnderecoSemNumeroESemLoteOmiteParenteses(): void
    {
        $endereco = DocumentoRegras::formatarEndereco([
            'obra_logradouro' => 'Rua Adelino Aires',
            'obra_quadra' => '5',
            'obra_sem_numero' => 1,
            'obra_sem_lote_quadra' => 1,
            'obra_bairro' => 'Centro',
        ]);

        self::assertSame('RUA ADELINO AIRES, SN, BAIRRO CENTRO, PAU DOS FERROS/RN.', $endereco);
    }

    public function testEspecificacaoDeConstrucaoUsaCamposGuiados(): void
    {
        self::assertSame(
            'CONSTRUÇÃO DE UMA RESIDENCIAL UNIFAMILIAR DE DOIS PAVIMENTOS (TÉRREO E PRIMEIRO PAVIMENTO) COM 120,50 M² DE ÁREA A SER CONSTRUÍDA.',
            DocumentoRegras::especificacaoConstrucao([
                'tipo_edificacao' => 'Residencial unifamiliar',
                'numero_pavimentos' => 2,
                'area_construcao' => '120,50',
            ])
        );
    }

    public function testCaracteristicasHabiteNovoModeloComDormitoriosESuites(): void
    {
        $texto = DocumentoRegras::caracteristicasHabite([
            'habite_uso' => 'Residencial',
            'habite_pavimento' => 'Pavimento térreo',
            'area_construida' => '53,00',
            'habite_tipo_construcao' => 'Casa isolada',
            'habite_padrao' => 'Baixo',
            'habite_estrutura' => 'Alvenaria e concreto armado',
            'habite_portas' => 'Madeira',
            'habite_janelas' => 'Alumínio e vidro',
            'habite_piso' => 'Cerâmico',
            'habite_paredes' => 'Pintura',
            'habite_forro' => 'Gesso',
            'habite_cobertura' => 'Telha cerâmica',
            'habite_ambientes_json' => json_encode([
                'quartos' => 1, 'suites' => 1, 'banheiros_sociais' => 1, 'salas' => 1, 'cozinhas' => 1,
            ]),
        ]);

        self::assertStringContainsString('DOIS DORMITÓRIOS, SENDO UMA SUÍTE, UM BANHEIRO SOCIAL, UMA SALA E UMA COZINHA', $texto);
    }

    public function testCaracteristicasHabiteApenasSuite(): void
    {
        $texto = DocumentoRegras::caracteristicasHabite([
            'habite_uso' => 'Residencial',
            'habite_pavimento' => 'Pavimento térreo',
            'area_construida' => '40,00',
            'habite_tipo_construcao' => 'Casa isolada',
            'habite_padrao' => 'Normal',
            'habite_estrutura' => 'Alvenaria e concreto armado',
            'habite_portas' => 'Madeira',
            'habite_janelas' => 'Alumínio e vidro',
            'habite_piso' => 'Cerâmico',
            'habite_paredes' => 'Pintura',
            'habite_forro' => 'Gesso',
            'habite_cobertura' => 'Telha cerâmica',
            'habite_ambientes_json' => json_encode([
                'quartos' => 0, 'suites' => 1, 'banheiros_sociais' => 0,
            ]),
        ]);

        self::assertStringContainsString('CONSTITUÍDO POR UM DORMITÓRIO, SENDO UMA SUÍTE.', $texto);
    }

    public function testCaracteristicasHabiteSemSuites(): void
    {
        $texto = DocumentoRegras::caracteristicasHabite([
            'habite_uso' => 'Residencial',
            'habite_pavimento' => 'Pavimento térreo',
            'area_construida' => '60,00',
            'habite_tipo_construcao' => 'Casa isolada',
            'habite_padrao' => 'Normal',
            'habite_estrutura' => 'Alvenaria e concreto armado',
            'habite_portas' => 'Madeira',
            'habite_janelas' => 'Alumínio e vidro',
            'habite_piso' => 'Cerâmico',
            'habite_paredes' => 'Pintura',
            'habite_forro' => 'Gesso',
            'habite_cobertura' => 'Telha cerâmica',
            'habite_ambientes_json' => json_encode([
                'quartos' => 2, 'suites' => 0, 'banheiros_sociais' => 1,
            ]),
        ]);

        self::assertStringContainsString('CONSTITUÍDO POR DOIS DORMITÓRIOS E UM BANHEIRO SOCIAL.', $texto);
        self::assertStringNotContainsString('SUÍTE', $texto);
    }

    public function testCaracteristicasHabiteMultiplasSuites(): void
    {
        $texto = DocumentoRegras::caracteristicasHabite([
            'habite_uso' => 'Residencial',
            'habite_pavimento' => 'Dois pavimentos',
            'area_construida' => '150,00',
            'habite_tipo_construcao' => 'Casa isolada',
            'habite_padrao' => 'Alto',
            'habite_estrutura' => 'Alvenaria e concreto armado',
            'habite_portas' => 'Madeira',
            'habite_janelas' => 'Alumínio e vidro',
            'habite_piso' => 'Porcelanato',
            'habite_paredes' => 'Pintura',
            'habite_forro' => 'Gesso',
            'habite_cobertura' => 'Telha cerâmica',
            'habite_ambientes_json' => json_encode([
                'quartos' => 2, 'suites' => 2, 'banheiros_sociais' => 1,
            ]),
        ]);

        self::assertStringContainsString('CONSTITUÍDO POR QUATRO DORMITÓRIOS, SENDO DUAS SUÍTES E UM BANHEIRO SOCIAL.', $texto);
    }

    public function testCaracteristicasHabiteCompatibilidadeComDadosLegados(): void
    {
        $texto = DocumentoRegras::caracteristicasHabite([
            'habite_uso' => 'Residencial',
            'habite_pavimento' => 'Pavimento térreo',
            'area_construida' => '70,00',
            'habite_tipo_construcao' => 'Casa isolada',
            'habite_padrao' => 'Normal',
            'habite_estrutura' => 'Alvenaria e concreto armado',
            'habite_portas' => 'Madeira',
            'habite_janelas' => 'Alumínio e vidro',
            'habite_piso' => 'Cerâmico',
            'habite_paredes' => 'Pintura',
            'habite_forro' => 'Gesso',
            'habite_cobertura' => 'Telha cerâmica',
            'habite_ambientes_json' => json_encode([
                'quartos' => 3, 'suites' => 1, 'banheiros' => 2, 'salas' => 1, 'cozinhas' => 1,
            ]),
        ]);

        self::assertStringContainsString('TRÊS DORMITÓRIOS, SENDO UMA SUÍTE, UM BANHEIRO SOCIAL, UMA SALA E UMA COZINHA', $texto);
    }

    public function testCaracteristicasHabiteRespeitaEspecificacaoManual(): void
    {
        $customTexto = 'TEXTO DE CARACTERÍSTICAS PERSONALIZADO PELO USUÁRIO.';
        $texto = DocumentoRegras::caracteristicasHabite([
            'especificacao' => $customTexto,
            'habite_uso' => 'Comercial',
            'habite_pavimento' => 'Seis pavimentos',
        ]);
        self::assertSame($customTexto, $texto);
    }

    public function testCaracteristicasHabiteSuportaAmbientesExtras(): void
    {
        $texto = DocumentoRegras::caracteristicasHabite([
            'habite_uso' => 'Residencial',
            'habite_pavimento' => 'Pavimento térreo',
            'area_construida' => '100,00',
            'habite_tipo_construcao' => 'Casa isolada',
            'habite_padrao' => 'Normal',
            'habite_estrutura' => 'Alvenaria e concreto armado',
            'habite_portas' => 'Madeira',
            'habite_janelas' => 'Vidro',
            'habite_piso' => 'Cerâmico',
            'habite_paredes' => 'Reboco e pintura',
            'habite_forro' => 'Laje',
            'habite_cobertura' => 'Telha cerâmica',
            'habite_ambientes_json' => json_encode([
                'quartos' => 3, 'suites' => 1, 'banheiros' => 2, 'salas' => 1, 'cozinhas' => 1,
                'extras' => [
                    ['nome' => 'Varanda', 'quantidade' => 1],
                    ['nome' => 'Garagem', 'quantidade' => 2],
                ]
            ]),
        ]);

        self::assertStringContainsString('UM VARANDA E DOIS GARAGEM', $texto);
    }

    public function testDesmembramentoRepeteBlocoComCadastroPorLote(): void
    {
        $html = DocumentoRegras::lotesDesmembramentoHtml([
            'desmembramento_lotes_json' => json_encode(['lotes' => [[
                'ordem' => 1,
                'area' => 250,
                'cadastro_imobiliario' => '1010844',
                'confrontacoes' => [
                    'norte' => ['metragem' => 10, 'descricao' => 'Rua A'],
                    'oeste' => ['metragem' => 25, 'descricao' => 'Lote 2'],
                    'leste' => ['metragem' => 25, 'descricao' => 'José da Silva'],
                    'sul' => ['metragem' => 10, 'descricao' => 'Rua B'],
                ],
            ]]]),
        ]);

        self::assertStringContainsString('DESCRIÇÃO DO LOTE Nº 1</strong> DO CADASTRO 1010844 <strong>COM 250,00 M²', $html);
        self::assertStringContainsString('10,00 METROS AO NORTE CONFINANTE COM RUA A.', $html);
    }

    public function testDesmembramentoLoteIrregularUsaDescricaoLivreEmVezDeConfrontacoes(): void
    {
        $html = DocumentoRegras::lotesDesmembramentoHtml([
            'desmembramento_lotes_json' => json_encode(['lotes' => [[
                'ordem' => 1,
                'area' => 300,
                'cadastro_imobiliario' => '2020933',
                'geometria' => 'irregular',
                'descricao_irregular' => 'Lote em formato de L, com frente de 12m para a Rua X.',
                'confrontacoes' => [],
            ]]]),
        ]);

        self::assertStringContainsString('DESCRIÇÃO DO LOTE Nº 1</strong> DO CADASTRO 2020933 <strong>COM 300,00 M²', $html);
        self::assertStringContainsString('LOTE EM FORMATO DE L, COM FRENTE DE 12M PARA A RUA X.', $html);
        self::assertStringNotContainsString('CONFINANTE COM', $html);
    }

    public function testConselhoResponsavelReconheceCtfComoTerceiraOpcao(): void
    {
        self::assertSame('CREA', DocumentoRegras::conselhoResponsavel(['responsavel_tecnico_tipo_documento' => 'CREA']));
        self::assertSame('CAU', DocumentoRegras::conselhoResponsavel(['responsavel_tecnico_tipo_documento' => 'CAU']));
        self::assertSame('CTF', DocumentoRegras::conselhoResponsavel(['responsavel_tecnico_tipo_documento' => 'CTF']));
        self::assertSame('CREA', DocumentoRegras::conselhoResponsavel(['responsavel_tecnico_tipo_documento' => '']));
    }

    public function testRotuloDocumentoTecnicoParaCtfEhRegistro(): void
    {
        self::assertSame('ART', DocumentoRegras::rotuloDocumentoTecnico(['responsavel_tecnico_tipo_documento' => 'CREA']));
        self::assertSame('RRT', DocumentoRegras::rotuloDocumentoTecnico(['responsavel_tecnico_tipo_documento' => 'CAU']));
        self::assertSame('Registro', DocumentoRegras::rotuloDocumentoTecnico(['responsavel_tecnico_tipo_documento' => 'CTF']));
    }

    public function testInterpretaNumeroManualComAno(): void
    {
        self::assertSame(['numero' => 60, 'ano' => 2026], DocumentoRegras::interpretarNumero('60/2026'));
        self::assertNull(DocumentoRegras::interpretarNumero('sessenta'));
    }

    public function testReferenciaDaEdificacaoJuntaTipoEArea(): void
    {
        $this->assertSame(
            'uma edificação residencial unifamiliar com 53,00 m²',
            DocumentoRegras::edificacaoReferencia([
                'tipo_edificacao' => 'Residencial unifamiliar',
                'area_construcao' => '53,00',
            ])
        );
    }

    public function testReferenciaDaEdificacaoOmiteOTipoQuandoORequerimentoEAnteriorAoCampo(): void
    {
        // Requerimento enviado antes do formulário reformulado: sem tipo, a
        // frase precisa continuar legível para quem completa no editor.
        $this->assertSame(
            'uma edificação com 82,62 m²',
            DocumentoRegras::edificacaoReferencia(['area_construcao' => '82,62'])
        );
    }

    public function testAreaNaoDuplicaAUnidadeQuandoOCidadaoJaDigitouM2(): void
    {
        $this->assertSame('52,03', DocumentoRegras::formatarArea('52,03 m²'));
        $this->assertSame('52,03', DocumentoRegras::formatarArea('52,03m2'));
        $this->assertSame('382,90', DocumentoRegras::formatarArea('382,90 METROS QUADRADOS'));
        $this->assertSame('53,00', DocumentoRegras::formatarArea('53'));
    }
}

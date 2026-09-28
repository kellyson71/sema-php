<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/documento_regras.php';
require_once dirname(__DIR__, 2) . '/includes/assinantes_modelos.php';
require_once dirname(__DIR__, 2) . '/admin/templates/engine/DocumentBuilder.php';

final class LicenciamentoAmbientalTest extends TestCase
{
    private function pdo(): PDO
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('Driver pdo_sqlite indisponível neste PHP (presente na imagem Docker do projeto).');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE configuracoes (chave TEXT PRIMARY KEY, valor TEXT)');
        $pdo->exec('CREATE TABLE administradores (id INTEGER PRIMARY KEY, nome TEXT, nome_completo TEXT,
            matricula_portaria TEXT, nivel TEXT, ativo INTEGER)');
        $pdo->exec('CREATE TABLE assinaturas_digitais (id INTEGER PRIMARY KEY, documento_id TEXT, requerimento_id INTEGER,
            tipo_documento TEXT, substituido_por_documento_id TEXT)');
        return $pdo;
    }

    public function testValidadeEhRecebimentoMaisCincoAnos(): void
    {
        self::assertSame('27/07/2031', DocumentoRegras::validadeLicenca('2026-07-27'));
        self::assertSame('27/07/2031', DocumentoRegras::validadeLicenca('27/07/2026'));
        self::assertSame('01/03/2033', DocumentoRegras::validadeLicenca('2028-02-29'));
        self::assertSame('', DocumentoRegras::validadeLicenca(''));
        self::assertSame('', DocumentoRegras::validadeLicenca(null));
        self::assertSame('', DocumentoRegras::validadeLicenca('0000-00-00'));
    }

    public function testSegundoParecerDePendenciasViraFinal(): void
    {
        $pdo = $this->pdo();
        $ins = $pdo->prepare('INSERT INTO assinaturas_digitais (documento_id, requerimento_id, tipo_documento, substituido_por_documento_id) VALUES (?, ?, ?, ?)');

        self::assertSame('PARECER TÉCNICO – PENDÊNCIAS', DocumentoRegras::tituloParecerPendencias($pdo, 10));

        // Outro processo e outro modelo não contam; duas assinaturas do mesmo documento contam uma vez.
        $ins->execute(['x', 99, 'parecer_tecnico_pendencias_ambiental', null]);
        $ins->execute(['y', 10, 'licenca_ambiental_unica', null]);
        self::assertSame('PARECER TÉCNICO – PENDÊNCIAS', DocumentoRegras::tituloParecerPendencias($pdo, 10));

        $ins->execute(['a', 10, 'parecer_tecnico_pendencias_ambiental', null]);
        $ins->execute(['a', 10, 'parecer_tecnico_pendencias_ambiental', null]);
        self::assertSame(1, DocumentoRegras::pareceresPendenciasEmitidos($pdo, 10));
        self::assertSame('PARECER TÉCNICO FINAL', DocumentoRegras::tituloParecerPendencias($pdo, 10));
    }

    public function testVersaoRetificadaNaoContaComoParecerEmitido(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO assinaturas_digitais (documento_id, requerimento_id, tipo_documento, substituido_por_documento_id)
            VALUES ('velho', 5, 'parecer_tecnico_pendencias_ambiental', 'novo')");
        self::assertSame('PARECER TÉCNICO – PENDÊNCIAS', DocumentoRegras::tituloParecerPendencias($pdo, 5));
        self::assertSame('PARECER TÉCNICO – PENDÊNCIAS', DocumentoRegras::tituloParecerPendencias(null, 5));
    }

    public function testNomesDosDocumentosAnexadosVemDaListaDoTipo(): void
    {
        $lista = ['1. Requerimento assinado;', '2. Documentos pessoais PF/PJ;', '3. Planta georreferenciada.'];
        $nomes = DocumentoRegras::nomesDocumentosAnexados(
            ['doc_lau_2', 'doc_lau_0', 'doc_lau_0', 'boleto_pagamento_admin', 'doc_lau_9'],
            $lista
        );
        self::assertSame(['Requerimento assinado', 'Planta georreferenciada'], $nomes);

        $html = DocumentoRegras::tabelaAnaliseDocumentosHtml(['A & B']);
        self::assertStringContainsString('Análise Técnica', $html);
        self::assertStringContainsString('A &amp; B', $html);
        self::assertStringContainsString('Requerimento', DocumentoRegras::tabelaAnaliseDocumentosHtml([]));
    }

    public function testAssinantesVemDaConfiguracaoComSecretarioAutomatico(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO administradores VALUES
            (1, 'Vicente', 'Vicente de Paula Fernandes', 'Portaria 010/2025', 'secretario', 1),
            (2, 'Mailo', 'Mailodovinci de Sousa Pereira', NULL, 'analista', 1),
            (3, 'Samara', 'Samara do Nascimento Linhares', 'Matrícula 2518', 'analista', 1),
            (4, 'Inativa', 'Pessoa Inativa', NULL, 'analista', 0)");
        $pdo->exec("INSERT INTO configuracoes VALUES
            ('modelo_assinante_eng_ambiental_id', '2'),
            ('modelo_assinante_eng_ambiental_matricula', 'Mat. 3208'),
            ('modelo_assinante_fiscal_ambiental_id', '3'),
            ('modelo_assinante_fiscal_ambiental_rotulo', '')");

        $a = assinantesModelo($pdo);
        self::assertSame(1, $a['secretario']['id']);
        self::assertSame('Portaria 010/2025', $a['secretario']['matricula']);
        self::assertSame('Mat. 3208', $a['eng_ambiental']['matricula']);
        self::assertSame('Eng. Ambiental', $a['eng_ambiental']['rotulo']);
        self::assertSame('Fiscal de Meio Ambiente', $a['fiscal_ambiental']['rotulo']);
        self::assertSame('Matrícula 2518', $a['fiscal_ambiental']['matricula']);

        // LAU: os três, menos quem assina. Parecer: só eng. + fiscal. Outros modelos: ninguém.
        self::assertSame([1, 3], idsCoassinantesModelo($pdo, 'licenca_ambiental_unica', 2));
        self::assertSame([2], idsCoassinantesModelo($pdo, 'parecer_tecnico_pendencias_ambiental', 3));
        self::assertSame([], idsCoassinantesModelo($pdo, 'carta_habite_se', 3));

        // Configurado para alguém inativo: fica sem assinante.
        $pdo->exec("UPDATE configuracoes SET valor = '4' WHERE chave = 'modelo_assinante_eng_ambiental_id'");
        self::assertSame(0, assinantesModelo($pdo)['eng_ambiental']['id']);
    }

    public function testDefinicoesRenderizamComAsVariaveisEsperadas(): void
    {
        $builder = new DocumentBuilder();
        $lau = $builder->render('licenca_ambiental_unica');
        foreach (['data_recebimento_processo', 'data_validade_licenca', 'nome_empreendedor', 'caracterizacao_empreendimento',
                  'assinante_secretario_nome', 'assinante_eng_ambiental_nome', 'assinante_fiscal_ambiental_nome'] as $var) {
            self::assertStringContainsString('{{' . $var . '}}', $lau);
        }
        self::assertStringContainsString('Fiscalização Ambiental/Eng.Ambiental', $lau);

        $parecer = $builder->render('parecer_tecnico_pendencias_ambiental');
        self::assertStringContainsString('{{titulo_parecer_pendencias}}', $parecer);
        self::assertStringContainsString('{{tabela_analise_documentos_html}}', $parecer);
        self::assertStringNotContainsString('assinante_secretario', $parecer);
    }
}

<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(__DIR__, 2) . '/includes/pendencia_helpers.php';

/**
 * Fluxo de pendência/complementação (includes/pendencia_helpers.php).
 */
final class PendenciaHelpersTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('Driver pdo_sqlite indisponível neste PHP (presente na imagem Docker do projeto).');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // resolverPendencia() usa NOW(), função do MySQL sem equivalente nativo no SQLite.
        $this->pdo->sqliteCreateFunction('NOW', static fn () => date('Y-m-d H:i:s'), 0);

        $this->pdo->exec("
            CREATE TABLE administradores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE requerimento_pendencias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                requerimento_id INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                descricao TEXT NOT NULL,
                resposta TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'aberta',
                admin_id INTEGER DEFAULT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                respondido_em TIMESTAMP DEFAULT NULL,
                decidido_em TIMESTAMP DEFAULT NULL,
                resolvido_manualmente INTEGER NOT NULL DEFAULT 0,
                reaberta_de_id INTEGER DEFAULT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE documentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                requerimento_id INTEGER NOT NULL,
                campo_formulario TEXT NOT NULL,
                nome_original TEXT NOT NULL,
                nome_salvo TEXT NOT NULL,
                caminho TEXT NOT NULL
            )
        ");

        $this->pdo->exec("INSERT INTO administradores (id, nome) VALUES (1, 'Ana'), (2, 'Carlos')");
    }

    // ─── campoFormularioPendencia (pura) ────────────────────────────────────

    #[Test]
    public function formataOCampoDoFormularioComOIdDaPendencia(): void
    {
        self::assertSame('pendencia_12', campoFormularioPendencia(12));
    }

    // ─── buscarPendencia ────────────────────────────────────────────────────

    #[Test]
    public function buscaUmaPendenciaExistente(): void
    {
        $this->pdo->exec("INSERT INTO requerimento_pendencias (id, requerimento_id, titulo, descricao) VALUES (1, 10, 'Falta ART', 'Envie a ART assinada.')");

        $pendencia = buscarPendencia($this->pdo, 1);

        self::assertNotNull($pendencia);
        self::assertSame('Falta ART', $pendencia['titulo']);
    }

    #[Test]
    public function retornaNullParaPendenciaInexistente(): void
    {
        self::assertNull(buscarPendencia($this->pdo, 999));
    }

    // ─── listarPendenciasRequerimento ───────────────────────────────────────

    #[Test]
    public function listaPendenciasDoRequerimentoDaMaisRecenteParaAMaisAntiga(): void
    {
        $this->pdo->exec("INSERT INTO requerimento_pendencias (id, requerimento_id, titulo, descricao, admin_id) VALUES
            (1, 10, 'Primeira', 'desc', 1),
            (2, 10, 'Segunda', 'desc', 2),
            (3, 20, 'De outro requerimento', 'desc', 1)");

        $pendencias = listarPendenciasRequerimento($this->pdo, 10);

        self::assertCount(2, $pendencias);
        self::assertSame([2, 1], array_map(fn($p) => (int) $p['id'], $pendencias));
    }

    #[Test]
    public function trazONomeDoAdminQueCriouAPendencia(): void
    {
        $this->pdo->exec("INSERT INTO requerimento_pendencias (id, requerimento_id, titulo, descricao, admin_id) VALUES (1, 10, 'T', 'D', 1)");

        $pendencias = listarPendenciasRequerimento($this->pdo, 10);

        self::assertSame('Ana', $pendencias[0]['admin_nome']);
    }

    #[Test]
    public function listaVaziaQuandoRequerimentoNaoTemPendencias(): void
    {
        self::assertSame([], listarPendenciasRequerimento($this->pdo, 999));
    }

    // ─── listarAnexosPendencia ───────────────────────────────────────────────

    #[Test]
    public function listaSomenteOsAnexosDoCampoDaquelaPendencia(): void
    {
        $this->pdo->exec("INSERT INTO documentos (requerimento_id, campo_formulario, nome_original, nome_salvo, caminho) VALUES
            (10, 'pendencia_1', 'comprovante.pdf', 'abc.pdf', '/uploads/10/abc.pdf'),
            (10, 'pendencia_2', 'outro.pdf', 'def.pdf', '/uploads/10/def.pdf'),
            (20, 'pendencia_1', 'de_outro_req.pdf', 'ghi.pdf', '/uploads/20/ghi.pdf')");

        $anexos = listarAnexosPendencia($this->pdo, 10, 1);

        self::assertCount(1, $anexos);
        self::assertSame('comprovante.pdf', $anexos[0]['nome_original']);
    }

    #[Test]
    public function nenhumAnexoRetornaListaVazia(): void
    {
        self::assertSame([], listarAnexosPendencia($this->pdo, 10, 1));
    }

    // ─── resolverPendencia ───────────────────────────────────────────────────

    #[Test]
    public function resolveComoAceitaEMarcaResolvidoManualmenteQuandoManual(): void
    {
        $this->pdo->exec("INSERT INTO requerimento_pendencias (id, requerimento_id, titulo, descricao) VALUES (1, 10, 'T', 'D')");

        resolverPendencia($this->pdo, 1, true);

        $pendencia = buscarPendencia($this->pdo, 1);
        self::assertSame('aceita', $pendencia['status']);
        self::assertSame(1, (int) $pendencia['resolvido_manualmente']);
        self::assertNotNull($pendencia['decidido_em']);
    }

    #[Test]
    public function resolveSemMarcarManualQuandoRespondidaPeloRequerente(): void
    {
        $this->pdo->exec("INSERT INTO requerimento_pendencias (id, requerimento_id, titulo, descricao) VALUES (1, 10, 'T', 'D')");

        resolverPendencia($this->pdo, 1, false);

        $pendencia = buscarPendencia($this->pdo, 1);
        self::assertSame('aceita', $pendencia['status']);
        self::assertSame(0, (int) $pendencia['resolvido_manualmente']);
    }

    // ─── reabrirPendencia ────────────────────────────────────────────────────

    #[Test]
    public function reabrePendenciaHerdandoORequerimentoEGuardandoAOrigem(): void
    {
        $this->pdo->exec("INSERT INTO requerimento_pendencias (id, requerimento_id, titulo, descricao) VALUES (1, 10, 'Título antigo', 'Descrição antiga')");

        $novoId = reabrirPendencia($this->pdo, 1, 'Título novo', 'Ainda falta o documento X.', 2);

        $nova = buscarPendencia($this->pdo, $novoId);
        self::assertNotNull($nova);
        self::assertSame(10, (int) $nova['requerimento_id']);
        self::assertSame('Título novo', $nova['titulo']);
        self::assertSame('Ainda falta o documento X.', $nova['descricao']);
        self::assertSame(2, (int) $nova['admin_id']);
        self::assertSame(1, (int) $nova['reaberta_de_id']);
    }

    // ─── normalizarUploadMultiplo (pura) ────────────────────────────────────

    #[Test]
    public function normalizaFormatoColunarDoPhpEmListaDeArquivos(): void
    {
        $files = [
            'name'     => ['a.pdf', 'b.pdf'],
            'type'     => ['application/pdf', 'application/pdf'],
            'tmp_name' => ['/tmp/php1', '/tmp/php2'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [100, 200],
        ];

        $arquivos = normalizarUploadMultiplo($files);

        self::assertCount(2, $arquivos);
        self::assertSame('a.pdf', $arquivos[0]['name']);
        self::assertSame(200, $arquivos[1]['size']);
    }

    #[Test]
    public function ignoraSlotsVaziosDeUpload(): void
    {
        $files = [
            'name'     => ['a.pdf', '', 'c.pdf'],
            'type'     => ['application/pdf', '', 'application/pdf'],
            'tmp_name' => ['/tmp/php1', '', '/tmp/php3'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size'     => [100, 0, 300],
        ];

        $arquivos = normalizarUploadMultiplo($files);

        self::assertCount(2, $arquivos);
        self::assertSame('a.pdf', $arquivos[0]['name']);
        self::assertSame('c.pdf', $arquivos[1]['name']);
    }

    #[Test]
    public function retornaListaVaziaParaEntradaNulaOuMalformada(): void
    {
        self::assertSame([], normalizarUploadMultiplo(null));
        self::assertSame([], normalizarUploadMultiplo([]));
        self::assertSame([], normalizarUploadMultiplo(['name' => 'nao-eh-array']));
    }
}

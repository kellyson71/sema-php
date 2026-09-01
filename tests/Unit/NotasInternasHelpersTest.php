<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(__DIR__, 2) . '/includes/notas_internas_helpers.php';

/**
 * Observações internas (chat da equipe por requerimento). Usa um PDO
 * SQLite em memória com o schema mínimo — só as colunas que
 * includes/notas_internas_helpers.php realmente consulta, espelhando
 * database/2026-08-20_notas_internas_e_pendencias_resolucao.sql.
 */
final class NotasInternasHelpersTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('Driver pdo_sqlite indisponível neste PHP (presente na imagem Docker do projeto).');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE administradores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                nome_completo TEXT DEFAULT '',
                cargo TEXT DEFAULT '',
                nivel TEXT NOT NULL DEFAULT 'operador',
                email TEXT DEFAULT ''
            )
        ");

        $this->pdo->exec("
            CREATE TABLE requerimento_notas_internas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                requerimento_id INTEGER NOT NULL,
                texto TEXT NOT NULL,
                admin_id INTEGER DEFAULT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            INSERT INTO administradores (id, nome, nome_completo, cargo, nivel, email) VALUES
                (1, 'Ana', 'Ana Beatriz Lima', 'Fiscal Ambiental', 'fiscal', 'ana@example.com'),
                (2, 'Carlos', '', '', 'operador', 'carlos@example.com'),
                (3, 'Maria', 'Maria Souza', 'Secretária', 'admin_geral', 'maria@example.com')
        ");
    }

    // ─── adicionarNotaInterna / salvarNotaInterna ──────────────────────────

    #[Test]
    public function adicionaNotaEDevolveOId(): void
    {
        $id = adicionarNotaInterna($this->pdo, 42, 'Falta o comprovante de residência.', 1);

        self::assertGreaterThan(0, $id);
        $nota = $this->pdo->query('SELECT * FROM requerimento_notas_internas WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(42, (int) $nota['requerimento_id']);
        self::assertSame('Falta o comprovante de residência.', $nota['texto']);
        self::assertSame(1, (int) $nota['admin_id']);
    }

    #[Test]
    public function salvarNotaInternaEhAliasDeAdicionar(): void
    {
        salvarNotaInterna($this->pdo, 42, 'Nota via alias.', 2);

        $notas = buscarNotasInternas($this->pdo, 42);
        self::assertCount(1, $notas);
        self::assertSame('Nota via alias.', $notas[0]['texto']);
    }

    // ─── buscarNotasInternas ────────────────────────────────────────────────

    #[Test]
    public function listaNotasEmOrdemCronologicaCrescente(): void
    {
        $id1 = adicionarNotaInterna($this->pdo, 10, 'Primeira nota.', 1);
        $id2 = adicionarNotaInterna($this->pdo, 10, 'Segunda nota.', 2);
        $id3 = adicionarNotaInterna($this->pdo, 10, 'Terceira nota.', 1);

        $notas = buscarNotasInternas($this->pdo, 10);

        self::assertSame([$id1, $id2, $id3], array_map(fn($n) => (int) $n['id'], $notas));
    }

    #[Test]
    public function trazAdminNomeUsandoNomeCompletoQuandoDisponivel(): void
    {
        adicionarNotaInterna($this->pdo, 10, 'Nota da Ana.', 1);
        $notas = buscarNotasInternas($this->pdo, 10);

        self::assertSame('Ana Beatriz Lima', $notas[0]['admin_nome']);
        self::assertSame('Fiscal Ambiental', $notas[0]['admin_cargo']);
    }

    #[Test]
    public function caiParaNomeCurtoQuandoNomeCompletoEstaVazio(): void
    {
        adicionarNotaInterna($this->pdo, 10, 'Nota do Carlos.', 2);
        $notas = buscarNotasInternas($this->pdo, 10);

        self::assertSame('Carlos', $notas[0]['admin_nome']);
    }

    #[Test]
    public function naoTrazNotasDeOutroRequerimento(): void
    {
        adicionarNotaInterna($this->pdo, 10, 'Do requerimento 10.', 1);
        adicionarNotaInterna($this->pdo, 20, 'Do requerimento 20.', 1);

        self::assertCount(1, buscarNotasInternas($this->pdo, 10));
        self::assertCount(1, buscarNotasInternas($this->pdo, 20));
    }

    // ─── buscarNotaInterna ──────────────────────────────────────────────────

    #[Test]
    public function buscaApenasAMaisRecente(): void
    {
        adicionarNotaInterna($this->pdo, 10, 'Mais antiga.', 1);
        $idMaisRecente = adicionarNotaInterna($this->pdo, 10, 'Mais recente.', 2);

        $nota = buscarNotaInterna($this->pdo, 10);

        self::assertSame($idMaisRecente, (int) $nota['id']);
        self::assertSame('Mais recente.', $nota['texto']);
    }

    #[Test]
    public function retornaNullQuandoNaoHaNenhumaNota(): void
    {
        self::assertNull(buscarNotaInterna($this->pdo, 999));
    }

    // ─── excluirNotaInterna ─────────────────────────────────────────────────

    #[Test]
    public function adminDonoConsegueExcluirAPropriaNota(): void
    {
        $id = adicionarNotaInterna($this->pdo, 10, 'Nota do Carlos.', 2);

        $resultado = excluirNotaInterna($this->pdo, $id, 10, 2, false);

        self::assertTrue($resultado);
        self::assertNull(buscarNotaInterna($this->pdo, 10));
    }

    #[Test]
    public function operadorNaoConsegueExcluirNotaDeOutroAdmin(): void
    {
        $id = adicionarNotaInterna($this->pdo, 10, 'Nota da Ana.', 1);

        $resultado = excluirNotaInterna($this->pdo, $id, 10, 2, false);

        self::assertFalse($resultado, 'excluirNotaInterna deve reportar falha quando nenhuma linha é afetada.');
        $nota = buscarNotaInterna($this->pdo, 10);
        self::assertNotNull($nota, 'A nota de outro admin não deveria ter sido removida.');
        self::assertSame($id, (int) $nota['id']);
    }

    #[Test]
    public function adminGeralConsegueExcluirNotaDeQualquerAdmin(): void
    {
        $id = adicionarNotaInterna($this->pdo, 10, 'Nota da Ana.', 1);

        $resultado = excluirNotaInterna($this->pdo, $id, 10, 3, true);

        self::assertTrue($resultado);
        self::assertNull(buscarNotaInterna($this->pdo, 10));
    }

    #[Test]
    public function excluirNotaInexistenteRetornaFalse(): void
    {
        self::assertFalse(excluirNotaInterna($this->pdo, 9999, 10, 1, false));
        self::assertFalse(excluirNotaInterna($this->pdo, 9999, 10, 1, true));
    }
}

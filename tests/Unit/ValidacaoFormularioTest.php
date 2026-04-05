<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Testes das regras de validação do formulário principal.
 *
 * Como o processar_formulario.php mistura lógica com saída HTTP, os testes aqui
 * exercitam a lógica de validação de forma isolada — replicando as mesmas
 * regras aplicadas no servidor para garantir consistência.
 */
class ValidacaoFormularioTest extends TestCase
{
    // ─── Helpers de validação (espelham processar_formulario.php) ─────────────

    private function validarEmail(string $email): bool
    {
        return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
    }

    private function validarTamanhoArquivo(int $bytes): bool
    {
        return $bytes <= MAX_FILE_SIZE;
    }

    private function validarExtensaoMimeArquivo(string $extensao, string $mime): bool
    {
        return strtolower($extensao) === 'pdf' && $mime === 'application/pdf';
    }

    private function validarCamposRequerente(array $dados): array
    {
        $erros = [];
        if (empty(trim($dados['nome'] ?? ''))) {
            $erros[] = 'Nome do requerente é obrigatório.';
        }
        if (empty(trim($dados['email'] ?? '')) || !$this->validarEmail($dados['email'] ?? '')) {
            $erros[] = 'E-mail do requerente inválido.';
        }
        if (empty(trim($dados['cpf_cnpj'] ?? ''))) {
            $erros[] = 'CPF/CNPJ do requerente é obrigatório.';
        }
        if (empty(trim($dados['telefone'] ?? ''))) {
            $erros[] = 'Telefone do requerente é obrigatório.';
        }
        return $erros;
    }

    private static function carregarTipos(): array
    {
        static $tipos = null;
        if ($tipos === null) {
            $arquivo = dirname(__DIR__, 2) . '/tipos_alvara.php';
            // Usa include (não include_once) para garantir que a variável
            // seja definida no escopo correto mesmo quando chamado de dentro de métodos.
            $tipos = (static function () use ($arquivo): array {
                include $arquivo;
                return $tipos_alvara;
            })();
        }
        return $tipos;
    }

    private function isTipoAmbiental(string $tipo): bool
    {
        $tipos = self::carregarTipos();
        return ($tipos[$tipo]['categoria'] ?? '') === 'ambiental';
    }

    private function tipoExigeCtf(string $tipo): bool
    {
        $tipos = self::carregarTipos();
        return (bool) ($tipos[$tipo]['exige_ctf'] ?? false);
    }

    // ─── Validação de email ────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('emailsValidosProvider')]
    public function emailsValidosPassamValidacao(string $email): void
    {
        $this->assertTrue($this->validarEmail($email), "'{$email}' deveria ser válido.");
    }

    public static function emailsValidosProvider(): array
    {
        return [
            ['joao@example.com'],
            ['maria.silva@prefeitura.gov.br'],
            ['admin+tag@dominio.com.br'],
            ['usuario123@email.org'],
        ];
    }

    #[Test]
    #[DataProvider('emailsInvalidosProvider')]
    public function emailsInvalidosReprovamValidacao(string $email): void
    {
        $this->assertFalse($this->validarEmail($email), "'{$email}' deveria ser inválido.");
    }

    public static function emailsInvalidosProvider(): array
    {
        return [
            ['nao-e-email'],
            ['@semdominio.com'],
            ['semdobrela.com'],
            [''],
            ['espaco no meio@email.com'],
        ];
    }

    // ─── Validação de tamanho de arquivo ──────────────────────────────────────

    #[Test]
    public function arquivoDentroDo10MbEValido(): void
    {
        $this->assertTrue($this->validarTamanhoArquivo(5 * 1024 * 1024)); // 5 MB
    }

    #[Test]
    public function arquivoExatamente10MbEValido(): void
    {
        $this->assertTrue($this->validarTamanhoArquivo(10 * 1024 * 1024)); // 10 MB
    }

    #[Test]
    public function arquivoAcimaDe10MbEInvalido(): void
    {
        $this->assertFalse($this->validarTamanhoArquivo(10 * 1024 * 1024 + 1));
    }

    #[Test]
    public function arquivoZeroBytesEValido(): void
    {
        $this->assertTrue($this->validarTamanhoArquivo(0));
    }

    // ─── Validação de extensão e MIME ─────────────────────────────────────────

    #[Test]
    public function pdfComMimeCorretoEAceito(): void
    {
        $this->assertTrue($this->validarExtensaoMimeArquivo('pdf', 'application/pdf'));
    }

    #[Test]
    public function pdfComMimeErradoERecusado(): void
    {
        $this->assertFalse($this->validarExtensaoMimeArquivo('pdf', 'text/plain'));
    }

    #[Test]
    public function extensaoNaoPdfERecusada(): void
    {
        $this->assertFalse($this->validarExtensaoMimeArquivo('doc', 'application/pdf'));
    }

    #[Test]
    public function extensaoJpgComMimePdfERecusada(): void
    {
        $this->assertFalse($this->validarExtensaoMimeArquivo('jpg', 'application/pdf'));
    }

    // ─── Validação dos campos do requerente ───────────────────────────────────

    #[Test]
    public function dadosRequerenteCompletosNaoGeramErros(): void
    {
        $dados = [
            'nome'     => 'João da Silva',
            'email'    => 'joao@email.com',
            'cpf_cnpj' => '123.456.789-01',
            'telefone' => '(84) 99999-9999',
        ];
        $this->assertEmpty($this->validarCamposRequerente($dados));
    }

    #[Test]
    public function nomeFazendoFaltaGeraErro(): void
    {
        $dados = [
            'nome'     => '',
            'email'    => 'joao@email.com',
            'cpf_cnpj' => '12345678901',
            'telefone' => '84999999999',
        ];
        $erros = $this->validarCamposRequerente($dados);
        $this->assertNotEmpty($erros);
        $this->assertStringContainsString('Nome', $erros[0]);
    }

    #[Test]
    public function emailInvalidoGeraErro(): void
    {
        $dados = [
            'nome'     => 'Maria',
            'email'    => 'email-invalido',
            'cpf_cnpj' => '12345678901',
            'telefone' => '84999999999',
        ];
        $erros = $this->validarCamposRequerente($dados);
        $this->assertNotEmpty($erros);
        $this->assertStringContainsString('mail', $erros[0]);
    }

    #[Test]
    public function cpfFazendoFaltaGeraErro(): void
    {
        $dados = [
            'nome'     => 'Maria',
            'email'    => 'maria@email.com',
            'cpf_cnpj' => '',
            'telefone' => '84999999999',
        ];
        $erros = $this->validarCamposRequerente($dados);
        $this->assertNotEmpty($erros);
    }

    #[Test]
    public function telefoneFazendoFaltaGeraErro(): void
    {
        $dados = [
            'nome'     => 'Maria',
            'email'    => 'maria@email.com',
            'cpf_cnpj' => '12345678901',
            'telefone' => '',
        ];
        $erros = $this->validarCamposRequerente($dados);
        $this->assertNotEmpty($erros);
    }

    // ─── Lógica de categorias de alvará ───────────────────────────────────────

    #[Test]
    public function construcaoNaoEAmbiental(): void
    {
        $this->assertFalse($this->isTipoAmbiental('construcao'));
    }

    #[Test]
    public function licencaPreviaEAmbiental(): void
    {
        $this->assertTrue($this->isTipoAmbiental('licenca_previa_ambiental'));
    }

    #[Test]
    public function construcaoNaoExigeCtf(): void
    {
        $this->assertFalse($this->tipoExigeCtf('construcao'));
    }

    #[Test]
    public function licencaOperacaoExigeCtf(): void
    {
        $this->assertTrue($this->tipoExigeCtf('licenca_operacao'));
    }

    // ─── Sanitização contra XSS ───────────────────────────────────────────────

    #[Test]
    public function sanitizeProtegeTodosInputsDoFormulario(): void
    {
        $inputs = [
            'nome'            => '<script>alert(1)</script>João',
            'endereco'        => '"><img src=x onerror=alert(1)>',
            'observacoes'     => "'; DROP TABLE requerimentos; --",
        ];

        foreach ($inputs as $campo => $valor) {
            $resultado = sanitize($valor);
            $this->assertStringNotContainsString('<script>', $resultado, "Campo '{$campo}' não foi sanitizado.");
            $this->assertStringNotContainsString('<img', $resultado, "Campo '{$campo}' não foi sanitizado.");
        }
    }
}

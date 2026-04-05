<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testes unitários para includes/functions.php
 */
class FunctionsTest extends TestCase
{
    // ─── gerarProtocolo ───────────────────────────────────────────────────────

    #[Test]
    public function gerarProtocoloRetornaStringNaoVazia(): void
    {
        $protocolo = gerarProtocolo();
        $this->assertNotEmpty($protocolo);
        $this->assertIsString($protocolo);
    }

    #[Test]
    public function gerarProtocoloTemFormatoCorreto(): void
    {
        $protocolo = gerarProtocolo();
        // Formato: YmdHis (14 dígitos) + 3 dígitos aleatórios = 17 dígitos
        $this->assertMatchesRegularExpression('/^\d{17}$/', $protocolo);
    }

    #[Test]
    public function gerarProtocoloEUnico(): void
    {
        // O protocolo usa date('YmdHis') + rand(100,999). Chamadas no mesmo segundo
        // compartilham o mesmo timestamp, então testamos unicidade com uma margem:
        // ao gerar 10 protocolos esperamos pelo menos 8 únicos.
        $protocolos = [];
        for ($i = 0; $i < 10; $i++) {
            $protocolos[] = gerarProtocolo();
        }
        $this->assertGreaterThanOrEqual(8, count(array_unique($protocolos)),
            'Muitas colisões de protocolo geradas em sequência.');
    }

    // ─── sanitize ─────────────────────────────────────────────────────────────

    #[Test]
    public function sanitizeEscapaTagsHtml(): void
    {
        $resultado = sanitize('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $resultado);
        $this->assertStringContainsString('&lt;script&gt;', $resultado);
    }

    #[Test]
    public function sanitizeEscapaAspas(): void
    {
        $resultado = sanitize('"aspas duplas" e \'aspas simples\'');
        $this->assertStringContainsString('&quot;', $resultado);
        $this->assertStringContainsString('&#039;', $resultado);
    }

    #[Test]
    public function sanitizeNaoAlteraTextoSimples(): void
    {
        $texto = 'João Silva - Rua das Flores, 123';
        $this->assertSame($texto, sanitize($texto));
    }

    #[Test]
    public function sanitizeStringVaziaRetornaVazia(): void
    {
        $this->assertSame('', sanitize(''));
    }

    // ─── formatarStatus ───────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('statusProvider')]
    public function formatarStatusNormalizaCorretamente(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, formatarStatus($entrada));
    }

    public static function statusProvider(): array
    {
        return [
            'analise minusculo'       => ['analise', 'Em Análise'],
            'em analise com acento'   => ['Em Análise', 'Em Análise'],
            'em analise sem acento'   => ['em analise', 'Em Análise'],
            'aprovado minusculo'      => ['aprovado', 'Aprovado'],
            'aprovado maiusculo'      => ['APROVADO', 'Aprovado'],
            'rejeitado'               => ['rejeitado', 'Rejeitado'],
            'reprovado vira rejeitado'=> ['reprovado', 'Rejeitado'],
            'pendente'                => ['pendente', 'Pendente'],
            'status desconhecido'     => ['arquivado', 'arquivado'],
        ];
    }

    // ─── formatarData ─────────────────────────────────────────────────────────

    #[Test]
    public function formatarDataRetornaFormatoBrasileiro(): void
    {
        $resultado = formatarData('2025-06-15 14:30:00');
        $this->assertSame('15/06/2025 14:30', $resultado);
    }

    #[Test]
    public function formatarDataHoraRetornaFormatoCompleto(): void
    {
        $resultado = formatarDataHora('2025-06-15 14:30:45');
        $this->assertSame('15/06/2025 às 14:30:45', $resultado);
    }

    #[Test]
    public function formatarDataHoraStringVaziaRetornaMensagem(): void
    {
        $resultado = formatarDataHora('');
        $this->assertSame('Data não informada', $resultado);
    }

    // ─── formatarCpfCnpj ──────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('cpfCnpjProvider')]
    public function formatarCpfCnpjAplicaMascaraCorreta(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, formatarCpfCnpj($entrada));
    }

    public static function cpfCnpjProvider(): array
    {
        return [
            'CPF somente digitos'       => ['12345678901', '123.456.789-01'],
            'CPF com mascara existente' => ['123.456.789-01', '123.456.789-01'],
            'CPF curto completa zeros'  => ['1234567', '000.012.345-67'],
            'CNPJ somente digitos'      => ['12345678000195', '12.345.678/0001-95'],
            'CNPJ com mascara'          => ['12.345.678/0001-95', '12.345.678/0001-95'],
            'string vazia retorna msg'  => ['', 'Não informado'],
            'so letras retorna msg'     => ['abc', 'Não informado'],
        ];
    }

    // ─── formatarTamanho ──────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('tamanhoProvider')]
    public function formatarTamanhoRetornaUnidadeCorreta(int $bytes, string $esperado): void
    {
        $this->assertSame($esperado, formatarTamanho($bytes));
    }

    public static function tamanhoProvider(): array
    {
        return [
            'bytes'      => [512, '512 B'],
            'kilobytes'  => [2048, '2 KB'],
            'megabytes'  => [5 * 1024 * 1024, '5 MB'],
            'gigabytes'  => [2 * 1024 * 1024 * 1024, '2 GB'],
            'zero bytes' => [0, '0 B'],
        ];
    }

    // ─── formatarNomeMes ──────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('mesProvider')]
    public function formatarNomeMesRetornaPortugues(int $mes, string $esperado): void
    {
        $this->assertSame($esperado, formatarNomeMes($mes));
    }

    public static function mesProvider(): array
    {
        return [
            'janeiro'   => [1, 'Janeiro'],
            'junho'     => [6, 'Junho'],
            'dezembro'  => [12, 'Dezembro'],
            'invalido'  => [13, ''],
            'zero'      => [0, ''],
        ];
    }

    // ─── setMensagem / getMensagem ────────────────────────────────────────────

    #[Test]
    public function mensagemFlashEArmazenadaERecuperada(): void
    {
        setMensagem('sucesso', 'Operação realizada com sucesso!');
        $mensagem = getMensagem();

        $this->assertIsArray($mensagem);
        $this->assertSame('sucesso', $mensagem['tipo']);
        $this->assertSame('Operação realizada com sucesso!', $mensagem['texto']);
    }

    #[Test]
    public function getMensagemLimpaAposLeitura(): void
    {
        setMensagem('erro', 'Algo deu errado.');
        getMensagem(); // Consome a mensagem
        $this->assertNull(getMensagem()); // Não deve existir mais
    }

    #[Test]
    public function getMensagemRetornaNullQuandoNaoExiste(): void
    {
        unset($_SESSION['mensagem']);
        $this->assertNull(getMensagem());
    }

    // ─── isAdmin ──────────────────────────────────────────────────────────────

    #[Test]
    public function isAdminRetornaTrueQuandoSessaoAtiva(): void
    {
        $_SESSION['admin'] = true;
        $this->assertTrue(isAdmin());
    }

    #[Test]
    public function isAdminRetornaFalseQuandoSemSessao(): void
    {
        unset($_SESSION['admin']);
        $this->assertFalse(isAdmin());
    }

    #[Test]
    public function isAdminRetornaFalseQuandoSessaoFalsa(): void
    {
        $_SESSION['admin'] = false;
        $this->assertFalse(isAdmin());
    }
}

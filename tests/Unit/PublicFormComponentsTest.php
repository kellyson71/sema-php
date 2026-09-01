<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Funções de suporte do formulário público reformulado (identificação
 * unificada, composer de endereço). Ver includes/public_form_components.php.
 */
final class PublicFormComponentsTest extends TestCase
{
    // ─── publicFormEscape ──────────────────────────────────────────────────

    #[Test]
    public function escapaCaracteresHtmlEspeciais(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
            publicFormEscape("<script>alert('x')</script>")
        );
    }

    #[Test]
    public function preservaAcentuacaoAoEscapar(): void
    {
        self::assertSame('São João da Construção', publicFormEscape('São João da Construção'));
    }

    #[Test]
    public function escapaAspasDuplas(): void
    {
        self::assertSame('&quot;citado&quot;', publicFormEscape('"citado"'));
    }

    // ─── emailRequerenteValido ─────────────────────────────────────────────

    #[Test]
    #[DataProvider('emailsValidos')]
    public function aceitaEmailsValidos(string $email): void
    {
        self::assertTrue(emailRequerenteValido($email));
    }

    public static function emailsValidos(): array
    {
        return [
            'simples' => ['joao@example.com'],
            'com subdominio' => ['joao@mail.example.com.br'],
            'com espaços nas pontas' => ['  joao@example.com  '],
        ];
    }

    #[Test]
    #[DataProvider('emailsInvalidos')]
    public function rejeitaEmailsInvalidos(string $email): void
    {
        self::assertFalse(emailRequerenteValido($email));
    }

    public static function emailsInvalidos(): array
    {
        return [
            'vazio' => [''],
            'só espaços' => ['   '],
            'sem arroba' => ['joao.example.com'],
            'domínio incompleto' => ['joao@example'],
            'com espaço no meio' => ['jo ao@example.com'],
            'maior que 191 caracteres' => [str_repeat('a', 185) . '@ex.com'],
        ];
    }

    // ─── emailsRequerenteCoincidem ─────────────────────────────────────────

    #[Test]
    public function consideraEmailsIguaisComoCoincidentes(): void
    {
        self::assertTrue(emailsRequerenteCoincidem('joao@example.com', 'joao@example.com'));
    }

    #[Test]
    public function ignoraDiferencaDeMaiusculasEEspacosNasPontas(): void
    {
        self::assertTrue(emailsRequerenteCoincidem('Joao@Example.com', '  joao@example.com  '));
    }

    #[Test]
    public function rejeitaEmailsDiferentes(): void
    {
        self::assertFalse(emailsRequerenteCoincidem('joao@example.com', 'maria@example.com'));
    }

    #[Test]
    public function rejeitaConfirmacaoVazia(): void
    {
        self::assertFalse(emailsRequerenteCoincidem('joao@example.com', ''));
    }

    // ─── enderecoPauDosFerrosValido ────────────────────────────────────────

    #[Test]
    #[DataProvider('enderecosValidos')]
    public function aceitaEnderecosBemFormados(string $endereco): void
    {
        self::assertTrue(enderecoPauDosFerrosValido($endereco));
    }

    public static function enderecosValidos(): array
    {
        return [
            'com número' => ['RUA DAS FLORES, 123, BAIRRO CENTRO, PAU DOS FERROS/RN.'],
            'sem número (SN)' => ['RUA DAS FLORES, SN, BAIRRO CENTRO, PAU DOS FERROS/RN.'],
            'sem ponto final' => ['RUA DAS FLORES, 123, BAIRRO CENTRO, PAU DOS FERROS/RN'],
            'com lote e quadra' => ['RUA DAS FLORES, (LOTE 1, QUADRA 2) 123, BAIRRO CENTRO, PAU DOS FERROS/RN.'],
            'numero com letra' => ['RUA DAS FLORES, 123A, BAIRRO CENTRO, PAU DOS FERROS/RN.'],
        ];
    }

    #[Test]
    #[DataProvider('enderecosInvalidos')]
    public function rejeitaEnderecosIncompletos(string $endereco): void
    {
        self::assertFalse(enderecoPauDosFerrosValido($endereco));
    }

    public static function enderecosInvalidos(): array
    {
        return [
            'vazio' => [''],
            'só espaços' => ['   '],
            'sem cidade' => ['RUA DAS FLORES, 123, BAIRRO CENTRO'],
            'sem bairro' => ['RUA DAS FLORES, 123, PAU DOS FERROS/RN.'],
            'cidade errada' => ['RUA DAS FLORES, 123, BAIRRO CENTRO, NATAL/RN.'],
        ];
    }

    // ─── renderLocationComposer ────────────────────────────────────────────

    #[Test]
    public function refleteNomeLabelEValorNoHtmlGerado(): void
    {
        $html = renderLocationComposer('proprietario_endereco', 'Local da ocorrência', true, 'RUA X, SN, BAIRRO Y, PAU DOS FERROS/RN.');

        self::assertStringContainsString('Local da ocorrência', $html);
        self::assertStringContainsString('name="proprietario_endereco"', $html);
        self::assertStringContainsString('value="RUA X, SN, BAIRRO Y, PAU DOS FERROS/RN."', $html);
    }

    #[Test]
    public function campoObrigatorioMarcaRequiredEAsteriscoNoLabel(): void
    {
        $html = renderLocationComposer('proprietario_endereco', 'Local da ocorrência', true);

        self::assertStringNotContainsString('data-location-optional', $html);
        self::assertStringContainsString('required data-required="true"', $html);
        self::assertStringContainsString('Rua / logradouro *', $html);
    }

    #[Test]
    public function campoOpcionalNaoMarcaRequiredNemAsterisco(): void
    {
        $html = renderLocationComposer('proprietario_endereco', 'Local da ocorrência', false);

        self::assertStringContainsString('data-location-optional="true"', $html);
        self::assertStringNotContainsString('required data-required="true"', $html);
        self::assertStringContainsString('Rua / logradouro"', $html);
    }

    #[Test]
    public function campoEnderecoObjetivoUsaNomesPrefixadosComObraNosSubcampos(): void
    {
        $html = renderLocationComposer('endereco_objetivo', 'Localização da obra ou objetivo', true);

        self::assertStringContainsString('name="obra_logradouro"', $html);
        self::assertStringContainsString('name="obra_bairro"', $html);
        self::assertStringContainsString('name="obra_lote"', $html);
        self::assertStringContainsString('name="obra_quadra"', $html);
        self::assertStringContainsString('name="obra_numero"', $html);
        self::assertStringContainsString('name="obra_sem_lote_quadra"', $html);
        self::assertStringContainsString('name="obra_sem_numero"', $html);
    }

    #[Test]
    public function camposQueNaoSaoEnderecoObjetivoNaoGeramCheckboxesDeAusencia(): void
    {
        $html = renderLocationComposer('proprietario_endereco', 'Local da ocorrência', true);

        self::assertStringNotContainsString('obra_sem_lote_quadra', $html);
        self::assertStringNotContainsString('obra_sem_numero', $html);
        self::assertStringNotContainsString('name="obra_logradouro"', $html);
    }

    #[Test]
    public function escapaValorHtmlPerigosoNoValorDoHidden(): void
    {
        $html = renderLocationComposer('endereco_objetivo', 'Localização', true, '"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}

<?php
/**
 * PdfService — Gerador de PDF via Puppeteer (Headless Chrome)
 *
 * Suporta dois modos de operação:
 *   1. HTTP: chamada ao microserviço Node.js (rápido, ~0.5s)
 *   2. CLI:  chamada direta ao script generate.js (sem daemon, ~3-5s)
 *
 * Se o microserviço estiver indisponível, tenta CLI automaticamente.
 * Se ambos falharem, cai no fallback TCPDF (compatibilidade total).
 *
 * Uso:
 *   $pdf = new PdfService();
 *   $pdf->generateToFile($html, '/tmp/documento.pdf');
 *   // ou
 *   $pdfBinary = $pdf->generate($html);
 */

class PdfService
{
    private string $serviceUrl;
    private string $secret;
    private string $nodeBin;
    private string $generateScript;
    private int $timeout;

    public function __construct(array $options = [])
    {
        // URL do microserviço (modo HTTP)
        $this->serviceUrl = $options['service_url']
            ?? (defined('PDF_SERVICE_URL') ? PDF_SERVICE_URL : 'http://127.0.0.1:3001');

        // Token de autenticação
        $this->secret = $options['secret']
            ?? (defined('PDF_SERVICE_SECRET') ? PDF_SERVICE_SECRET : '');

        // Caminho do Node.js (modo CLI)
        $this->nodeBin = $options['node_bin']
            ?? (defined('PDF_NODE_BIN') ? PDF_NODE_BIN : $this->detectNodeBin());

        // Caminho do script generate.js
        $this->generateScript = $options['generate_script']
            ?? dirname(__DIR__) . '/services/pdf/generate.js';

        // Timeout em segundos
        $this->timeout = $options['timeout'] ?? 30;
    }

    /**
     * Gera PDF a partir de HTML e retorna o binário.
     *
     * @param string $html HTML do documento (fragmento ou completo)
     * @param array  $options Opções extras (landscape, headerHtml, footerHtml)
     * @return string Conteúdo binário do PDF
     * @throws Exception Se todos os métodos falharem
     */
    public function generate(string $html, array $options = []): string
    {
        // Tenta modo HTTP primeiro (mais rápido)
        $pdf = $this->tryHttp($html, $options);
        if ($pdf !== false) {
            return $pdf;
        }

        // Fallback: modo CLI
        $pdf = $this->tryCli($html);
        if ($pdf !== false) {
            return $pdf;
        }

        throw new \Exception('PdfService: falha em todos os métodos de geração (HTTP e CLI)');
    }

    /**
     * Gera PDF e salva diretamente em arquivo.
     *
     * @param string $html HTML do documento
     * @param string $outputPath Caminho completo do arquivo PDF
     * @param array  $options Opções extras
     * @return bool true em caso de sucesso
     */
    public function generateToFile(string $html, string $outputPath, array $options = []): bool
    {
        // Tenta CLI direto para arquivo (mais eficiente)
        if ($this->tryCliToFile($html, $outputPath)) {
            return true;
        }

        // Fallback: gera em memória e salva
        $pdf = $this->generate($html, $options);
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($outputPath, $pdf) !== false;
    }

    /**
     * Verifica se o serviço está disponível.
     */
    public function isAvailable(): bool
    {
        try {
            $ch = curl_init($this->serviceUrl . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return ($data['status'] ?? '') === 'ok';
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return false;
    }

    /**
     * Verifica se o CLI está disponível.
     */
    public function isCliAvailable(): bool
    {
        return !empty($this->nodeBin)
            && file_exists($this->generateScript)
            && is_executable($this->nodeBin);
    }

    // ── Modo HTTP ────────────────────────────────────────────────────

    private function tryHttp(string $html, array $options): string|false
    {
        try {
            $payload = json_encode([
                'html'    => $html,
                'options' => $options,
            ]);

            $ch = curl_init($this->serviceUrl . '/generate');
            $headers = ['Content-Type: application/json'];
            if ($this->secret) {
                $headers[] = 'Authorization: Bearer ' . $this->secret;
            }

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && strpos($contentType, 'application/pdf') !== false) {
                return $response;
            }

            error_log("PdfService HTTP falhou: code=$httpCode, error=$error");
            return false;
        } catch (\Throwable $e) {
            error_log("PdfService HTTP exception: " . $e->getMessage());
            return false;
        }
    }

    // ── Modo CLI ─────────────────────────────────────────────────────

    private function tryCli(string $html): string|false
    {
        if (!$this->isCliAvailable()) {
            return false;
        }

        try {
            $tmpInput  = tempnam(sys_get_temp_dir(), 'sema_pdf_in_') . '.html';
            $tmpOutput = tempnam(sys_get_temp_dir(), 'sema_pdf_out_') . '.pdf';

            file_put_contents($tmpInput, $html);

            $cmd = sprintf(
                '%s %s %s %s 2>&1',
                escapeshellarg($this->nodeBin),
                escapeshellarg($this->generateScript),
                escapeshellarg($tmpInput),
                escapeshellarg($tmpOutput)
            );

            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($tmpOutput) && filesize($tmpOutput) > 0) {
                $pdf = file_get_contents($tmpOutput);
                @unlink($tmpInput);
                @unlink($tmpOutput);
                return $pdf;
            }

            error_log("PdfService CLI falhou: exit=$exitCode, output=" . implode("\n", $output));
            @unlink($tmpInput);
            @unlink($tmpOutput);
            return false;
        } catch (\Throwable $e) {
            error_log("PdfService CLI exception: " . $e->getMessage());
            return false;
        }
    }

    private function tryCliToFile(string $html, string $outputPath): bool
    {
        if (!$this->isCliAvailable()) {
            return false;
        }

        try {
            $tmpInput = tempnam(sys_get_temp_dir(), 'sema_pdf_in_') . '.html';
            file_put_contents($tmpInput, $html);

            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $cmd = sprintf(
                '%s %s %s %s 2>&1',
                escapeshellarg($this->nodeBin),
                escapeshellarg($this->generateScript),
                escapeshellarg($tmpInput),
                escapeshellarg($outputPath)
            );

            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            @unlink($tmpInput);

            if ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                return true;
            }

            error_log("PdfService CLI-to-file falhou: exit=$exitCode, output=" . implode("\n", $output));
            return false;
        } catch (\Throwable $e) {
            error_log("PdfService CLI-to-file exception: " . $e->getMessage());
            return false;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function detectNodeBin(): string
    {
        // Hostinger: Node.js fica em /opt/alt/
        $candidates = [
            '/opt/alt/alt-nodejs20/root/usr/bin/node',
            '/opt/alt/alt-nodejs22/root/usr/bin/node',
            '/opt/alt/alt-nodejs18/root/usr/bin/node',
            '/usr/local/bin/node',
            '/usr/bin/node',
        ];

        foreach ($candidates as $bin) {
            if (file_exists($bin) && is_executable($bin)) {
                return $bin;
            }
        }

        // Último recurso: confiar no PATH
        $which = trim(shell_exec('which node 2>/dev/null') ?? '');
        return $which ?: '';
    }

    /**
     * Constrói o HTML do carimbo de assinatura eletrônica.
     *
     * @param array $assinante Dados do assinante: nome, cargo, cpf, matricula, data_hora
     * @return string HTML do carimbo
     */
    public static function buildCarimboAssinatura(array $assinante): string
    {
        $nome = htmlspecialchars(strtoupper($assinante['nome'] ?? ''));
        $cargo = htmlspecialchars($assinante['cargo'] ?? '');
        $cpf = htmlspecialchars($assinante['cpf'] ?? '');
        $matricula = htmlspecialchars($assinante['matricula'] ?? '');
        $dataHora = htmlspecialchars($assinante['data_hora'] ?? date('d/m/Y \à\s H:i:s'));

        $cpfMat = '';
        if ($cpf) $cpfMat .= "CPF: $cpf";
        if ($matricula) $cpfMat .= ($cpfMat ? ' | ' : '') . "Mat: $matricula";

        return <<<HTML
<div class="carimbo-assinatura" style="margin-top:20px; float:right; width:240px; border:1px solid #ddd; border-radius:4px; padding:8px 12px; background:#fdfffe; font-family:Helvetica,Arial,sans-serif;">
    <div class="titulo-carimbo" style="font-size:6pt; font-weight:bold; color:#2d8661; text-align:center; margin-bottom:4px; text-transform:uppercase; border:none; padding:0;">ASSINATURA ELETRÔNICA SEMA</div>
    <div class="nome-carimbo" style="font-size:8pt; font-weight:bold; text-align:center; color:#1e1e1e;">{$nome}</div>
    <div class="info-carimbo" style="font-size:6pt; color:#646464; text-align:center; line-height:1.5;">
        {$cargo}<br>
        {$cpfMat}<br>
        Autenticado em: {$dataHora}
    </div>
</div>
<div style="clear:both;"></div>
HTML;
    }
}

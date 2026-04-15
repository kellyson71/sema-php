<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
class ParecerService
{
    private $templatesPath;
    private $uploadsPath;

    public function __construct()
    {
        $this->templatesPath = dirname(__DIR__) . '/assets/doc/';
        $this->uploadsPath = dirname(__DIR__) . '/uploads/pareceres/';

        if (!is_dir($this->uploadsPath)) {
            mkdir($this->uploadsPath, 0755, true);
        }
    }

    public function listarTemplates()
    {
        $templates = [];
        if (is_dir($this->templatesPath)) {
            $files = scandir($this->templatesPath);
            foreach ($files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['docx', 'html'])) {
                    $templates[] = [
                        'nome' => $file,
                        'tipo' => $ext === 'html' ? 'html' : 'docx',
                        'label' => $this->gerarLabelAmigavel($file),
                        'descricao' => $this->gerarDescricaoTemplate($file)
                    ];
                }
            }
        }
        return $templates;
    }

    private function gerarLabelAmigavel($nomeArquivo)
    {
        $nomeSemExtensao = pathinfo($nomeArquivo, PATHINFO_FILENAME);
        $legivel = str_replace(['_', '-'], ' ', $nomeSemExtensao);
        $legivel = preg_replace('/\s+/', ' ', trim($legivel));

        return ucwords($legivel);
    }

    private function gerarDescricaoTemplate($nomeArquivo)
    {
        $nome = strtolower(pathinfo($nomeArquivo, PATHINFO_FILENAME));

        if (str_contains($nome, 'template_oficial')) {
            return 'Layout oficial em A4 com fundo e área para assinatura.';
        }

        if (str_contains($nome, 'licenca_previa')) {
            return 'Modelo estruturado para licenças prévias com campos obrigatórios.';
        }

        if (str_contains($nome, 'licenca_atividade')) {
            return 'Parecer técnico de viabilidade ambiental para licença de atividade econômica.';
        }

        if (str_contains($nome, 'detalhado')) {
            return 'Parecer técnico detalhado com checklist de verificação e condicionantes.';
        }

        if (str_contains($nome, 'padrao')) {
            return 'Modelo padrão com resumo da análise e bloco de responsáveis.';
        }

        return 'Modelo disponível para edição no editor online.';
    }

    public function carregarTemplate($nomeTemplate)
    {
        $templatePath = $this->templatesPath . $nomeTemplate;

        if (!file_exists($templatePath)) {
            throw new Exception("Template não encontrado: {$nomeTemplate}");
        }

        return $templatePath;
    }

    public function verificarTipoTemplate($nomeTemplate)
    {
        $ext = pathinfo($nomeTemplate, PATHINFO_EXTENSION);
        if (strtolower($ext) === 'html') {
            if (strpos(strtolower($nomeTemplate), 'template_oficial_a4') !== false || strpos(strtolower($nomeTemplate), 'licenca_previa_projeto') !== false || strpos(strtolower($nomeTemplate), 'licenca_') !== false || strpos(strtolower($nomeTemplate), 'parecer_tecnico') !== false) {
                return 'oficial_a4';
            }
            return 'html';
        }
        return 'docx';
    }

    public function preencherDados($requerimento, $adminData = null)
    {
        // Lógica para definir a área construída/do lote
        $area = '';
        if (!empty($requerimento['area_construida'])) {
            $area = $requerimento['area_construida'];
        } elseif (!empty($requerimento['area_construcao'])) {
            $area = $requerimento['area_construcao'];
        } elseif (!empty($requerimento['area_lote'])) {
            $area = $requerimento['area_lote'];
        }

        $artNumero = $requerimento['responsavel_tecnico_numero'] ?? $requerimento['responsavel_tecnico_registro'] ?? '';
        $artNumero = trim($artNumero) !== '' ? $artNumero : 'a ser informado';

        $especificacao = $requerimento['especificacao'] ?? '';
        $especificacao = trim($especificacao) !== '' ? $especificacao : 'a ser informada';

        $nomeInteressado = $requerimento['proprietario_nome'] ?? $requerimento['requerente_nome'] ?? '';
        $cpfInteressado = $requerimento['proprietario_cpf_cnpj'] ?? $requerimento['requerente_cpf_cnpj'] ?? '';
        $atividade = $requerimento['atividade'] ?? $especificacao;
        $atividade = trim($atividade) !== '' ? $atividade : 'a ser informada';
        $cnaeDescricao = $requerimento['cnae_descricao'] ?? $especificacao;
        $cnaeDescricao = trim($cnaeDescricao) !== '' ? $cnaeDescricao : 'a ser informada';

        $dados = [
            'protocolo' => $requerimento['protocolo'] ?? '',
            'nome_requerente' => $requerimento['requerente_nome'] ?? '',
            'cpf_cnpj_requerente' => $requerimento['requerente_cpf_cnpj'] ?? '',
            'email_requerente' => $requerimento['requerente_email'] ?? '',
            'telefone_requerente' => $requerimento['requerente_telefone'] ?? '',
            'endereco_objetivo' => $requerimento['endereco_objetivo'] ?? '',
            'tipo_alvara' => (function() use ($requerimento) {
                $slug = $requerimento['tipo_alvara'] ?? '';
                static $tipos = null;
                if ($tipos === null) {
                    $arquivo = dirname(__DIR__) . '/tipos_alvara.php';
                    if (file_exists($arquivo)) { include $arquivo; $tipos = $tipos_alvara ?? []; }
                    else { $tipos = []; }
                }
                return $tipos[$slug]['nome'] ?? ucwords(str_replace('_', ' ', $slug));
            })(),
            'status' => $requerimento['status'] ?? '',
            'data_envio' => isset($requerimento['data_envio']) ? date('d/m/Y H:i', strtotime($requerimento['data_envio'])) : '',
            'data_atual' => date('d/m/Y'),
            'nome_proprietario' => $requerimento['proprietario_nome'] ?? '',
            'cpf_cnpj_proprietario' => $requerimento['proprietario_cpf_cnpj'] ?? '',
            'observacoes' => $requerimento['observacoes'] ?? '',
            'responsavel_tecnico_nome' => $requerimento['responsavel_tecnico_nome'] ?? '',
            'responsavel_tecnico_registro' => $requerimento['responsavel_tecnico_registro'] ?? '',
            'responsavel_tecnico_numero' => $requerimento['responsavel_tecnico_numero'] ?? '',
            'responsavel_tecnico_tipo_documento' => $requerimento['responsavel_tecnico_tipo_documento'] ?? '',
            'especificacao' => $especificacao,
            'art_numero' => $artNumero,
            'area_construida' => $area !== '' ? $area : 'a ser informada',
            'area' => $area !== '' ? $area : 'a ser informada',
            'detalhes_imovel' => $especificacao,
            'area_lote' => $requerimento['area_lote'] ?? '',
            'nome_interessado' => $nomeInteressado,
            'cpf_interessado' => $cpfInteressado,
            'atividade' => $atividade,
            'cnae_descricao' => $cnaeDescricao
        ];

        if ($adminData !== null) {
            $dados['admin_nome_completo'] = $adminData['nome_completo'] ?? $adminData['nome'] ?? '';
            $dados['admin_cargo'] = $adminData['cargo'] ?? '';
            $dados['admin_matricula_portaria'] = $adminData['matricula_portaria'] ?? '';
        }

        // Substituir campos vazios por "Não informado"
        foreach ($dados as $k => $v) {
            if (is_string($v) && trim($v) === '') {
                $dados[$k] = 'Não informado';
            }
        }

        // Calcular número sequencial do documento no ano
        try {
            $db = new Database();
            $anoAtual = date('Y');
            // Conta documentos assinados neste ano para gerar sequencial
            $sql = "SELECT COUNT(*) as total FROM assinaturas_digitais WHERE YEAR(timestamp_assinatura) = :ano";
            $resultado = $db->query($sql, ['ano' => $anoAtual])->fetch();
            $proximoNumero = ($resultado['total'] ?? 0) + 1;
            
            $dados['numero_documento_ano'] = $proximoNumero;
            $dados['ano_atual'] = $anoAtual;
        } catch (Exception $e) {
            $dados['numero_documento_ano'] = '??';
            $dados['ano_atual'] = date('Y');
        }

        return $dados;
    }

    public function substituirVariaveisDocx($templatePath, $dados)
    {
        $tipoTemplate = $this->verificarTipoTemplate($templatePath);

        if ($tipoTemplate === 'oficial_a4') {
            return $this->processarTemplateA4($templatePath, $dados);
        }

        if ($tipoTemplate === 'html') {
            return $this->processarTemplateHtml($templatePath, $dados);
        }

        try {
            $phpWord = IOFactory::load($templatePath);

            $tempDir = sys_get_temp_dir() . '/phpword_images_' . uniqid();
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            Settings::setOutputEscapingEnabled(true);

            $imagesMap = $this->extrairImagensDoDocxZip($templatePath, $tempDir);

            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');

            ob_start();
            $htmlWriter->save('php://output');
            $html = ob_get_clean();

            $html = $this->processarImagensHtml($html, $imagesMap);
            $html = $this->melhorarPreservacaoEstilos($html);

            $this->limparDiretorioTemporario($tempDir);

            foreach ($dados as $variavel => $valor) {
                $html = str_replace('{{' . $variavel . '}}', htmlspecialchars($valor), $html);
            }

            // Variáveis não mapeadas
            $html = preg_replace('/\{\{[^}]+\}\}/', 'Não informado', $html);

            return $html;

        } catch (Exception $e) {
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->limparDiretorioTemporario($tempDir);
            }
            throw new Exception("Erro ao processar template: " . $e->getMessage());
        }
    }

    private function processarTemplateA4($templatePath, $dados)
    {
        $html = file_get_contents($templatePath);

        foreach ($dados as $variavel => $valor) {
            $html = str_replace('{{' . $variavel . '}}', htmlspecialchars($valor), $html);
        }

        // Variáveis não mapeadas
        $html = preg_replace('/\{\{[^}]+\}\}/', 'Não informado', $html);

        return self::extrairConteudoTemplate($html);
    }

    private function processarTemplateHtml($templatePath, $dados)
    {
        $html = file_get_contents($templatePath);

        foreach ($dados as $variavel => $valor) {
            $html = str_replace('{{' . $variavel . '}}', htmlspecialchars($valor), $html);
        }

        // Variáveis não mapeadas
        $html = preg_replace('/\{\{[^}]+\}\}/', 'Não informado', $html);

        $html = $this->processarImagensRelativas($html, dirname($templatePath));

        return $html;
    }

    /**
     * Extrai apenas o conteúdo interno de templates no formato A4 legado.
     * Templates legados têm: #documento > #fundo-imagem + #conteudo.
     * Templates novos (fragmentos limpos) não têm essa estrutura e são retornados intactos.
     */
    public static function extrairConteudoTemplate(string $html): string
    {
        if (strpos($html, 'id="conteudo"') === false
            && strpos($html, "id='conteudo'") === false) {
            return $html;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $conteudoDiv = $doc->getElementById('conteudo');
        if (!$conteudoDiv) {
            return $html;
        }

        $innerHtml = '';
        foreach ($conteudoDiv->childNodes as $child) {
            $innerHtml .= $doc->saveHTML($child);
        }

        return trim($innerHtml) ?: $html;
    }

    private function processarImagensRelativas($html, $baseDir)
    {
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']*?)["\']([^>]*?)>/i',
            function($matches) use ($baseDir) {
                $src = $matches[2];

                if (strpos($src, 'data:') === 0 || strpos($src, 'http') === 0) {
                    return $matches[0];
                }

                $imagePath = '';

                if (strpos($src, '../') === 0) {
                    $relativePath = substr($src, 3);
                    $imagePath = dirname($baseDir) . '/' . $relativePath;
                } elseif (strpos($src, '/') === 0) {
                    $imagePath = dirname(dirname(__DIR__)) . $src;
                } else {
                    $imagePath = $baseDir . '/' . $src;
                }

                $imagePath = str_replace('//', '/', $imagePath);

                if (file_exists($imagePath)) {
                    $imageData = file_get_contents($imagePath);
                    $imageInfo = @getimagesize($imagePath);
                    if ($imageInfo) {
                        $mimeType = $imageInfo['mime'];
                        $base64 = base64_encode($imageData);
                        $dataUri = 'data:' . $mimeType . ';base64,' . $base64;
                        return '<img' . $matches[1] . 'src="' . $dataUri . '"' . $matches[3] . '>';
                    }
                }

                return $matches[0];
            },
            $html
        );

        return $html;
    }

    private function melhorarPreservacaoEstilos($html)
    {
        $cssCustomizado = '
        <style>
            body {
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
                margin: 2cm;
            }
            p {
                margin: 6pt 0;
                text-align: justify;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin: 10pt 0;
            }
            table td, table th {
                border: 1px solid #000;
                padding: 5pt;
                vertical-align: top;
            }
            img {
                max-width: 100%;
                height: auto;
                display: block;
                margin: 10pt auto;
            }
            .MsoNormal {
                margin: 0;
            }
            h1, h2, h3, h4, h5, h6 {
                margin: 12pt 0 6pt 0;
                font-weight: bold;
            }
            strong, b {
                font-weight: bold;
            }
            em, i {
                font-style: italic;
            }
            u {
                text-decoration: underline;
            }
            div {
                margin: 0;
            }
        </style>';

        if (stripos($html, '<head>') !== false) {
            $html = preg_replace('/<head>/i', '<head>' . $cssCustomizado, $html);
        } elseif (stripos($html, '<body>') !== false) {
            $html = preg_replace('/<body>/i', $cssCustomizado . '<body>', $html);
        } else {
            $html = $cssCustomizado . $html;
        }

        $html = preg_replace_callback('/style="([^"]*?)"/i', function($matches) {
            $style = $matches[1];
            $style = preg_replace('/mso-[^;]+;?/i', '', $style);
            $style = preg_replace('/\s+/', ' ', trim($style));
            return 'style="' . $style . '"';
        }, $html);

        return $html;
    }

    private function extrairImagensDoDocxZip($templatePath, $tempDir)
    {
        $imagesMap = [];

        try {
            $zip = new ZipArchive();
            if ($zip->open($templatePath) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (preg_match('/^word\/media\/(.+)$/i', $filename, $matches)) {
                        $imageName = $matches[1];
                        $imageData = $zip->getFromIndex($i);

                        if ($imageData) {
                            $extension = pathinfo($imageName, PATHINFO_EXTENSION);
                            $tempFile = $tempDir . '/' . uniqid() . '_' . $imageName;
                            file_put_contents($tempFile, $imageData);

                            $imageInfo = @getimagesize($tempFile);
                            if ($imageInfo) {
                                $imagesMap[$imageName] = $tempFile;
                                $imagesMap[basename($imageName)] = $tempFile;
                            }
                        }
                    }
                }
                $zip->close();
            }
        } catch (Exception $e) {
        }

        return $imagesMap;
    }

    private function processarImagensHtml($html, $imagesMap)
    {
        if (empty($imagesMap)) {
            return $html;
        }

        foreach ($imagesMap as $imageName => $imagePath) {
            if (file_exists($imagePath)) {
                $imageData = file_get_contents($imagePath);
                $imageInfo = @getimagesize($imagePath);
                if ($imageInfo) {
                    $mimeType = $imageInfo['mime'];
                    $base64 = base64_encode($imageData);
                    $dataUri = 'data:' . $mimeType . ';base64,' . $base64;

                    $escapedName = preg_quote($imageName, '/');
                    $escapedBaseName = preg_quote(basename($imageName), '/');

                    $patterns = [
                        '/(<img[^>]*src=["\'])([^"\']*' . $escapedName . ')(["\'][^>]*>)/i',
                        '/(<img[^>]*src=["\'])([^"\']*\/media\/' . $escapedName . ')(["\'][^>]*>)/i',
                        '/(<img[^>]*src=["\'])([^"\']*' . $escapedBaseName . ')(["\'][^>]*>)/i',
                        '/(<img[^>]*src=["\'])([^"\']*\/media\/' . $escapedBaseName . ')(["\'][^>]*>)/i',
                        '/(<img[^>]*src=["\'])([^"\']*\/word\/media\/' . $escapedName . ')(["\'][^>]*>)/i',
                        '/(<img[^>]*src=["\'])([^"\']*\/word\/media\/' . $escapedBaseName . ')(["\'][^>]*>)/i'
                    ];

                    foreach ($patterns as $pattern) {
                        $html = preg_replace($pattern, '$1' . $dataUri . '$3', $html);
                    }

                        $html = preg_replace_callback(
                        '/(<img[^>]*src=["\'])([^"\']*)(["\'][^>]*>)/i',
                            function($matches) use ($dataUri, $imageName, $imagePath) {
                            $src = $matches[2];
                            if (strpos($src, 'data:') === 0) {
                                return $matches[0];
                            }

                            if (strpos($src, $imageName) !== false ||
                                strpos($src, basename($imageName)) !== false ||
                                strpos($src, basename($imagePath)) !== false ||
                                (strpos($src, 'media') !== false && strpos($src, 'image') !== false)) {
                                    return $matches[1] . $dataUri . $matches[3];
                                }
                                return $matches[0];
                            },
                            $html
                        );
                    }
                }
            }

        $html = preg_replace_callback(
            '/(<img[^>]*)(>)/i',
            function($matches) {
                $imgTag = $matches[1];
                if (strpos($imgTag, 'style=') === false) {
                    $imgTag .= ' style="max-width: 100%; height: auto; display: block; margin: 10pt auto;"';
                }
                return $imgTag . $matches[2];
            },
            $html
        );

        return $html;
    }

    private function limparDiretorioTemporario($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . '/' . $file;
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
        rmdir($dir);
    }

    public function salvarParecerHtml($requerimento_id, $html, $templateNome)
    {
        $pastaRequerimento = $this->uploadsPath . $requerimento_id . '/';

        if (!is_dir($pastaRequerimento)) {
            mkdir($pastaRequerimento, 0755, true);
        }

        $timestamp = date('YmdHis');
        $nomeBase = 'parecer_' . pathinfo($templateNome, PATHINFO_FILENAME) . '_' . $timestamp;
        $nomeArquivoHtml = $nomeBase . '.html';
        $nomeArquivoJson = $nomeBase . '.json';

        $caminhoHtml = $pastaRequerimento . $nomeArquivoHtml;
        $caminhoJson = $pastaRequerimento . $nomeArquivoJson;

        $htmlFinal = $this->garantirEstruturaHtmlCompleta($html);
        file_put_contents($caminhoHtml, $htmlFinal);

        return [
            'nome' => $nomeArquivoHtml,
            'caminho' => $caminhoHtml,
            'caminho_relativo' => 'pareceres/' . $requerimento_id . '/' . $nomeArquivoHtml,
            'caminho_json' => $caminhoJson,
            'caminho_json_relativo' => 'pareceres/' . $requerimento_id . '/' . $nomeArquivoJson,
            'timestamp' => $timestamp
        ];
    }

    private function garantirEstruturaHtmlCompleta($html)
    {
        if (stripos($html, '<!DOCTYPE') === false) {
            if (stripos($html, '<html') === false) {
                $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
            } else {
                if (stripos($html, '<head') === false && stripos($html, '<html') !== false) {
                    $html = preg_replace('/<html[^>]*>/i', '$0<head><meta charset="UTF-8"></head>', $html);
                }
                if (stripos($html, '<body') === false && stripos($html, '</head>') !== false) {
                    $html = preg_replace('/<\/head>/i', '</head><body>', $html);
                    if (stripos($html, '</html>') === false) {
                        $html .= '</body></html>';
                    }
                }
            }
        }

        if (stripos($html, '@page') === false) {
            $cssPrint = '<style>
                @page {
                    size: A4;
                    margin: 0;
                }
                * {
                    box-sizing: border-box;
                }
                html, body {
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: "Times New Roman", Times, serif;
                    font-size: 12pt;
                    line-height: 1.6;
                    color: #000;
                }
                @media print {
                    body {
                        background: white;
                    }
                    * {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>';

            if (stripos($html, '</head>') !== false) {
                $html = str_replace('</head>', $cssPrint . '</head>', $html);
            } elseif (stripos($html, '<body') !== false) {
                $html = preg_replace('/<body[^>]*>/i', $cssPrint . '$0', $html);
            } else {
                $html = $cssPrint . $html;
            }
        }

        return $html;
    }

    public function listarPareceres($requerimento_id)
    {
        global $pdo;
        
        // Garantir que $pdo existe (em alguns contextos de include pode haver problema de escopo)
        if (!isset($pdo) || !$pdo) {
            try {
                // Tenta incluir conexao.php para recuperar o $pdo se ele sumiu
                @require_once dirname(__DIR__) . '/admin/conexao.php';
            } catch (\Exception $e) {}
        }
        
        $pareceres = [];
        $arquivosJaAdicionados = []; // evita duplicatas

        // ── 1. Fontes do banco de dados (assinaturas_digitais) ────────────────
        // Inclui arquivos salvos em admin/pareceres/{id}/ pelo processa_assinatura.php
        try {
            $stmt = $pdo->prepare("
                SELECT ad.documento_id, ad.nome_arquivo, ad.caminho_arquivo,
                       ad.timestamp_assinatura, ad.assinante_nome, ad.tipo_documento
                FROM assinaturas_digitais ad
                WHERE ad.requerimento_id = ?
                ORDER BY ad.timestamp_assinatura DESC
            ");
            $stmt->execute([$requerimento_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $caminho = $row['caminho_arquivo'];
                
                // Tentar resolver o caminho físico (várias tentativas)
                if (!file_exists($caminho)) {
                    // Tentativa 1: relativo à raiz
                    $tentativa1 = dirname(__DIR__) . '/' . ltrim($row['caminho_arquivo'], '/');
                    if (file_exists($tentativa1)) {
                        $caminho = $tentativa1;
                    } else {
                        // Tentativa 2: relativo a admin/ (onde processa_assinatura salva)
                        $tentativa2 = dirname(__DIR__) . '/admin/' . ltrim($row['caminho_arquivo'], '/');
                        if (file_exists($tentativa2)) {
                            $caminho = $tentativa2;
                        }
                    }
                }
                $ext = strtolower(pathinfo($row['nome_arquivo'], PATHINFO_EXTENSION));

                $tamanho = file_exists($caminho) ? filesize($caminho) : 0;
                $data    = date('d/m/Y H:i', strtotime($row['timestamp_assinatura']));

                $pareceres[] = [
                    'nome'        => $row['nome_arquivo'],
                    'arquivo'     => $row['nome_arquivo'],
                    'caminho'     => $caminho,
                    'data'        => $data,
                    'tamanho'     => $tamanho,
                    'tipo'        => $ext ?: 'html',
                    'documento_id'=> $row['documento_id'],
                    'assinante'   => $row['assinante_nome'],
                ];
                $arquivosJaAdicionados[] = $row['nome_arquivo'];
            }
        } catch (\Exception $e) {
            // fallback silencioso — continua para varredura de disco
        }

        // ── 2. Varredura de disco (uploads/pareceres/{id}/) — fallback ────────
        $pastaRequerimento = $this->uploadsPath . $requerimento_id . '/';
        if (is_dir($pastaRequerimento)) {
            $files = scandir($pastaRequerimento);
            foreach ($files as $file) {
                if (in_array($file, $arquivosJaAdicionados)) continue; // já listado
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'html'])) continue;

                $caminhoCompleto = $pastaRequerimento . $file;
                $parecerData = [
                    'nome'        => $file,
                    'arquivo'     => $file,
                    'caminho'     => $caminhoCompleto,
                    'data'        => date('d/m/Y H:i', filemtime($caminhoCompleto)),
                    'tamanho'     => filesize($caminhoCompleto),
                    'tipo'        => $ext,
                    'documento_id'=> null,
                    'assinante'   => null,
                ];

                // Tentar enriquecer com JSON lateral
                $caminhoJson = $pastaRequerimento . pathinfo($file, PATHINFO_FILENAME) . '.json';
                if (file_exists($caminhoJson)) {
                    $jsonData = json_decode(file_get_contents($caminhoJson), true);
                    if ($jsonData) {
                        $parecerData['documento_id'] = $jsonData['documento_id'] ?? null;
                        $parecerData['assinante']    = $jsonData['dados_assinatura']['assinante_nome']
                            ?? $jsonData['dados_assinatura']['assinante_nome_completo']
                            ?? null;
                    }
                }

                $pareceres[]            = $parecerData;
                $arquivosJaAdicionados[] = $file;
            }
        }

        return $pareceres;
    }


    public function excluirParecer($requerimento_id, $nomeArquivo)
    {
        global $pdo;
        
        // Garantir que $pdo existe
        if (!isset($pdo) || !$pdo) {
            try {
                @require_once dirname(__DIR__) . '/admin/conexao.php';
            } catch (\Exception $e) {}
        }
        
        $sucesso = false;

        // 1. Tentar excluir do banco de dados (novo fluxo)
        // Precisamos encontrar qual documento_id corresponde a este nome_arquivo
        if (isset($pdo) && $pdo) {
            try {
                $stmtBusca = $pdo->prepare("SELECT documento_id, caminho_arquivo FROM assinaturas_digitais WHERE requerimento_id = ? AND nome_arquivo = ? LIMIT 1");
                $stmtBusca->execute([$requerimento_id, $nomeArquivo]);
                $doc = $stmtBusca->fetch(PDO::FETCH_ASSOC);

                if ($doc) {
                    $caminhoNoBanco = $doc['caminho_arquivo'];
                    
                    // Excluir do banco
                    $stmtDel = $pdo->prepare("DELETE FROM assinaturas_digitais WHERE documento_id = ?");
                    $stmtDel->execute([$doc['documento_id']]);
                    $sucesso = true; // Se estava no banco e excluiu, conta como sucesso
                    
                    // Tentar excluir o arquivo listado no banco
                    $caminhosParaTestar = [
                        $caminhoNoBanco,
                        dirname(__DIR__) . '/' . ltrim($caminhoNoBanco, '/'),
                        dirname(__DIR__) . '/admin/' . ltrim($caminhoNoBanco, '/')
                    ];
                    
                    foreach ($caminhosParaTestar as $cPath) {
                        if (file_exists($cPath)) {
                            @unlink($cPath);
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Falha silenciosa no BD, tenta via filepath clássico
            }
        }

        // 2. Fluxo clássico (pasta uploads/pareceres/{id}/)
        $caminhoCompleto = $this->uploadsPath . $requerimento_id . '/' . $nomeArquivo;

        if (file_exists($caminhoCompleto)) {
            @unlink($caminhoCompleto);
            $sucesso = true;
        }
        
        // 3. Limpar arquivos secundários (.json, .pdf) na pasta clássica
        $baseName = pathinfo($nomeArquivo, PATHINFO_FILENAME);
        $arquivosAssociados = glob($this->uploadsPath . $requerimento_id . '/' . $baseName . '.*');
        if ($arquivosAssociados) {
            foreach ($arquivosAssociados as $arq) {
                if (file_exists($arq)) {
                    @unlink($arq);
                }
            }
        }

        return $sucesso;
    }

    public function downloadParecer($requerimento_id, $nomeArquivo)
    {
        $caminhoCompleto = $this->uploadsPath . $requerimento_id . '/' . $nomeArquivo;

        if (file_exists($caminhoCompleto)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
            header('Content-Length: ' . filesize($caminhoCompleto));
            readfile($caminhoCompleto);
            exit;
        }

        return false;
    }

    /**
     * Carrega um template HTML processando imagens para data URI, mas SEM substituir variáveis.
     * Usado para carregar o template no editor com highlight de variáveis.
     */
    public function prepararTemplateParaEditor(string $templatePath): string
    {
        $html = file_get_contents($templatePath);
        $html = $this->processarImagensRelativas($html, dirname($templatePath));
        $html = self::removerEstilosTemplate($html);
        return self::extrairConteudoTemplate($html);
    }

    /**
     * Substitui variáveis {{var}} no HTML envolvendo-as em spans destacados.
     * Usado no editor para indicar visualmente os campos preenchidos automaticamente.
     */
    public static function aplicarHighlights(string $html, array $dados): string
    {
        foreach ($dados as $variavel => $valor) {
            $valorSeguro = htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
            $varSegura   = htmlspecialchars($variavel, ENT_QUOTES, 'UTF-8');
            $html = str_replace(
                '{{' . $variavel . '}}',
                '<span class="var-field" data-var="' . $varSegura . '">' . $valorSeguro . '</span>',
                $html
            );
        }
        return $html;
    }

    /**
     * Converte spans var-field de volta para {{variavel}}.
     * Usado ao salvar como template — preserva apenas as variáveis que o usuário manteve.
     */
    public static function converterSpansParaVariaveis(string $html): string
    {
        return preg_replace(
            '/<span[^>]+class=["\']var-field["\'][^>]+data-var=["\']([^"\']+)["\'][^>]*>(?:(?!<\/span>)[\s\S])*<\/span>/U',
            '{{$1}}',
            $html
        );
    }

    /**
     * Remove spans var-field, mantendo apenas o valor de texto (para geração de PDF).
     */
    public static function stripVarSpans(string $html): string
    {
        return preg_replace(
            '/<span[^>]+class=["\']var-field["\'][^>]*>((?:(?!<\/span>)[\s\S])*)<\/span>/U',
            '$1',
            $html
        );
    }

    /**
     * Remove tags <style> do HTML, mantendo apenas o conteúdo editável.
     */
    public static function removerEstilosTemplate(string $html): string
    {
        return preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $html);
    }
}

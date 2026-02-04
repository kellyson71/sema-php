<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use Dompdf\Dompdf;
use Dompdf\Options;
use Gumlet\ImageResize;

class ParecerService
{
    private $templatesPath;
    private $uploadsPath;
    private $tempFiles = [];

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
            if (strpos(strtolower($nomeTemplate), 'template_oficial_a4') !== false || strpos(strtolower($nomeTemplate), 'licenca_previa_projeto') !== false || strpos(strtolower($nomeTemplate), 'parecer_tecnico') !== false) {
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

        $dados = [
            'protocolo' => $requerimento['protocolo'] ?? '',
            'nome_requerente' => $requerimento['requerente_nome'] ?? '',
            'cpf_cnpj_requerente' => $requerimento['requerente_cpf_cnpj'] ?? '',
            'email_requerente' => $requerimento['requerente_email'] ?? '',
            'telefone_requerente' => $requerimento['requerente_telefone'] ?? '',
            'endereco_objetivo' => $requerimento['endereco_objetivo'] ?? '',
            'tipo_alvara' => $requerimento['tipo_alvara'] ?? '',
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
            'especificacao' => $requerimento['especificacao'] ?? '',
            // Campos unificados para o template
            'area_construida' => $area, // Usado genericamente nos templates como área principal
            'area_lote' => $requerimento['area_lote'] ?? ''
        ];

        if ($adminData !== null) {
            $dados['admin_nome_completo'] = $adminData['nome_completo'] ?? $adminData['nome'] ?? '';
            $dados['admin_cargo'] = $adminData['cargo'] ?? '';
            $dados['admin_matricula_portaria'] = $adminData['matricula_portaria'] ?? '';
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

        $imagePath = dirname(__DIR__) . '/assets/doc/images/image1.png';

        if (file_exists($imagePath)) {
            $imageData = file_get_contents($imagePath);
            $imageInfo = @getimagesize($imagePath);
            if ($imageInfo) {
                $mimeType = $imageInfo['mime'];
                $base64 = base64_encode($imageData);
                $dataUri = 'data:' . $mimeType . ';base64,' . $base64;
                $html = str_replace('src="images/image1.png"', 'src="' . $dataUri . '"', $html);
            }
        }

        return $html;
    }

    private function processarTemplateHtml($templatePath, $dados)
    {
        $html = file_get_contents($templatePath);

        foreach ($dados as $variavel => $valor) {
            $html = str_replace('{{' . $variavel . '}}', htmlspecialchars($valor), $html);
        }

        $html = $this->processarImagensRelativas($html, dirname($templatePath));

        return $html;
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

    public function converterHtmlParaPdf($html, $caminhoSaida)
    {
        try {
            $options = new Options();
            $options->set('defaultFont', 'Times-Roman');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('chroot', dirname(__DIR__));
            $options->set('enableCssFloat', true);
            $options->set('enableFontSubsetting', true);

            $dompdf = new Dompdf($options);

            $html = $this->prepararHtmlParaPdf($html);

            $debugFile = dirname($caminhoSaida) . '/debug_html_' . time() . '.html';
            file_put_contents($debugFile, $html);
            error_log("HTML debug salvo em: " . $debugFile);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');

            error_log("Iniciando renderização do PDF...");
            $dompdf->render();
            error_log("Renderização concluída.");

            $output = $dompdf->output();
            error_log("PDF gerado, tamanho: " . strlen($output) . " bytes");

            // Limpar arquivos temporários após a renderização
            if (isset($this->tempFiles)) {
                foreach ($this->tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        @unlink($tempFile);
                        error_log("Arquivo temporário removido: " . $tempFile);
                    }
                }
                $this->tempFiles = [];
            }

            if (empty($output) || strlen($output) < 1000) {
                error_log("PDF vazio ou muito pequeno. Tamanho: " . strlen($output));
                error_log("HTML Debug salvo em: " . $debugFile);
                throw new Exception("PDF gerado está vazio (" . strlen($output) . " bytes). Verifique o arquivo de debug: " . basename($debugFile) . ". Possíveis causas: extensão GD não habilitada ou problemas na renderização do HTML.");
            }

            if (!is_dir(dirname($caminhoSaida))) {
                mkdir(dirname($caminhoSaida), 0755, true);
            }

            file_put_contents($caminhoSaida, $output);
            error_log("PDF salvo com sucesso em: " . $caminhoSaida);

            return true;

        } catch (Exception $e) {
            error_log("Erro ao gerar PDF: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            throw new Exception("Erro ao gerar PDF: " . $e->getMessage());
        }
    }

    private function prepararHtmlParaPdf($html)
    {
        $ehTemplateA4 = strpos($html, 'id="documento"') !== false || strpos($html, 'id=\'documento\'') !== false;

        if ($ehTemplateA4) {
            $html = $this->prepararTemplateA4ParaPdf($html);
        } else {
            $html = $this->preservarEstilosHtml($html);
            $html = $this->normalizarEstilosParaPdf($html);
            $html = $this->garantirEstruturaHtml($html);
        }

        return $html;
    }

    private function prepararTemplateA4ParaPdf($html)
    {
        error_log("=== prepararTemplateA4ParaPdf INICIO ===");
        error_log("HTML recebido (primeiros 500 chars): " . substr($html, 0, 500));

        $parser = new DOMDocument();
        libxml_use_internal_errors(true);
        @$parser->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $documentoDiv = $parser->getElementById('documento');
        $imgFundo = null;
        $imgSrc = '';

        error_log("documentoDiv encontrado: " . ($documentoDiv ? 'SIM' : 'NÃO'));

        if ($documentoDiv) {
            $imgFundo = $parser->getElementById('fundo-imagem');

            error_log("imgFundo encontrado: " . ($imgFundo ? 'SIM' : 'NÃO'));

            if ($imgFundo) {
                $imgSrc = $imgFundo->getAttribute('src');
                error_log("imgSrc atual: " . substr($imgSrc, 0, 100) . "...");
            }

            // Sempre converter PNG para JPEG, mesmo se já estiver em base64
            $precisaConverter = false;
            $ehPngBase64 = false;
            if (!empty($imgSrc)) {
                if (strpos($imgSrc, 'data:image/png') === 0 || stripos($imgSrc, 'data:image/png') !== false) {
                    $precisaConverter = true;
                    $ehPngBase64 = true;
                    error_log("Imagem é PNG base64, precisa converter para JPEG");
                } elseif (strpos($imgSrc, 'data:image/jpeg') === 0 || stripos($imgSrc, 'data:image/jpg') !== false) {
                    error_log("Imagem já está em formato JPEG base64, usando diretamente");
                    $precisaConverter = false;
                } elseif (strpos($imgSrc, 'data:') === false) {
                    $precisaConverter = true;
                    error_log("Imagem não está em base64, precisa carregar e converter");
                }
            } else {
                $precisaConverter = true;
                error_log("imgSrc vazio, precisa carregar imagem");
            }

            if ($precisaConverter) {
                $imagePath = dirname(__DIR__) . '/assets/doc/images/image1.png';

                // Se já está em base64 PNG, salvar temporariamente e converter
                if ($ehPngBase64 && !empty($imgSrc)) {
                    error_log("Extraindo PNG de base64 para converter...");
                    $base64Data = substr($imgSrc, strpos($imgSrc, ',') + 1);
                    // Salvar dentro do diretório do projeto para que o DomPDF possa acessar (chroot)
                    $tempDir = dirname(__DIR__) . '/uploads/temp/';
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }
                    $tempPng = $tempDir . 'temp_png_' . uniqid() . '.png';
                    $decoded = base64_decode($base64Data, true);
                    if ($decoded !== false) {
                        file_put_contents($tempPng, $decoded);
                        $imagePath = $tempPng;
                        error_log("PNG temporário salvo: " . $tempPng . " (tamanho: " . strlen($decoded) . " bytes)");
                    } else {
                        error_log("ERRO: Falha ao decodificar base64 PNG");
                        $ehPngBase64 = false;
                    }
                }

                error_log("Tentando carregar imagem de: " . $imagePath);
                error_log("Arquivo existe: " . (file_exists($imagePath) ? 'SIM' : 'NÃO'));

                if (file_exists($imagePath)) {
                    try {
                        // Converter PNG para JPEG usando Gumlet (mais compatível com DomPDF)
                        // Salvar dentro do diretório do projeto para que o DomPDF possa acessar (chroot)
                        $tempDir = dirname(__DIR__) . '/uploads/temp/';
                        if (!is_dir($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }
                        $tempJpeg = $tempDir . 'temp_bg_' . uniqid() . '.jpg';
                        $imageResize = new ImageResize($imagePath);
                        // Manter as dimensões originais, apenas converter formato
                        $imageResize->save($tempJpeg, IMAGETYPE_JPEG, 95);

                        if (file_exists($tempJpeg)) {
                            error_log("JPEG temporário criado: " . $tempJpeg . " (tamanho: " . filesize($tempJpeg) . " bytes)");

                            // Usar caminho relativo ao chroot do DomPDF
                            $relativePath = str_replace(dirname(__DIR__) . '/', '', $tempJpeg);
                            error_log("Caminho relativo da imagem: " . $relativePath);

                            // Atualizar imgSrc para usar o caminho relativo (não base64)
                            $imgSrc = $relativePath;

                            if ($imgFundo) {
                                $imgFundo->setAttribute('src', $relativePath);
                                error_log("Imagem JPEG atualizada no elemento existente com caminho relativo: " . $relativePath);
                            }

                            // Guardar o caminho para limpar depois da renderização
                            $this->tempFiles[] = $tempJpeg;
                            if (isset($tempPng) && file_exists($tempPng)) {
                                $this->tempFiles[] = $tempPng;
                            }
                        } else {
                            throw new Exception("Não foi possível converter a imagem para JPEG");
                        }
                    } catch (Exception $e) {
                        error_log("ERRO ao converter imagem com Gumlet: " . $e->getMessage());
                        error_log("Trace: " . $e->getTraceAsString());
                        // Fallback: tentar usar PNG original
                        if (isset($tempPng) && file_exists($tempPng)) {
                            $imagePath = $tempPng;
                        }
                        $imageData = file_get_contents($imagePath);
                        $imageInfo = @getimagesize($imagePath);
                        if ($imageInfo) {
                            $mimeType = $imageInfo['mime'];
                            $base64 = base64_encode($imageData);
                            $imgSrc = 'data:' . $mimeType . ';base64,' . $base64;
                            error_log("Usando PNG original como fallback");
                            if ($imgFundo) {
                                $imgFundo->setAttribute('src', $imgSrc);
                            }
                        }
                        if (isset($tempPng) && file_exists($tempPng)) {
                            @unlink($tempPng);
                        }
                    }
                } else {
                    error_log("ERRO: Arquivo de imagem não encontrado!");
                }
            } else {
                error_log("Imagem já está em formato JPEG base64, usando diretamente");
            }

            $html = $parser->saveHTML();
            error_log("HTML após processamento (primeiros 1000 chars): " . substr($html, 0, 1000));

            if (strpos($html, '<img') === false) {
                error_log("AVISO: Tag <img> não encontrada no HTML após saveHTML(), tentando inserir manualmente");
                if (!empty($imgSrc)) {
                    $html = str_replace('<div id="documento"', '<div id="documento"><img id="fundo-imagem" src="' . $imgSrc . '" style="position: absolute; top: 0; left: 0; width: 210mm; height: 297mm;" />', $html);
                }
            }
        } else {
            error_log("ERRO: Div #documento não encontrada no HTML!");
        }

        // Garantir que existe tag <style> no HTML ANTES de tentar substituir
        if (stripos($html, '<style') === false) {
            // Se não tem style, adicionar antes do </head> ou antes do <body>
            if (stripos($html, '</head>') !== false) {
                $html = str_replace('</head>', '<style></style></head>', $html);
            } elseif (stripos($html, '<body') !== false) {
                $html = preg_replace('/<body[^>]*>/i', '<head><style></style></head><body>', $html);
            } else {
                $html = '<head><style></style></head>' . $html;
            }
            error_log("Tag <style> vazia adicionada ao HTML");
        }

        if (!empty($imgSrc)) {
            error_log("imgSrc disponível: " . strlen($imgSrc) . " chars");
            error_log("Tipo imgSrc: " . (strpos($imgSrc, 'data:') === 0 ? 'base64' : 'caminho relativo'));

            $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) use ($imgSrc) {
                $css = $matches[1];

                error_log("CSS encontrado no HTML, substituindo...");

                $cssCompleto = '
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
            #documento {
                position: relative;
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                page-break-after: always;
            }
            #fundo-imagem {
                position: absolute;
                top: 0;
                left: 0;
                width: 210mm;
                height: 297mm;
                display: block;
                z-index: 1;
            }
            #conteudo {
                position: absolute;
                top: 40mm;
                left: 20mm;
                right: 20mm;
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
                z-index: 2;
            }
            ' . $css;

                return '<style>' . $cssCompleto . '</style>';
            }, $html);

            // Se não encontrou style para substituir, adicionar o CSS completo
            if (stripos($html, '@page') === false) {
                error_log("CSS não foi aplicado, adicionando manualmente...");
                if (stripos($html, '</head>') !== false) {
                    $cssCompleto = '<style>
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
            #documento {
                position: relative;
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                page-break-after: always;
            }
            #fundo-imagem {
                position: absolute;
                top: 0;
                left: 0;
                width: 210mm;
                height: 297mm;
                display: block;
                z-index: 1;
            }
            #conteudo {
                position: absolute;
                top: 40mm;
                left: 20mm;
                right: 20mm;
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
                z-index: 2;
            }
            </style>';
                    $html = str_replace('</head>', $cssCompleto . '</head>', $html);
                } else {
                    $html = '<head><style>
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
            #documento {
                position: relative;
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                page-break-after: always;
            }
            #fundo-imagem {
                position: absolute;
                top: 0;
                left: 0;
                width: 210mm;
                height: 297mm;
                display: block;
                z-index: 1;
            }
            #conteudo {
                position: absolute;
                top: 40mm;
                left: 20mm;
                right: 20mm;
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
                z-index: 2;
            }
            </style></head>' . $html;
                }
            }

            error_log("CSS aplicado com sucesso");
        } else {
            error_log("AVISO: imgSrc vazio, usando fallback");
            $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) {
                $css = $matches[1];

                $cssCompleto = '
            @page {
                size: A4;
                margin: 0;
            }
            html, body {
                margin: 0;
                padding: 0;
                width: 210mm;
                height: 297mm;
            }
            body {
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
            }
            #documento {
                position: relative;
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                background: white;
                overflow: visible;
            }
            #fundo-imagem {
                position: absolute;
                top: 0;
                left: 0;
                width: 210mm !important;
                height: 297mm !important;
                z-index: 1;
                display: block !important;
            }
            #conteudo {
                position: absolute;
                top: 40mm;
                left: 20mm;
                right: 20mm;
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
            }
            ' . $css;

                return '<style>' . $cssCompleto . '</style>';
            }, $html);
        }

        $html = preg_replace('/width:\s*calc\(100%\s*-\s*120px\)/i', 'width: 90mm', $html);

        $html = preg_replace_callback('/style\s*=\s*["\']([^"\']*)["\']/i', function($matches) {
            $style = $matches[1];
            $style = preg_replace('/position\s*:\s*fixed/i', 'position: relative', $style);
            $style = preg_replace('/transform\s*:\s*translate[^;]*/i', '', $style);
            $style = preg_replace('/calc\([^)]+\)/i', '90mm', $style);
            return 'style="' . trim($style, '; ') . '"';
        }, $html);

        if (stripos($html, '<html') === false) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        } elseif (stripos($html, '<head') === false && stripos($html, '<html') !== false) {
            $html = preg_replace('/<html[^>]*>/i', '$0<head><meta charset="UTF-8"></head>', $html);
        } elseif (stripos($html, '<body') === false && stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', '</head><body>', $html);
            if (stripos($html, '</html>') === false) {
                $html .= '</body></html>';
            }
        }

        return $html;
    }

    private function preservarEstilosHtml($html)
    {
        $temStyle = strpos($html, '<style') !== false;

        if (!$temStyle && strpos($html, 'style=') === false) {
            $cssBasico = '
            <style>
            @page {
                size: A4;
                margin: 2cm;
            }
            body {
                font-family: "Times New Roman", Times, serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
                margin: 0;
                padding: 2cm;
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
            }
            </style>';

            if (stripos($html, '<head>') !== false) {
                $html = preg_replace('/<head>/i', '<head>' . $cssBasico, $html);
            } elseif (stripos($html, '<body>') !== false) {
                $html = preg_replace('/<body>/i', $cssBasico . '<body>', $html);
            } else {
                $html = $cssBasico . $html;
            }

            return $html;
        }

        if ($temStyle) {
            $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) {
                $css = $matches[1];

                if (strpos($css, '@page') === false) {
                    $css = '@page { size: A4; margin: 2cm; } ' . $css;
                }

                if (strpos($css, 'body') === false || strpos($css, 'body {') === false) {
                    $css = 'body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.6; color: #000; margin: 0; padding: 2cm; } ' . $css;
                }

                return '<style>' . $css . '</style>';
            }, $html);
        }

        return $html;
    }

    private function normalizarEstilosParaPdf($html)
    {
        $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) {
            $css = $matches[1];

            $css = preg_replace('/@media[^{]*\{[^}]*\}/is', '', $css);

            $css = preg_replace('/:\s*!important\s*;/i', ';', $css);

            $css = preg_replace('/background[^:]*:\s*url\([^)]+\)/i', '', $css);

            $css = preg_replace('/position\s*:\s*fixed/i', 'position: relative', $css);
            $css = preg_replace('/position\s*:\s*absolute/i', 'position: relative', $css);

            return '<style>' . $css . '</style>';
        }, $html);

        $html = preg_replace_callback('/style\s*=\s*["\']([^"\']*)["\']/i', function($matches) {
            $style = $matches[1];

            $style = preg_replace('/position\s*:\s*fixed/i', 'position: relative', $style);
            $style = preg_replace('/position\s*:\s*absolute/i', 'position: relative', $style);

            return 'style="' . $style . '"';
        }, $html);

        return $html;
    }

    private function garantirEstruturaHtml($html)
    {
        if (stripos($html, '<html') === false) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        } elseif (stripos($html, '<head') === false && stripos($html, '<html') !== false) {
            $html = preg_replace('/<html[^>]*>/i', '$0<head><meta charset="UTF-8"></head>', $html);
        } elseif (stripos($html, '<body') === false && stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', '</head><body>', $html);
            if (stripos($html, '</html>') === false) {
                $html .= '</body></html>';
            }
        }

        return $html;
    }

    public function salvarParecer($requerimento_id, $html, $templateNome)
    {
        $pastaRequerimento = $this->uploadsPath . $requerimento_id . '/';

        if (!is_dir($pastaRequerimento)) {
            mkdir($pastaRequerimento, 0755, true);
        }

        $nomeArquivo = 'parecer_' . pathinfo($templateNome, PATHINFO_FILENAME) . '_' . date('YmdHis') . '.pdf';
        $caminhoCompleto = $pastaRequerimento . $nomeArquivo;

        $this->converterHtmlParaPdf($html, $caminhoCompleto);

        return [
            'nome' => $nomeArquivo,
            'caminho' => $caminhoCompleto,
            'caminho_relativo' => 'pareceres/' . $requerimento_id . '/' . $nomeArquivo
        ];
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
        $pastaRequerimento = $this->uploadsPath . $requerimento_id . '/';
        $pareceres = [];

        if (is_dir($pastaRequerimento)) {
            $files = scandir($pastaRequerimento);
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'html'])) {
                    $caminhoCompleto = $pastaRequerimento . $file;
                    $parecerData = [
                        'nome' => $file,
                        'arquivo' => $file,
                        'caminho' => $caminhoCompleto,
                        'data' => date('d/m/Y H:i', filemtime($caminhoCompleto)),
                        'tamanho' => filesize($caminhoCompleto),
                        'tipo' => $ext,
                        'documento_id' => null,
                        'assinante' => 'Desconhecido'
                    ];

                    $caminhoJson = $pastaRequerimento . pathinfo($file, PATHINFO_FILENAME) . '.json';
                    if (file_exists($caminhoJson)) {
                        $jsonData = json_decode(file_get_contents($caminhoJson), true);
                        if ($jsonData) {
                            if (isset($jsonData['documento_id'])) {
                                $parecerData['documento_id'] = $jsonData['documento_id'];
                            }
                            if (isset($jsonData['dados_assinatura']['assinante_nome'])) {
                                $parecerData['assinante'] = $jsonData['dados_assinatura']['assinante_nome'];
                            } elseif (isset($jsonData['dados_assinatura']['assinante_nome_completo'])) {
                                $parecerData['assinante'] = $jsonData['dados_assinatura']['assinante_nome_completo'];
                            }
                        }
                    } else {
                        // Buscar documento_id na tabela de assinaturas digitais
                        // Tentar buscar pelo nome do arquivo ou pelo caminho
                        $stmt = $pdo->prepare("
                            SELECT documento_id, assinante_nome
                            FROM assinaturas_digitais
                            WHERE requerimento_id = ?
                            AND (nome_arquivo = ? OR caminho_arquivo LIKE ?)
                            LIMIT 1
                        ");
                        $nomeArquivo = $file;
                        $caminhoPattern = '%/' . $requerimento_id . '/' . $nomeArquivo;
                        $stmt->execute([$requerimento_id, $nomeArquivo, $caminhoPattern]);
                        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($resultado) {
                            $parecerData['documento_id'] = $resultado['documento_id'];
                            $parecerData['assinante'] = $resultado['assinante_nome'];
                        }
                    }

                    $pareceres[] = $parecerData;
                }
            }

            usort($pareceres, function($a, $b) {
                return filemtime($b['caminho']) - filemtime($a['caminho']);
            });
        }

        return $pareceres;
    }

    public function excluirParecer($requerimento_id, $nomeArquivo)
    {
        $caminhoCompleto = $this->uploadsPath . $requerimento_id . '/' . $nomeArquivo;

        if (file_exists($caminhoCompleto)) {
            return unlink($caminhoCompleto);
        }

        return false;
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
}

<?php
require_once '../tipos_alvara.php';
require_once '../includes/config.php';

// Marcação padrão de cada campo de arquivo — zona arrastável + status
// preenchido via JS (index.js) quando o usuário escolhe um arquivo.
function campoArquivo(string $id, string $label, string $name, string $limiteLabel, bool $obrigatorio = true, ?array $downloadLink = null): string
{
    $req = $obrigatorio ? ' required' : '';
    $html = '<div class="file-input-container">';
    $html .= '<label for="' . $id . '">' . $label . '</label>';
    if ($downloadLink) {
        $url = rtrim(BASE_URL, '/') . '/' . $downloadLink['arquivo'];
        $html .= '<a href="' . htmlspecialchars($url) . '" target="_blank" download class="doc-download-link">'
            . '<i class="fas fa-file-pdf"></i> ' . htmlspecialchars($downloadLink['label'])
            . '</a>';
    }
    $html .= '<div class="file-input-zona">';
    $html .= '<input type="file" id="' . $id . '" name="' . $name . '" accept=".pdf"' . $req . '>';
    $html .= '</div>';
    $html .= '<span class="file-input-status"><i class="fas fa-check-circle"></i><span class="nome"></span></span>';
    $html .= '<small class="formato-arquivo">Formato aceito: PDF (Máx. ' . $limiteLabel . ')</small>';
    $html .= '</div>';
    return $html;
}

if (!isset($_POST['tipo']) || !isset($tipos_alvara[$_POST['tipo']])) {
    echo '<div class="mensagem-erro">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Tipo de alvará inválido.</p>
          </div>';
    exit;
}

$tipo = $_POST['tipo'];
$alvara = $tipos_alvara[$tipo];

// Denúncia: exibe upload de evidências no painel direito (mesmo padrão visual dos alvarás)
if ($tipo === 'denuncia') {
    echo '<div class="documentos-lista">
        <h3 style="color:#b91c1c;">DENÚNCIA AMBIENTAL / URBANA</h3>
        <div class="documentos-section">
            <h4 style="color:#b91c1c;">Evidências (opcional)</h4>
            <div class="file-input-container">
                <label>Fotos, vídeos ou documentos da ocorrência</label>
                <div class="file-input-zona">
                    <input type="file" name="evidencias[]" multiple
                        accept="image/jpeg,image/png,image/jpg,application/pdf,video/mp4,video/quicktime">
                </div>
                <span class="file-input-status"><i class="fas fa-check-circle"></i><span class="nome"></span></span>
                <small class="formato-arquivo">Formatos aceitos: JPG, PNG, PDF, MP4 (Máx. 20MB por arquivo)</small>
            </div>
        </div>
        <div class="documentos-section" style="background:#fef2f2;padding:14px;border-radius:8px;margin-top:8px;">
            <ul class="observacoes-lista" style="margin:0;">
                <li>Sua identidade será mantida em sigilo caso opte pela denúncia anônima.</li>
                <li>Guarde o número de protocolo para acompanhar sua denúncia.</li>
                <li>Dúvidas pelo WhatsApp (84) 99668-6413.</li>
            </ul>
        </div>
    </div>';
    exit;
}

$limiteLabel = ($alvara['categoria'] ?? '') === 'ambiental' ? '40MB' : '10MB';

echo '<div class="documentos-lista">';
echo '<h3>' . $alvara['nome'] . '</h3>';

// Tratamento especial para alvará de funcionamento
if ($tipo === 'funcionamento') {
    // Documentos para Pessoa Física
    if (isset($alvara['pessoa_fisica'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos para Pessoa Física</h4>';
        foreach ($alvara['pessoa_fisica'] as $index => $documento) {
            $id = 'doc_pf_' . $tipo . '_' . $index;
            echo campoArquivo($id, $documento, $id, $limiteLabel);
        }
        echo '</div>';
    }

    // Documentos para Pessoa Jurídica
    if (isset($alvara['pessoa_juridica'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos para Pessoa Jurídica</h4>';
        foreach ($alvara['pessoa_juridica'] as $index => $documento) {
            $id = 'doc_pj_' . $tipo . '_' . $index;
            echo campoArquivo($id, $documento, $id, $limiteLabel);
        }
        echo '</div>';
    }
} else {
    // Documentos obrigatórios para outros tipos de alvará
    if (isset($alvara['documentos'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos Obrigatórios</h4>';
        foreach ($alvara['documentos'] as $index => $documento) {
            $id = 'doc_' . $tipo . '_' . $index;
            $dl = $alvara['download_links'][$index] ?? null;
            echo campoArquivo($id, $documento, $id, $limiteLabel, true, $dl);
        }
        echo '</div>';
    }
}

// Documentos opcionais
if (isset($alvara['documentos_opcionais'])) {
    echo '<div class="documentos-section">';
    echo '<h4>Documentos Opcionais</h4>';
    foreach ($alvara['documentos_opcionais'] as $index => $documento) {
        $id = 'doc_opcional_' . $tipo . '_' . $index;
        echo campoArquivo($id, $documento, $id, $limiteLabel, false);
    }
    echo '</div>';
}

// Observações
if (isset($alvara['observacoes'])) {
    echo '<div class="documentos-section">';
    echo '<h4>Observações</h4>';
    echo '<ul class="observacoes-lista">';
    foreach ($alvara['observacoes'] as $observacao) {
        echo '<li>' . $observacao . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// Contato
if (isset($alvara['contato'])) {
    echo '<div class="documentos-section">';
    echo '<h4>Contato</h4>';
    echo '<ul class="observacoes-lista">';
    foreach ($alvara['contato'] as $contato) {
        echo '<li>' . $contato . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

echo '</div>';

// A estilização de .documentos-lista, .file-input-container etc. vive só em
// index.php (css/govbr-theme.css) — não duplica aqui. Um <style> próprio
// nesse fragmento injetado via AJAX já causou bug de cor divergente antes
// (ver css/govbr-theme.css, comentário "Raiz de vários resquícios").

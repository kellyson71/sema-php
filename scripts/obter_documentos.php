<?php
require_once '../tipos_alvara.php';

if (!isset($_POST['tipo']) || !isset($tipos_alvara[$_POST['tipo']])) {
    echo '<div class="mensagem-erro">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Tipo de alvará inválido.</p>
          </div>';
    exit;
}

$tipo = $_POST['tipo'];
$alvara = $tipos_alvara[$tipo];

function renderFileInput($id, $documento, $required = true) {
    $req = $required ? ' required' : '';
    echo '<div class="file-input-container">';
    echo '<label for="' . htmlspecialchars($id) . '">' . htmlspecialchars($documento) . ($required ? ' <span class="obrigatorio">*</span>' : '') . '</label>';
    echo '<div class="file-drop-zone">';
    echo '<input type="file" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($id) . '" accept=".pdf"' . $req . '>';
    echo '<div class="file-drop-content">';
    echo '<i class="fas fa-cloud-upload-alt file-upload-icon"></i>';
    echo '<span class="file-drop-text">Clique ou arraste o PDF aqui</span>';
    echo '<span class="file-drop-hint"><i class="fas fa-file-pdf"></i> PDF &middot; máximo 10 MB</span>';
    echo '</div>';
    echo '<div class="file-selected-info">';
    echo '<div class="file-success-check"><i class="fas fa-check"></i></div>';
    echo '<i class="fas fa-file-pdf file-pdf-icon"></i>';
    echo '<div class="file-sel-details"><span class="file-sel-name"></span><span class="file-sel-meta"><span class="file-sel-size"></span><span class="file-sel-badge">Pronto para envio</span></span></div>';
    echo '<button type="button" class="file-remove-btn" title="Remover arquivo"><i class="fas fa-times"></i></button>';
    echo '</div>';
    echo '<span class="file-error-msg"></span>';
    echo '</div>';
    echo '</div>';
}

echo '<div class="documentos-lista">';
echo '<h3>' . htmlspecialchars($alvara['nome']) . '</h3>';

if ($tipo === 'funcionamento') {
    if (isset($alvara['pessoa_fisica'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos para Pessoa Física</h4>';
        foreach ($alvara['pessoa_fisica'] as $index => $documento) {
            renderFileInput('doc_pf_' . $tipo . '_' . $index, $documento, true);
        }
        echo '</div>';
    }
    if (isset($alvara['pessoa_juridica'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos para Pessoa Jurídica</h4>';
        foreach ($alvara['pessoa_juridica'] as $index => $documento) {
            renderFileInput('doc_pj_' . $tipo . '_' . $index, $documento, true);
        }
        echo '</div>';
    }
} else {
    if (isset($alvara['documentos'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos Obrigatórios</h4>';
        foreach ($alvara['documentos'] as $index => $documento) {
            renderFileInput('doc_' . $tipo . '_' . $index, $documento, true);
        }
        echo '</div>';
    }
}

if (isset($alvara['documentos_opcionais'])) {
    echo '<div class="documentos-section">';
    echo '<h4>Documentos Opcionais</h4>';
    foreach ($alvara['documentos_opcionais'] as $index => $documento) {
        renderFileInput('doc_opcional_' . $tipo . '_' . $index, $documento, false);
    }
    echo '</div>';
}

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
?>
<style>
.documentos-lista {
    padding: 20px;
}

.documentos-lista h3 {
    color: #024287;
    font-size: 1.5rem;
    margin-bottom: 20px;
    text-align: center;
}

.documentos-section {
    margin-bottom: 30px;
}

.documentos-section h4 {
    color: #009640;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 8px;
}

.file-input-container {
    margin-bottom: 16px;
}

.file-input-container > label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-weight: 500;
    font-size: 0.88rem;
}

.file-input-container > label .obrigatorio {
    color: #dc3545;
    margin-left: 2px;
}

/* Drop zone base */
.file-drop-zone {
    position: relative;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
    overflow: hidden;
}

.file-drop-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
    z-index: 2;
}

/* Conteúdo padrão (sem arquivo) */
.file-drop-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 22px 16px;
    gap: 6px;
    pointer-events: none;
}

.file-upload-icon {
    font-size: 2rem;
    color: #94a3b8;
    transition: color 0.2s, transform 0.2s;
}

.file-drop-text {
    font-size: 0.88rem;
    color: #475569;
    font-weight: 500;
}

.file-drop-hint {
    font-size: 0.78rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Hover */
.file-drop-zone:hover,
.file-drop-zone.drag-over {
    border-color: #009640;
    background: #f0fdf4;
    box-shadow: 0 0 0 3px rgba(0,150,64,0.08);
}

.file-drop-zone:hover .file-upload-icon,
.file-drop-zone.drag-over .file-upload-icon {
    color: #009640;
    transform: translateY(-3px);
}

.file-drop-zone.drag-over {
    border-style: solid;
    background: #dcfce7;
}

/* Tem arquivo */
@keyframes filePopIn {
    0%   { opacity: 0; transform: scale(0.96) translateY(4px); }
    60%  { transform: scale(1.01) translateY(-1px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

@keyframes checkPop {
    0%   { transform: scale(0); opacity: 0; }
    60%  { transform: scale(1.25); }
    100% { transform: scale(1); opacity: 1; }
}

.file-drop-zone.has-file {
    border: 2px solid #009640;
    border-left: 5px solid #009640;
    background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%);
    cursor: default;
    box-shadow: 0 2px 10px rgba(0, 150, 64, 0.12);
}

.file-selected-info {
    display: none;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    pointer-events: none;
    animation: filePopIn 0.28s ease both;
}

.file-drop-zone.has-file .file-drop-content {
    display: none;
}

.file-drop-zone.has-file .file-selected-info {
    display: flex;
    pointer-events: auto;
}

/* Badge de check de sucesso */
.file-success-check {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #009640;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
    animation: checkPop 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
    animation-delay: 0.1s;
    opacity: 0;
}

.file-drop-zone.has-file .file-success-check {
    opacity: 1;
}

.file-pdf-icon {
    font-size: 1.5rem;
    color: #dc3545;
    flex-shrink: 0;
}

.file-sel-details {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.file-sel-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-sel-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.file-sel-size {
    font-size: 0.75rem;
    color: #64748b;
}

.file-sel-badge {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #009640;
    background: #dcfce7;
    padding: 2px 7px;
    border-radius: 20px;
    border: 1px solid #bbf7d0;
}

.file-remove-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    font-size: 0.75rem;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    position: relative;
    z-index: 3;
}

.file-remove-btn:hover {
    border-color: #dc3545;
    color: #dc3545;
    background: #fff5f5;
}

/* Estado de erro */
.file-drop-zone.error {
    border: 2px solid #dc3545;
    background: #fff5f5;
}

.file-drop-zone.error .file-upload-icon {
    color: #dc3545;
}

.file-error-msg {
    display: none;
    font-size: 0.78rem;
    color: #dc3545;
    padding: 0 16px 10px;
    font-weight: 500;
}

.file-drop-zone.error .file-error-msg {
    display: block;
}

/* Seções de observações */
.observacoes-lista {
    list-style: none;
    padding: 0;
    margin: 0;
}

.observacoes-lista li {
    margin-bottom: 8px;
    color: #6c757d;
    font-size: 14px;
    padding-left: 20px;
    position: relative;
}

.observacoes-lista li::before {
    content: "•";
    position: absolute;
    left: 0;
    color: #009640;
}

@media (max-width: 768px) {
    .documentos-lista { padding: 15px; }
    .documentos-lista h3 { font-size: 1.3rem; }
    .documentos-section h4 { font-size: 1rem; }
    .file-drop-content { padding: 18px 12px; }
}
</style>
<script>
(function () {
    const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

    function formatSize(bytes) {
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function initZone(zone) {
        const input    = zone.querySelector('input[type="file"]');
        const content  = zone.querySelector('.file-drop-content');
        const info     = zone.querySelector('.file-selected-info');
        const nameEl   = zone.querySelector('.file-sel-name');
        const sizeEl   = zone.querySelector('.file-sel-size');
        const removeBtn= zone.querySelector('.file-remove-btn');
        const errorEl  = zone.querySelector('.file-error-msg');

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = formatSize(file.size);
            errorEl.textContent = '';
            // Reinicia animação removendo e re-adicionando a classe
            zone.classList.remove('has-file', 'error');
            void zone.offsetWidth; // força reflow para reiniciar keyframes
            zone.classList.add('has-file');
        }

        function showError(msg) {
            errorEl.textContent = msg;
            zone.classList.add('error');
            zone.classList.remove('has-file');
            input.value = '';
        }

        function clearFile() {
            input.value = '';
            zone.classList.remove('has-file', 'error');
            errorEl.textContent = '';
        }

        function validate(file) {
            if (!file.name.toLowerCase().endsWith('.pdf')) {
                showError('Apenas arquivos PDF são aceitos.');
                return false;
            }
            if (file.size > MAX_SIZE) {
                showError('Arquivo excede o limite de 10 MB. Reduza o PDF e tente novamente.');
                return false;
            }
            return true;
        }

        input.addEventListener('change', function () {
            if (!this.files || !this.files[0]) return;
            if (validate(this.files[0])) showFile(this.files[0]);
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function (e) {
            if (!zone.contains(e.relatedTarget)) zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (!file) return;
            if (!validate(file)) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showFile(file);
        });

        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            clearFile();
        });
    }

    document.querySelectorAll('.file-drop-zone').forEach(initZone);
})();
</script>

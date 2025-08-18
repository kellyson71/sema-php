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
            echo '<div class="file-input-container">';
            echo '<label for="' . $id . '">' . $documento . '</label>';
            echo '<input type="file" id="' . $id . '" name="' . $id . '" accept=".pdf" required>';
            echo '<small class="formato-arquivo">Formato aceito: PDF (Máx. 10MB)</small>';
            echo '</div>';
        }
        echo '</div>';
    }

    // Documentos para Pessoa Jurídica
    if (isset($alvara['pessoa_juridica'])) {
        echo '<div class="documentos-section">';
        echo '<h4>Documentos para Pessoa Jurídica</h4>';
        foreach ($alvara['pessoa_juridica'] as $index => $documento) {
            $id = 'doc_pj_' . $tipo . '_' . $index;
            echo '<div class="file-input-container">';
            echo '<label for="' . $id . '">' . $documento . '</label>';
            echo '<input type="file" id="' . $id . '" name="' . $id . '" accept=".pdf" required>';
            echo '<small class="formato-arquivo">Formato aceito: PDF (Máx. 10MB)</small>';
            echo '</div>';
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
            echo '<div class="file-input-container">';
            echo '<label for="' . $id . '">' . $documento . '</label>';
            echo '<input type="file" id="' . $id . '" name="' . $id . '" accept=".pdf" required>';
            echo '<small class="formato-arquivo">Formato aceito: PDF (Máx. 10MB)</small>';
            echo '</div>';
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
        echo '<div class="file-input-container">';
        echo '<label for="' . $id . '">' . $documento . '</label>';
        echo '<input type="file" id="' . $id . '" name="' . $id . '" accept=".pdf">';
        echo '<small class="formato-arquivo">Formato aceito: PDF (Máx. 10MB)</small>';
        echo '</div>';
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

// Estilos específicos para a lista de documentos
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
    font-size: 1.2rem;
    margin-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 8px;
}

.file-input-container {
    margin-bottom: 15px;
}

.file-input-container label {
    display: block;
    margin-bottom: 8px;
    color: #495057;
    font-weight: 500;
}

.file-input-container input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 5px;
    background: #f8f9fa;
    cursor: pointer;
    font-size: 14px;
}

.file-input-container input[type="file"]:hover {
    border-color: #009640;
}

.file-input-container input[type="file"]:focus {
    outline: none;
    border-color: #009640;
    box-shadow: 0 0 0 2px rgba(0, 150, 64, 0.1);
}

.observacoes-section,
.contato-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.observacoes-section h4,
.contato-section h4 {
    color: #495057;
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.observacoes-section ul,
.contato-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.observacoes-section li,
.contato-section li {
    margin-bottom: 8px;
    color: #6c757d;
    font-size: 14px;
    padding-left: 20px;
    position: relative;
}

.observacoes-section li:before {
    content: "•";
    position: absolute;
    left: 0;
    color: #009640;
}

.contato-section li:before {
    content: "📞";
    position: absolute;
    left: 0;
}

@media (max-width: 768px) {
    .documentos-lista {
        padding: 15px;
    }

    .documentos-lista h3 {
        font-size: 1.3rem;
    }

    .documentos-section h4 {
        font-size: 1.1rem;
    }
}
</style>

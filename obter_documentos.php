<?php
// Inclui o arquivo com os tipos de alvará
include_once 'tipos_alvara.php';

// Verifica se o tipo de alvará foi enviado
if (isset($_POST['tipo'])) {
    $tipo = $_POST['tipo'];

    // Exibe os documentos para o tipo de alvará selecionado
    echo exibirDocumentosModernos($tipo);
} else {
    echo '<div class="aviso">Selecione um tipo de alvará para ver os documentos necessários.</div>';
}

/**
 * Exibe os documentos necessários para o tipo de alvará com visual moderno
 * 
 * @param string $tipo Tipo de alvará
 * @return string HTML formatado
 */
function exibirDocumentosModernos($tipo)
{
    global $tipos_alvara;

    if (!isset($tipos_alvara[$tipo])) {
        return '<div class="aviso">Tipo de alvará não encontrado.</div>';
    }

    $alvara = $tipos_alvara[$tipo];

    $html = '<div class="documento-container">';

    // Cabeçalho com título
    $html .= '<div class="documento-header">
                <div class="documento-titulo">
                    <i class="fas fa-file-alt"></i>
                    <h3>' . $alvara['nome'] . '</h3>
                </div>
                <p class="documento-subtitulo">Faça o upload dos documentos necessários:</p>
              </div>';

    // Seção de documentos
    if ($tipo == 'funcionamento') {
        // Pessoa Física
        $html .= '<div class="documento-secao">
                    <div class="secao-titulo">
                        <i class="fas fa-user"></i>
                        <h4>PESSOA FÍSICA</h4>
                    </div>
                    <div class="documento-grid">';

        foreach ($alvara['pessoa_fisica'] as $index => $doc) {
            $campo_id = 'pf_doc_' . ($index + 1);
            $doc_title = strip_tags($doc);
            $html .= criarItemUpload($campo_id, $doc, $doc_title);
        }

        $html .= '</div></div>';

        // Pessoa Jurídica
        $html .= '<div class="documento-secao">
                    <div class="secao-titulo">
                        <i class="fas fa-building"></i>
                        <h4>PESSOA JURÍDICA</h4>
                    </div>
                    <div class="documento-grid">';

        foreach ($alvara['pessoa_juridica'] as $index => $doc) {
            $campo_id = 'pj_doc_' . ($index + 1);
            $doc_title = strip_tags($doc);
            $html .= criarItemUpload($campo_id, $doc, $doc_title);
        }

        $html .= '</div></div>';
    } else {
        if (isset($alvara['documentos'])) {
            $html .= '<div class="documento-secao">
                        <div class="documento-grid">';

            foreach ($alvara['documentos'] as $index => $doc) {
                $campo_id = 'doc_' . ($index + 1);
                $doc_title = strip_tags($doc);
                $html .= criarItemUpload($campo_id, $doc, $doc_title);
            }

            $html .= '</div></div>';
        }

        if (isset($alvara['obras_publicas'])) {
            $html .= '<div class="documento-secao">
                        <div class="secao-titulo">
                            <i class="fas fa-hard-hat"></i>
                            <h4>PARA OBRAS PÚBLICAS</h4>
                        </div>
                        <div class="documento-grid">';

            foreach ($alvara['obras_publicas'] as $index => $doc) {
                $campo_id = 'obras_publicas_doc_' . ($index + 1);
                $doc_title = strip_tags($doc);
                $html .= criarItemUpload($campo_id, $doc, $doc_title);
            }

            $html .= '</div></div>';
        }
    }

    // Observações
    if (isset($alvara['observacoes'])) {
        $html .= '<div class="documento-observacoes">';
        $html .= '<div class="observacoes-titulo">
                    <i class="fas fa-exclamation-circle"></i>
                    <h4>Observações Importantes</h4>
                  </div>';
        $html .= '<ul>';

        foreach ($alvara['observacoes'] as $obs) {
            $html .= '<li>' . $obs . '</li>';
        }

        $html .= '</ul></div>';
    }

    $html .= '</div>';

    // Adicionar CSS diretamente no HTML
    $html .= '<style>
        .documento-container {
            background-color: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            color: #024287;
        }
        
        .documento-header {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eef0f4;
        }
        
        .documento-titulo {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .documento-titulo i {
            font-size: 24px;
            color: #009640;
            margin-right: 12px;
        }
        
        .documento-titulo h3 {
            font-family: Viga, sans-serif;
            font-size: 22px;
            color: #024287;
            margin: 0;
        }
        
        .documento-subtitulo {
            font-size: 16px;
            color: #6C757D;
            margin-top: 8px;
        }
        
        .documento-secao {
            margin-bottom: 24px;
        }
        
        .secao-titulo {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .secao-titulo i {
            font-size: 18px;
            color: #009640;
            margin-right: 10px;
        }
        
        .secao-titulo h4 {
            font-size: 18px;
            color: #024287;
            margin: 0;
            font-weight: 600;
        }
        
        .documento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        
        .upload-item {
            border: 1px solid #eef0f4;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .upload-item:hover {
            border-color: #009640;
            box-shadow: 0 4px 8px rgba(0, 150, 64, 0.1);
        }
        
        .upload-item label {
            display: block;
            font-size: 14px;
            margin-bottom: 10px;
            color: #024287;
            font-weight: 500;
        }
        
        .upload-item input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px dashed #CED4DA;
            border-radius: 8px;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-item input[type="file"]:hover {
            border-color: #009640;
            background-color: #f0f9f4;
        }
        
        .documento-observacoes {
            background-color: #f0f9f4;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .observacoes-titulo {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .observacoes-titulo i {
            font-size: 18px;
            color: #009640;
            margin-right: 10px;
        }
        
        .observacoes-titulo h4 {
            font-size: 16px;
            color: #024287;
            margin: 0;
            font-weight: 600;
        }
        
        .documento-observacoes ul {
            margin: 0;
            padding-left: 30px;
            list-style-type: none;
        }
        
        .documento-observacoes li {
            position: relative;
            padding: 6px 0;
            font-size: 14px;
            color: #4a5568;
        }
        
        .documento-observacoes li:before {
            content: "•";
            color: #009640;
            position: absolute;
            left: -15px;
            top: 6px;
        }
        
        @media (max-width: 768px) {
            .documento-grid {
                grid-template-columns: 1fr;
            }
            
            .documento-container {
                padding: 16px;
            }
        }
    </style>';

    return $html;
}

/**
 * Cria um item de upload para documento
 * 
 * @param string $id ID do campo
 * @param string $label Texto do label
 * @param string $title Título para o atributo title
 * @return string HTML do item de upload
 */
function criarItemUpload($id, $label, $title)
{
    return '<div class="upload-item">
                <label for="' . $id . '">' . $label . '</label>
                <input type="file" id="' . $id . '" name="' . $id . '" title="' . $title . '">
            </div>';
}

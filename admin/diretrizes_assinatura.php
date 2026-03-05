<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/conexao.php';

// Apenas usuários logados
verificaLogin();

$titulo_pagina = "Diretrizes de Assinatura Eletrônica";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - SEMA</title>
    <!-- Frameworks & Estilos baseados no sistema -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #333; }
        .legal-header { background: #1c4b36; color: white; padding: 40px 0; text-align: center; }
        .legal-header h1 { font-weight: 700; font-size: 2rem; }
        .legal-container { max-width: 800px; margin: 40px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .legal-section { margin-bottom: 30px; }
        .legal-section h3 { color: #1c4b36; font-size: 1.25rem; font-weight: 600; border-bottom: 2px solid #e9ecef; padding-bottom: 8px; margin-bottom: 15px; }
        .legal-section p, .legal-section li { font-size: 0.95rem; line-height: 1.6; color: #555; }
        .legal-section ul { padding-left: 20px; }
    </style>
</head>
<body>

    <div class="legal-header">
        <div class="container">
            <h1>Termo de Concordância e Diretrizes</h1>
            <p class="mb-0">Uso de Assinatura Eletrônica no Sistema da Secretaria Municipal de Meio Ambiente</p>
        </div>
    </div>

    <div class="container">
        <div class="legal-container">
            <div class="text-center mb-4">
                <img src="../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png" alt="SEMA Logo" height="50">
            </div>

            <div class="legal-section">
                <h3>1. Validade e Amparo Legal</h3>
                <p>
                    A assinatura eletrônica aqui proferida tem validade legal e administrativa para todos os fins de direito, em conformidade com as diretrizes e legislações vigentes sobre processos eletrônicos e validade de documentos digitais do Município de Pau dos Ferros/RN e do Governo Federal. 
                </p>
            </div>

            <div class="legal-section">
                <h3>2. Concordância e Veracidade das Informações</h3>
                <p>Ao utilizar o recurso de assinatura digital neste sistema ("Assinar e Baixar" / "Confirmar Assinatura"), o servidor público ou administrador concorda expressamente que:</p>
                <ul>
                    <li>Leu detidamente todo o conteúdo constante no documento gerado.</li>
                    <li>As informações técnicas, condicionantes e restrições (quando aplicáveis) são verdadeiras e refletem a realidade dos fatos observados e a legislação que rege a matéria.</li>
                    <li>Está no pleno exercício de suas atribuições laborais e possui prerrogativa para a emissão deste tipo de documento ou parecer.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3>3. Caráter Definitivo e Irrevogabilidade</h3>
                <p>
                    A confirmação da assinatura anexada em um Parecer ou Alvará equivale à concordância formal plena sobre o texto da peça. Uma vez anexado o registro digital com os carimbos de data/hora (TimeStamping) e chaves de segurança interna (Hashes criptográficos), a ação é inserida em banco de dados perene para fins de responsabilização legal e integridade documental, não cabendo exclusão retroativa por vias não oficiais.
                </p>
            </div>

            <div class="legal-section">
                <h3>4. Responsabilidade e Guarda de Credenciais</h3>
                <p>
                    O acesso a esta chancela digital é pessoal e intransferível. O servidor é inteiramente responsável por sua senha, acesso autenticado (com ou sem dupla validação) e pela sessão aberta (cookies ativos) na máquina de uso corrente. Assinaturas produzidas através do próprio login em sessão ativa têm autoria técnica inegável imputada ao titular logado.
                </p>
            </div>

            <div class="mt-5 text-center">
                <a href="javascript:window.close();" class="btn btn-outline-secondary px-4 py-2">
                    <i class="fas fa-times me-2"></i> Fechar esta janela
                </a>
            </div>
        </div>
    </div>

</body>
</html>

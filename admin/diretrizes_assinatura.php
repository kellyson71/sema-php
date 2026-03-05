<?php
require_once 'conexao.php';

// Validar login (assumindo que conexao.php ou algo similar importe a função)
if (function_exists('verificaLogin')) {
    verificaLogin();
}

$page_title = 'Diretrizes de Assinatura';
include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-auto">
            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-circle">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        <div class="col">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-shield-alt text-primary me-2"></i>
                Termos e Diretrizes de Assinatura Digital
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-10 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">Informações Legais e Responsabilidades</h6>
                </div>
                <div class="card-body" style="font-size: 1.05rem; line-height: 1.6;">
                    
                    <div class="alert alert-info border-left-info shadow-sm mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Ao utilizar a ferramenta de <strong>Assinatura Digital</strong> oferecida pelo sistema SEMA-PHP, o usuário reconhece e concorda expressamente com os termos estabelecidos neste documento.
                    </div>

                    <h5 class="fw-bold text-dark mt-4 border-bottom pb-2"><i class="fas fa-gavel text-secondary me-2"></i>1. Validade Jurídica e Equivalência</h5>
                    <p class="text-justify mb-4">
                        A assinatura digital aposta nos documentos e pareceres técnicos gerados neste sistema possui presunção de veracidade e equivalência à assinatura manuscrita (física) para todos os efeitos de tramitação processual na Secretaria de Meio Ambiente. 
                        A cada documento gerado, será vinculada uma <strong>Hash de Autenticidade (SHA-256)</strong> e registrado o Endereço IP, data e hora da assinatura no Banco de Dados.
                    </p>

                    <h5 class="fw-bold text-dark mt-4 border-bottom pb-2"><i class="fas fa-user-shield text-secondary me-2"></i>2. Responsabilidade do Assinante</h5>
                    <p class="text-justify mb-4">
                        O usuário (servidor, técnico ou administrador) é <strong>pessoalmente e funcionalmente responsável</strong> pelo conteúdo dos documentos que assina. 
                        É dever do usuário realizar a conferência minuciosa dos dados, condicionantes, valores e aprovações contidas no Parecer Técnico antes de confirmar a assinatura.
                    </p>
                    <ul class="mb-4">
                        <li class="mb-2">A senha de acesso ao sistema é pessoal e intransferível, não devendo ser compartilhada.</li>
                        <li class="mb-2">A alegação de uso indevido da conta não exime o servidor de responsabilização administrativa caso não tenha comunicado tempestivamente eventual vazamento de credenciais.</li>
                    </ul>

                    <h5 class="fw-bold text-dark mt-4 border-bottom pb-2"><i class="fas fa-file-archive text-secondary me-2"></i>3. Irrevogabilidade e Registro</h5>
                    <p class="text-justify mb-4">
                        Uma vez que o botão "Eu Concordo, Assinar" é acionado, a ação <strong>não não pode ser desfeita</strong> da cópia baixada, sendo gerado um arquivo final. Um registro imutável do ato (log de evento) e uma cópia exata do PDF gerado são guardados de forma permanente nos servidores da SEMA. Caso seja detectado erro material após a assinatura, um novo documento retificador deverá ser tramitado de acordo com as normas da secretaria.
                    </p>

                    <h5 class="fw-bold text-dark mt-4 border-bottom pb-2"><i class="fas fa-user-lock text-secondary me-2"></i>4. Auditoria</h5>
                    <p class="text-justify mb-4">
                        O sistema possui módulos de auditoria voltados para investigação de fraudes e eventuais divergências no processo de licenciamento ambiental. Modificações ou tentativas de adulteração de documentos já assinados, seja no arquivo físico (`.pdf`) ou em consultas à base de dados, configuram infração e serão reportadas à Corregedoria.
                    </p>

                </div>
                <div class="card-footer text-center bg-light py-4">
                    <p class="text-muted mb-0 small">Secretaria Municipal de Meio Ambiente <br> Setor de Licenciamento e Fiscalização</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

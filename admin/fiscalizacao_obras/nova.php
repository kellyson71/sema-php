<?php
require_once '../conexao.php';
verificaLogin();
require_once __DIR__ . '/../../includes/fiscalizacao_obras_helpers.php';

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$isAdmin = in_array($nivelAtual, ['admin', 'admin_geral'], true);
if ($nivelAtual !== 'fiscal' && !$isAdmin) {
    http_response_code(403);
    header('Location: ../index.php?error=sem_permissao');
    exit;
}

// Pré-preenchimento ao converter uma denúncia (ver admin/denuncia_converter_handler.php)
$rascunho = [
    'notificado_nome' => $_GET['notificado_nome'] ?? '',
    'notificado_cpf_cnpj' => $_GET['notificado_cpf_cnpj'] ?? '',
    'endereco' => $_GET['endereco'] ?? '',
    'descricao_fato' => $_GET['descricao_fato'] ?? '',
    'denuncia_origem_id' => $_GET['denuncia_origem_id'] ?? '',
];

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Notificação - SEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-8">

        <div class="mb-6">
            <a href="index.php" class="text-amber-600 hover:text-amber-800 flex items-center mb-4 transition-colors w-max">
                <i class="fas fa-arrow-left mr-2"></i> Voltar
            </a>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-file-signature text-amber-600 mr-3"></i>
                Nova Notificação
            </h1>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form action="processar.php" method="POST" enctype="multipart/form-data" id="formNotificacao" class="p-8">
                <input type="hidden" name="acao" value="cadastrar">
                <input type="hidden" name="denuncia_origem_id" value="<?= htmlspecialchars($rascunho['denuncia_origem_id']) ?>">

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-list-check text-gray-400 mr-2"></i> Tipo de documento
                </h3>
                <div class="mb-8">
                    <select name="tipo_documento" id="tipoDocumento" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <?php foreach (FISCALIZACAO_OBRAS_TIPOS as $valor => $label): ?>
                            <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-file-import text-gray-400 mr-2"></i> Origem do documento
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <label class="flex items-start gap-3 p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-amber-50 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50 transition-colors">
                        <input type="radio" name="origem" value="gerado_sistema" required checked class="mt-1 text-amber-600 focus:ring-amber-500" onchange="alternarOrigem()">
                        <span>
                            <span class="block font-semibold text-gray-800">Gerar documento no sistema</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Preenche o modelo oficial e permite assinar digitalmente.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-amber-50 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50 transition-colors">
                        <input type="radio" name="origem" value="upload_pdf" class="mt-1 text-amber-600 focus:ring-amber-500" onchange="alternarOrigem()">
                        <span>
                            <span class="block font-semibold text-gray-800">Anexar PDF já pronto</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Continua fazendo o documento por fora e só sobe o arquivo final.</span>
                        </span>
                    </label>
                </div>

                <div id="campoUploadPdf" class="mb-8 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">PDF da notificação</label>
                    <input type="file" name="pdf_pronto" accept="application/pdf" class="w-full rounded-lg border border-gray-300 px-4 py-3">
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-user-tag text-gray-400 mr-2"></i> Dados do notificado
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notificado(a) <span class="text-red-500">*</span></label>
                        <input type="text" name="notificado_nome" required value="<?= htmlspecialchars($rascunho['notificado_nome']) ?>" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CPF/CNPJ</label>
                        <input type="text" name="notificado_cpf_cnpj" value="<?= htmlspecialchars($rascunho['notificado_cpf_cnpj']) ?>" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Proprietário (se diferente)</label>
                        <input type="text" name="proprietario_nome" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Endereço</label>
                        <input type="text" name="endereco" value="<?= htmlspecialchars($rascunho['endereco']) ?>" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bairro</label>
                        <input type="text" name="bairro" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nº do imóvel</label>
                        <input type="text" name="numero_imovel" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-clipboard-list text-gray-400 mr-2"></i> Descrição do fato
                </h3>
                <div class="mb-8">
                    <textarea name="descricao_fato" rows="4" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500"><?= htmlspecialchars($rascunho['descricao_fato']) ?></textarea>
                </div>

                <div id="blocoArtigos">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                        <i class="fas fa-scale-balanced text-gray-400 mr-2"></i> Fundamentação legal (Lei Municipal nº 2.117/2025)
                    </h3>
                    <div id="listaArtigos" class="mb-8 space-y-2"></div>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-clock text-gray-400 mr-2"></i> Prazo
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data de emissão <span class="text-red-500">*</span></label>
                        <input type="date" name="data_emissao" required value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prazo (dias úteis)</label>
                        <input type="number" name="prazo_dias" min="1" placeholder="ex: 15" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-4">
                    <a href="index.php" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg font-medium transition-colors">Cancelar</a>
                    <button type="submit" class="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i> Registrar Notificação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const ARTIGOS_POR_TIPO = <?= json_encode(FISCALIZACAO_OBRAS_ARTIGOS, JSON_UNESCAPED_UNICODE) ?>;

        function renderArtigos() {
            const tipo = document.getElementById('tipoDocumento').value;
            const artigos = ARTIGOS_POR_TIPO[tipo] || {};
            const container = document.getElementById('listaArtigos');
            container.innerHTML = '';
            const chaves = Object.keys(artigos);
            if (chaves.length === 0) {
                document.getElementById('blocoArtigos').classList.add('hidden');
                return;
            }
            document.getElementById('blocoArtigos').classList.remove('hidden');
            chaves.forEach(codigo => {
                const label = document.createElement('label');
                label.className = 'flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50';
                label.innerHTML = `<input type="checkbox" name="artigos[]" value="${codigo}" class="mt-1"> <span class="text-sm text-gray-700">${artigos[codigo]}</span>`;
                container.appendChild(label);
            });
        }

        function alternarOrigem() {
            const gerarSistema = document.querySelector('input[name="origem"]:checked').value === 'gerado_sistema';
            document.getElementById('campoUploadPdf').classList.toggle('hidden', gerarSistema);
        }

        document.getElementById('tipoDocumento').addEventListener('change', renderArtigos);
        renderArtigos();
        alternarOrigem();
    </script>
</body>
</html>
<?php include '../footer.php'; ?>

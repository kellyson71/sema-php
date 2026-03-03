<?php
require_once 'conexao.php';
verificaLogin();

include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Denúncia - SEMA</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <div class="mb-6">
            <a href="denuncias.php" class="text-blue-600 hover:text-blue-800 flex items-center mb-4 transition-colors w-max">
                <i class="fas fa-arrow-left mr-2"></i> Voltar para Denúncias
            </a>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-file-invoice text-red-600 mr-3"></i>
                Registrar Nova Denúncia
            </h1>
            <p class="text-gray-600 mt-2">Diligencie com os dados e evidências do controle interno.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form action="processar_denuncia.php" method="POST" enctype="multipart/form-data" id="formDenuncia" class="p-8">
                <input type="hidden" name="acao" value="cadastrar">
                
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-user-tag text-gray-400 mr-2"></i> Dados do Infrator
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nome Completo ou Razão Social <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="infrator_nome" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-shadow">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            CPF ou CNPJ
                        </label>
                        <input type="text" name="infrator_cpf_cnpj" id="cpfCnpj" placeholder="Apenas números..." class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-shadow">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Endereço / Local da Infração
                        </label>
                        <input type="text" name="infrator_endereco" class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-shadow">
                    </div>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-clipboard-list text-gray-400 mr-2"></i> Detalhes da Ocorrência
                </h3>
                
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Relato e Observações <span class="text-red-500">*</span>
                    </label>
                    <textarea name="observacoes" rows="5" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-shadow" placeholder="Descreva os fatos detalhamente..."></textarea>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    <i class="fas fa-paperclip text-gray-400 mr-2"></i> Evidências (Anexos)
                </h3>
                
                <div class="mb-8">
                    <div class="flex items-center justify-center w-full">
                        <label for="anexos" class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100/80 transition-colors">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                                <p class="mb-2 text-sm text-gray-500"><span class="font-semibold text-blue-600">Clique para enviar</span> ou arraste arquivos aqui</p>
                                <p class="text-xs text-gray-500">Imagens (JPG, PNG), Vídeos (MP4) ou PDFs (Máx: 20MB)</p>
                            </div>
                            <input id="anexos" name="anexos[]" type="file" multiple class="hidden" accept="image/jpeg,image/png,image/jpg,application/pdf,video/mp4" onchange="mostrarNomesArquivos()" />
                        </label>
                    </div>
                    <div id="listaArquivos" class="mt-4 flex flex-col gap-2"></div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-4">
                    <a href="denuncias.php" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg font-medium transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" id="btnSalvar" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow font-medium transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i> 
                        <span>Registrar Denúncia</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script de Interface -->
    <script>
        document.getElementById('formDenuncia').addEventListener('submit', function() {
            const btn = document.getElementById('btnSalvar');
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.querySelector('span').textContent = 'Processando...';
            btn.querySelector('i').classList.replace('fa-save', 'fa-spinner');
            btn.querySelector('i').classList.add('fa-spin');
        });

        function mostrarNomesArquivos() {
            const input = document.getElementById('anexos');
            const lista = document.getElementById('listaArquivos');
            lista.innerHTML = '';
            
            if (input.files.length === 0) return;

            Array.from(input.files).forEach(file => {
                let icone = 'fa-file';
                let cor = 'text-gray-500';
                
                if (file.type.includes('image')) {
                    icone = 'fa-file-image';
                    cor = 'text-blue-500';
                } else if (file.type.includes('pdf')) {
                    icone = 'fa-file-pdf';
                    cor = 'text-red-500';
                } else if (file.type.includes('video')) {
                    icone = 'fa-file-video';
                    cor = 'text-purple-500';
                }

                const tamanho = (file.size / (1024 * 1024)).toFixed(2);
                
                lista.innerHTML += `
                    <div class="flex items-center p-3 bg-blue-50 rounded-lg border border-blue-100">
                        <i class="fas ${icone} ${cor} text-xl mr-3"></i>
                        <span class="text-sm font-medium text-gray-700 truncate flex-1">${file.name}</span>
                        <span class="text-xs text-gray-500 font-medium ml-3">${tamanho} MB</span>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
<?php include 'footer.php'; ?>

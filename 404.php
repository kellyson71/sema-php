<?php
http_response_code(404);

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$voltarUrl = $referer ?: '/';

// Detectar se veio de uma URL de arquivo (uploads, pareceres, etc.)
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isArquivo = preg_match('#/(uploads|pareceres|pareceres_denuncia)/#', $requestUri);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada - SEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

    <!-- Cabeçalho mínimo -->
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-5xl mx-auto flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color:#009851;">
                <i class="fas fa-leaf text-white text-base"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider leading-none">SEMA</p>
                <p class="text-sm font-bold text-gray-800 leading-tight">Secretaria de Meio Ambiente</p>
            </div>
        </div>
    </header>

    <!-- Conteúdo central -->
    <main class="flex-1 flex items-center justify-center px-4 py-16">
        <div class="max-w-md w-full text-center">

            <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-file-excel text-gray-300 text-4xl"></i>
            </div>

            <p class="text-6xl font-black text-gray-200 mb-2 leading-none">404</p>

            <h1 class="text-xl font-bold text-gray-800 mb-2">
                <?php if ($isArquivo): ?>
                    Arquivo não encontrado
                <?php else: ?>
                    Página não encontrada
                <?php endif; ?>
            </h1>

            <p class="text-sm text-gray-500 mb-8 leading-relaxed">
                <?php if ($isArquivo): ?>
                    O arquivo solicitado não existe ou foi removido do sistema.<br>
                    Entre em contato com a SEMA caso precise de uma cópia.
                <?php else: ?>
                    O endereço que você acessou não existe ou foi removido.<br>
                    Verifique o link e tente novamente.
                <?php endif; ?>
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <?php if ($referer && $referer !== $_SERVER['REQUEST_URI']): ?>
                <a href="<?php echo htmlspecialchars($voltarUrl); ?>"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left text-xs"></i> Voltar
                </a>
                <?php endif; ?>
                <a href="/"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-white rounded-lg text-sm font-medium transition-colors"
                   style="background-color:#009851;">
                    <i class="fas fa-home text-xs"></i> Página inicial
                </a>
                <a href="/consultar/"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <i class="fas fa-search text-xs"></i> Consultar protocolo
                </a>
            </div>

        </div>
    </main>

    <!-- Rodapé mínimo -->
    <footer class="border-t border-gray-200 py-4 text-center">
        <p class="text-xs text-gray-400">
            Prefeitura Municipal de Pau dos Ferros &mdash; SEMA
        </p>
    </footer>

</body>
</html>

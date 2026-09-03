<?php

/**
 * Migração única do protótipo pessoal em Python/Streamlit (notificacoes.db,
 * SQLite) que o fiscal de obras já usava antes deste módulo existir.
 *
 * Uso:
 *   php scripts/migrar_notificacoes_python.php /caminho/para/notificacoes.db <fiscal_id>
 *
 * <fiscal_id> é o id em `administradores` do usuário que vai aparecer como
 * responsável pelos registros migrados (normalmente o próprio fiscal).
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/fiscalizacao_obras_helpers.php';

$pdo = (new Database())->getConnection();

$caminhoSqlite = $argv[1] ?? null;
$fiscalId = isset($argv[2]) ? (int) $argv[2] : null;

if (!$caminhoSqlite || !$fiscalId) {
    fwrite(STDERR, "Uso: php scripts/migrar_notificacoes_python.php <notificacoes.db> <fiscal_id>\n");
    exit(1);
}
if (!is_file($caminhoSqlite)) {
    fwrite(STDERR, "Arquivo não encontrado: $caminhoSqlite\n");
    exit(1);
}

$MAPA_TIPOS = [
    'Notificação Preliminar' => 'notificacao_fiscal_obras',
    'Auto de Infração' => 'outro',
    'Notificação de Entulhos/Descarte de Material' => 'notificacao_descarte_material',
    'Notificação - Acessibilidade em Passeio' => 'notificacao_fiscal_obras',
];

$sqlite = new PDO('sqlite:' . $caminhoSqlite);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$linhas = $sqlite->query('SELECT * FROM notificacoes')->fetchAll(PDO::FETCH_ASSOC);

$migrados = 0;
foreach ($linhas as $linha) {
    $tipo = $MAPA_TIPOS[$linha['tipo_documento']] ?? 'outro';
    $dataEmissao = $linha['data_emissao'] ?: date('Y-m-d');

    $id = inserirNotificacaoObras($pdo, [
        'tipo_documento' => $tipo,
        'origem' => 'upload_pdf',
        'notificado_nome' => $linha['nome_notificado'] ?: 'Não informado',
        'proprietario_nome' => $linha['nome_proprietario'] ?: null,
        'endereco' => $linha['endereco'] ?: null,
        'bairro' => $linha['bairro'] ?: null,
        'descricao_fato' => $linha['observacoes'] ?: ('Migrado do controle anterior (nº ' . $linha['numero_notificacao'] . ').'),
        'prazo_dias' => $linha['prazo_dias'] ?: null,
        'data_emissao' => $dataEmissao,
        'status' => 'pendente',
        'pdf_upload_path' => null, // PDFs do protótipo ficam em notificacoes_pdfs/ — copiar manualmente se necessário
        'fiscal_id' => $fiscalId,
    ]);

    echo "Migrado: {$linha['nome_notificado']} -> notificacoes_obras.id = $id\n";
    $migrados++;
}

echo "Concluído: $migrados registro(s) migrado(s).\n";

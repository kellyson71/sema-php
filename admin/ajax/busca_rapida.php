<?php
/**
 * Busca rápida da topbar — alimenta o preview que aparece enquanto se digita.
 *
 * Mesmo critério da listagem (admin/requerimentos.php:151): protocolo, nome do
 * requerente ou CPF/CNPJ. Como lá, NÃO aplica a trava de setor: quem procura um
 * protocolo precisa achá-lo mesmo que ainda não tenha chegado à sua fila — o
 * resultado marca de qual setor ele é para não dar a impressão de já estar
 * disponível para ação.
 */
require_once '../conexao.php';
verificaLogin();

header('Content-Type: application/json; charset=utf-8');

$termo = trim((string) ($_GET['q'] ?? ''));

// Menos de 2 caracteres devolve vazio em vez de varrer a tabela inteira a cada
// tecla — o front nem chega a chamar, isto é a rede de segurança do servidor.
if (mb_strlen($termo) < 2) {
    echo json_encode(['resultados' => [], 'total' => 0]);
    exit;
}

$curinga = '%' . $termo . '%';
$limite  = 6;

// Mantém a busca tolerante às diferenças mais comuns sem alterar o valor
// exibido ao usuário. O COLLATE do banco já ignora maiúsculas/acentos na
// maioria das instalações; SOUNDEX cobre pequenos erros fonéticos/digitados.
$termoSemPontuacao = preg_replace('/[^[:alnum:]À-ÿ]+/u', ' ', $termo);
$palavras = array_values(array_filter(preg_split('/\s+/u', $termoSemPontuacao)));
$condicoes = ['r.protocolo LIKE ?', 'req.nome LIKE ?', 'req.cpf_cnpj LIKE ?', 'SOUNDEX(req.nome) = SOUNDEX(?)'];
$parametrosBusca = [$curinga, $curinga, $curinga, $termo];

// Cada palavra também é consultada separadamente: "joao silv" encontra
// "João da Silva", mesmo com apenas parte do nome digitada.
foreach ($palavras as $palavra) {
    if (mb_strlen($palavra) < 2) continue;
    $condicoes[] = 'req.nome LIKE ?';
    $parametrosBusca[] = '%' . $palavra . '%';
}
$whereBusca = implode(' OR ', $condicoes);

try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.protocolo, r.status, r.tipo_alvara, r.setor_atual, r.data_envio,
               req.nome AS requerente_nome, req.cpf_cnpj AS requerente_cpf
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE {$whereBusca}
        ORDER BY
            -- Protocolo exato primeiro: quem digita o número inteiro quer aquele.
            CASE WHEN r.protocolo = ? THEN 0
                 WHEN r.protocolo LIKE ? THEN 1
                 ELSE 2 END,
            r.data_envio DESC
        LIMIT " . ($limite + 1) . "
    ");
    $stmt->execute(array_merge($parametrosBusca, [$termo, $termo . '%']));
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['resultados' => [], 'total' => 0, 'erro' => 'Falha na consulta.']);
    exit;
}

// A linha extra só serve para saber se há mais do que cabe na lista.
$temMais = count($linhas) > $limite;
$linhas  = array_slice($linhas, 0, $limite);

$rotuloSetor = [
    'setor1' => 'Triagem',
    'setor2' => 'Fiscalização',
    'setor3' => 'Secretário',
];

$resultados = array_map(static function (array $r) use ($rotuloSetor) {
    $tipo = ucwords(str_replace('_', ' ', mb_strtolower((string) $r['tipo_alvara'])));
    return [
        'id'        => (int) $r['id'],
        'protocolo' => $r['protocolo'],
        'nome'      => $r['requerente_nome'],
        'status'    => $r['status'],
        'tipo'      => $tipo,
        'setor'     => $rotuloSetor[$r['setor_atual']] ?? '',
        'url'       => 'visualizar_requerimento.php?id=' . (int) $r['id'],
    ];
}, $linhas);

echo json_encode([
    'resultados' => $resultados,
    'total'      => count($resultados),
    'tem_mais'   => $temMais,
    'termo'      => $termo,
], JSON_UNESCAPED_UNICODE);

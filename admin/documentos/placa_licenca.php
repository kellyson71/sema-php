<?php
/**
 * Placa de licenciamento ambiental (PDF A4 paisagem) para afixar no empreendimento.
 * Layout da referência trazida na reunião de 25/09/2026. Os dados saem do mesmo
 * ParecerService::preencherDados() usado na LAU, então a validade é a mesma
 * (data de recebimento do processo + 5 anos).
 */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/parecer_service.php';
verificaLogin();

$requerimentoId = (int) ($_GET['requerimento_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT r.*, req.nome AS requerente_nome, req.cpf_cnpj AS requerente_cpf_cnpj,
           p.nome AS proprietario_nome, p.cpf_cnpj AS proprietario_cpf_cnpj
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    LEFT JOIN proprietarios p ON r.proprietario_id = p.id
    WHERE r.id = ?
");
$stmt->execute([$requerimentoId]);
$requerimento = $stmt->fetch(PDO::FETCH_ASSOC);

function placaAviso(string $titulo, string $texto, int $requerimentoId): void
{
    http_response_code(422);
    $voltar = '../visualizar_requerimento.php?id=' . $requerimentoId . '&tab=informacoes';
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Placa de licenciamento</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 16px;color:#1f2d27;">'
        . '<h1 style="font-size:1.2rem;">' . htmlspecialchars($titulo) . '</h1>'
        . '<p>' . $texto . '</p>'
        . '<p><a href="' . htmlspecialchars($voltar) . '">Voltar ao processo</a></p></body></html>';
    exit;
}

if (!$requerimento) {
    placaAviso('Processo não encontrado', 'Confira o link e tente de novo.', $requerimentoId);
}
if (!DocumentoRegras::tipoAmbiental((string) $requerimento['tipo_alvara'])) {
    placaAviso('Placa só para licenciamento ambiental', 'Este tipo de processo não tem placa de licenciamento.', $requerimentoId);
}

$dados = (new ParecerService())->preencherDados($requerimento, null, 'placa_licenca', $pdo);
if (($dados['data_validade_licenca'] ?? 'Não informado') === 'Não informado') {
    placaAviso(
        'Falta a data de recebimento do processo',
        'A validade da placa é a data de recebimento do processo + 5 anos. Preencha a data em '
        . '<strong>Editar dados do processo</strong> e gere a placa de novo.',
        $requerimentoId
    );
}

$licenca = $dados['nome_licenca'];
$telefone = DocumentoRegras::configuracao($pdo, 'placa_disque_denuncia', '(84) 92003-6562');
$ehCnpj = strlen(preg_replace('/\D/', '', (string) $dados['cpf_cnpj_empreendedor'])) > 11;
[$dia, $mes, $ano] = explode('/', $dados['data_validade_licenca']);

$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$logo = dirname(__DIR__, 2) . '/assets/img/logo-prefeitura-sema-horizontal.png';
$verde = '#0b6b3a';
// Uma linha "Rótulo: valor sublinhado" da placa; largura do rótulo em mm (0 = sem rótulo).
$linha = static function (string $rotulo, int $larguraRotulo, string $valor, int $larguraValor = 0) use ($e): string {
    return '<table cellpadding="2" cellspacing="0" style="margin-top:3mm;"><tr>'
        . ($rotulo !== '' ? '<td class="rotulo" style="width:' . $larguraRotulo . 'mm;">' . $e($rotulo) . '</td>' : '')
        . '<td class="valor" style="width:' . ($larguraValor ?: 255 - $larguraRotulo) . 'mm;">' . $e($valor) . '</td>'
        . '</tr></table>';
};
$onda = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 120" preserveAspectRatio="none">'
    . '<path d="M0,40 C250,0 450,90 700,45 C820,25 920,30 1000,55 L1000,120 L0,120 Z" fill="' . $verde . '"/>'
    . '<path d="M0,40 C250,0 450,90 700,45 C820,25 920,30 1000,55" fill="none" stroke="#7cc242" stroke-width="7"/>'
    . '</svg>'
);

$html = '
<style>
    body { font-family: sans-serif; color: ' . $verde . '; }
    .moldura { border: 3mm solid ' . $verde . '; border-radius: 6mm; }
    .rotulo { font-weight: bold; font-size: 20pt; }
    .valor { font-family: serif; font-size: 24pt; color: #111; border-bottom: 0.6mm solid ' . $verde . '; }
</style>
<div class="moldura">
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6mm;">
        <tr>
            <td width="55%" style="padding-left:8mm;"><img src="' . $e($logo) . '" style="height:30mm;"></td>
            <td width="45%" style="text-align:right; padding-right:10mm; border-left:0.8mm solid ' . $verde . ';">
                <div style="font-size:18pt; font-weight:bold;">DISQUE DENÚNCIA:</div>
                <div style="font-size:30pt; font-weight:bold;">' . $e($telefone) . '</div>
            </td>
        </tr>
    </table>
    <div style="text-align:center; font-weight:bold; font-size:17pt; margin-top:4mm;">
        PREFEITURA MUNICIPAL DE PAU DOS FERROS<br>SECRETARIA MUNICIPAL DE MEIO AMBIENTE
    </div>
    <div style="background-color:' . $verde . '; color:#fff; text-align:center; font-weight:bold; font-size:34pt;
                margin:4mm 8mm 0 8mm; padding:2mm 0; border-radius:3mm;">LICENCIAMENTO AMBIENTAL</div>
    <div style="padding:0 10mm; margin-top:4mm;">'
    . $linha('Nome do Empreendedor:', 96, $dados['nome_empreendedor'])
    . $linha($ehCnpj ? 'CNPJ:' : 'CPF:', 24, $dados['cpf_cnpj_empreendedor'])
    . $linha('', 0, $licenca . ' processo nº' . $dados['protocolo'])
    . $linha('Validade:', 38, "$dia / $mes / $ano", 90) . '
    </div>
    <img src="' . $onda . '" style="width:100%; height:30mm; margin-top:8mm;">
</div>';

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4-L',
    'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 8, 'margin_bottom' => 8,
    'tempDir' => sys_get_temp_dir() . '/mpdf',
]);
$mpdf->SetTitle('Placa de licenciamento — ' . $requerimento['protocolo']);
$mpdf->WriteHTML($html);

try {
    $pdo->prepare('INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)')
        ->execute([$_SESSION['admin_id'], $requerimentoId, 'Gerou placa de licenciamento ambiental']);
} catch (Throwable $e2) {
    error_log('[placa_licenca] histórico: ' . $e2->getMessage());
}

$mpdf->Output('placa_licenciamento_' . $requerimento['protocolo'] . '.pdf', \Mpdf\Output\Destination::INLINE);

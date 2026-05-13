<?php
require_once 'conexao.php';
verificaLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: denuncias.php?error=nao_encontrado");
    exit;
}

$stmt = $pdo->prepare("SELECT d.*, a.nome AS responsavel, a.nome_completo AS responsavel_completo, a.cargo AS responsavel_cargo
                       FROM denuncias d
                       LEFT JOIN administradores a ON d.admin_id = a.id
                       WHERE d.id = ?");
$stmt->execute([$id]);
$d = $stmt->fetch();

if (!$d) {
    header("Location: denuncias.php?error=nao_encontrado");
    exit;
}

$stmtHist = $pdo->prepare("SELECT h.*, a.nome AS admin_nome, a.cargo AS admin_cargo
                            FROM denuncia_historico h
                            LEFT JOIN administradores a ON h.admin_id = a.id
                            WHERE h.denuncia_id = ?
                            ORDER BY h.data_registro ASC");
$stmtHist->execute([$id]);
$historico = $stmtHist->fetchAll();

$stmtAnexos = $pdo->prepare("SELECT * FROM denuncia_anexos WHERE denuncia_id = ? ORDER BY data_upload ASC");
$stmtAnexos->execute([$id]);
$anexos = $stmtAnexos->fetchAll();

$numero = str_pad($d['id'], 6, '0', STR_PAD_LEFT);
$dataRegistro = date('d/m/Y \à\s H:i', strtotime($d['data_registro']));
$dataAtual = date('d/m/Y');
$meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
          7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$dataExtenso = date('d', strtotime($d['data_registro'])) . ' de ' . $meses[(int)date('m', strtotime($d['data_registro']))] . ' de ' . date('Y', strtotime($d['data_registro']));

$statusClass = match($d['status']) {
    'Em Análise' => 'status-analise',
    'Concluída'  => 'status-concluida',
    default      => 'status-pendente',
};

$logoSema      = '../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png';
$logoPrefeitura = '../assets/SEMA/PNG/Azul/Logo Prefeitura_SEMA.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Denúncia #<?= $numero ?> — SEMA Pau dos Ferros</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Times New Roman', Times, serif;
        font-size: 12pt;
        color: #111;
        background: #e8e8e8;
    }

    .page {
        width: 210mm;
        min-height: 297mm;
        margin: 20px auto;
        background: #fff;
        padding: 18mm 20mm 22mm;
        box-shadow: 0 4px 32px rgba(0,0,0,.18);
        position: relative;
    }

    /* ── Cabeçalho institucional ── */
    .cabecalho {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 10px;
        border-bottom: 3px solid #1a4a8a;
        margin-bottom: 6px;
    }
    .cabecalho img { height: 62px; object-fit: contain; }
    .cabecalho-centro {
        text-align: center;
        flex: 1;
        padding: 0 14px;
    }
    .cabecalho-centro .gov { font-size: 8.5pt; color: #555; letter-spacing: .05em; text-transform: uppercase; }
    .cabecalho-centro .secretaria { font-size: 11pt; font-weight: bold; color: #1a4a8a; line-height: 1.3; }
    .cabecalho-centro .municipio { font-size: 9pt; color: #444; }

    .faixa-titulo {
        background: #1a4a8a;
        color: #fff;
        text-align: center;
        padding: 7px 0;
        font-size: 12.5pt;
        font-weight: bold;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 18px;
    }

    /* ── Número e status ── */
    .numero-bloco {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .numero-label { font-size: 10pt; color: #555; }
    .numero-valor { font-size: 16pt; font-weight: bold; color: #1a4a8a; }
    .status-badge {
        padding: 4px 14px;
        border-radius: 3px;
        font-size: 9.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .06em;
    }
    .status-pendente  { background: #fff3cd; color: #7a4f00; border: 1px solid #f0c040; }
    .status-analise   { background: #dbeafe; color: #1e3a8a; border: 1px solid #93c5fd; }
    .status-concluida { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

    /* ── Seções ── */
    .secao { margin-bottom: 18px; }
    .secao-titulo {
        font-size: 10pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: #1a4a8a;
        border-bottom: 1.5px solid #1a4a8a;
        padding-bottom: 3px;
        margin-bottom: 10px;
    }

    /* ── Tabela de dados ── */
    .tabela-dados {
        width: 100%;
        border-collapse: collapse;
        font-size: 11pt;
    }
    .tabela-dados tr:nth-child(even) td { background: #f7f9fc; }
    .tabela-dados td {
        padding: 6px 9px;
        border: 1px solid #ccd4e0;
        vertical-align: top;
    }
    .tabela-dados .label {
        font-weight: bold;
        color: #333;
        width: 30%;
        white-space: nowrap;
        background: #eef2f8;
    }

    /* ── Relato ── */
    .relato-box {
        border: 1px solid #ccd4e0;
        background: #f9fafc;
        padding: 12px 14px;
        font-size: 11.5pt;
        line-height: 1.7;
        white-space: pre-wrap;
        text-align: justify;
    }

    /* ── Histórico ── */
    .historico-item {
        padding: 8px 0;
        border-bottom: 1px dashed #dde3ef;
    }
    .historico-item:last-child { border-bottom: none; }
    .hist-acao { font-weight: bold; font-size: 10.5pt; }
    .hist-detalhe { font-size: 10.5pt; color: #333; margin-top: 2px; }
    .hist-meta { font-size: 9pt; color: #666; margin-top: 3px; }

    /* ── Anexos ── */
    .anexo-item { font-size: 10.5pt; padding: 4px 0; border-bottom: 1px dashed #e0e6f0; }
    .anexo-item:last-child { border-bottom: none; }

    /* ── Rodapé ── */
    .rodape {
        margin-top: 32px;
        padding-top: 10px;
        border-top: 2px solid #1a4a8a;
        font-size: 8.5pt;
        color: #555;
        text-align: center;
    }

    /* ── Assinatura ── */
    .assinatura-bloco {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
        gap: 24px;
    }
    .assinatura-item { flex: 1; text-align: center; }
    .assinatura-linha {
        border-top: 1px solid #333;
        margin-bottom: 4px;
        margin-top: 50px;
    }
    .assinatura-nome { font-size: 10pt; font-weight: bold; }
    .assinatura-cargo { font-size: 9pt; color: #555; }

    /* ── Barra de ação (não imprime) ── */
    .barra-acao {
        position: fixed;
        top: 0; left: 0; right: 0;
        background: #1a4a8a;
        color: #fff;
        padding: 10px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 999;
        font-family: Arial, sans-serif;
        font-size: 13px;
    }
    .barra-acao a { color: #a8c8ff; text-decoration: none; font-size: 12px; }
    .barra-acao a:hover { text-decoration: underline; }
    .btn-imprimir {
        background: #fff;
        color: #1a4a8a;
        border: none;
        padding: 7px 20px;
        border-radius: 5px;
        font-weight: bold;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .btn-imprimir:hover { background: #dbeafe; }

    @media print {
        body { background: #fff; }
        .barra-acao { display: none; }
        .page { margin: 0; box-shadow: none; padding: 14mm 18mm 18mm; }
    }
</style>
</head>
<body>

<!-- Barra de ação (não aparece no print) -->
<div class="barra-acao">
    <div>
        <a href="visualizar_denuncia.php?id=<?= $d['id'] ?>">← Voltar à denúncia</a>
    </div>
    <div style="font-weight:bold; letter-spacing:.04em;">
        SEMA — Exportação de Denúncia #<?= $numero ?>
    </div>
    <button class="btn-imprimir" onclick="window.print()">
        🖨️ Imprimir / Salvar PDF
    </button>
</div>

<div style="height:46px"></div>

<div class="page">

    <!-- Cabeçalho -->
    <div class="cabecalho">
        <img src="<?= htmlspecialchars($logoSema) ?>" alt="Logo SEMA">
        <div class="cabecalho-centro">
            <div class="gov">Prefeitura Municipal de Pau dos Ferros – RN</div>
            <div class="secretaria">Secretaria Municipal de Meio Ambiente</div>
            <div class="municipio">SEMA — Fiscalização e Controle Ambiental</div>
        </div>
        <img src="<?= htmlspecialchars($logoPrefeitura) ?>" alt="Logo Prefeitura SEMA">
    </div>

    <div class="faixa-titulo">Ficha de Denúncia Ambiental</div>

    <!-- Número e status -->
    <div class="numero-bloco">
        <div>
            <div class="numero-label">Número do Registro</div>
            <div class="numero-valor">#<?= $numero ?></div>
        </div>
        <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($d['status']) ?></span>
    </div>

    <!-- Identificação do Infrator -->
    <div class="secao">
        <div class="secao-titulo">Identificação do Infrator / Autuado</div>
        <table class="tabela-dados">
            <tr>
                <td class="label">Nome / Razão Social</td>
                <td><?= htmlspecialchars($d['infrator_nome']) ?></td>
            </tr>
            <tr>
                <td class="label">CPF / CNPJ</td>
                <td><?= htmlspecialchars($d['infrator_cpf_cnpj'] ?: 'Não informado') ?></td>
            </tr>
            <tr>
                <td class="label">Endereço da Ocorrência</td>
                <td><?= htmlspecialchars($d['infrator_endereco'] ?: 'Não informado') ?></td>
            </tr>
        </table>
    </div>

    <!-- Dados do Registro -->
    <div class="secao">
        <div class="secao-titulo">Dados do Registro</div>
        <table class="tabela-dados">
            <tr>
                <td class="label">Data de Registro</td>
                <td><?= $dataRegistro ?></td>
            </tr>
            <tr>
                <td class="label">Registrado por</td>
                <td><?= htmlspecialchars($d['responsavel_completo'] ?: $d['responsavel'] ?: 'Sistema') ?>
                    <?php if (!empty($d['responsavel_cargo'])): ?>
                        — <em><?= htmlspecialchars($d['responsavel_cargo']) ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="label">Status Atual</td>
                <td><strong><?= htmlspecialchars($d['status']) ?></strong></td>
            </tr>
            <?php if (count($anexos) > 0): ?>
            <tr>
                <td class="label">Anexos</td>
                <td><?= count($anexos) ?> arquivo(s) vinculado(s)</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Relato / Observações -->
    <div class="secao">
        <div class="secao-titulo">Relato da Ocorrência</div>
        <div class="relato-box"><?= htmlspecialchars($d['observacoes']) ?></div>
    </div>

    <!-- Histórico de Ações -->
    <?php if (count($historico) > 0): ?>
    <div class="secao">
        <div class="secao-titulo">Histórico de Ações (<?= count($historico) ?> registro<?= count($historico) > 1 ? 's' : '' ?>)</div>
        <?php foreach ($historico as $h): ?>
        <div class="historico-item">
            <div class="hist-acao"><?= htmlspecialchars($h['acao']) ?></div>
            <?php if (!empty($h['detalhes']) && $h['detalhes'] !== 'Nenhum detalhe adicional.'): ?>
            <div class="hist-detalhe"><?= htmlspecialchars($h['detalhes']) ?></div>
            <?php endif; ?>
            <div class="hist-meta">
                Por: <strong><?= htmlspecialchars($h['admin_nome'] ?: 'Sistema') ?></strong>
                <?php if (!empty($h['admin_cargo'])): ?>(<?= htmlspecialchars($h['admin_cargo']) ?>)<?php endif; ?>
                &nbsp;·&nbsp; <?= date('d/m/Y \à\s H:i', strtotime($h['data_registro'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Anexos -->
    <?php if (count($anexos) > 0): ?>
    <div class="secao">
        <div class="secao-titulo">Documentos Anexados</div>
        <?php foreach ($anexos as $i => $anexo): ?>
        <div class="anexo-item">
            <?= $i + 1 ?>. <?= htmlspecialchars($anexo['nome_arquivo'] ?? $anexo['caminho_arquivo'] ?? 'Arquivo') ?>
            <span style="color:#666; font-size:9.5pt;">(<?= strtoupper($anexo['tipo_arquivo'] ?? '?') ?>)</span>
            — enviado em <?= date('d/m/Y', strtotime($anexo['data_upload'])) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Assinatura -->
    <div class="assinatura-bloco">
        <div class="assinatura-item">
            <div class="assinatura-linha"></div>
            <div class="assinatura-nome"><?= htmlspecialchars($d['responsavel_completo'] ?: $d['responsavel'] ?: '_________________________') ?></div>
            <div class="assinatura-cargo"><?= htmlspecialchars($d['responsavel_cargo'] ?: 'Agente Responsável') ?></div>
            <div class="assinatura-cargo">SEMA – Pau dos Ferros/RN</div>
        </div>
        <div class="assinatura-item">
            <div class="assinatura-linha"></div>
            <div class="assinatura-nome">_________________________</div>
            <div class="assinatura-cargo">Infrator / Representante Legal</div>
            <div class="assinatura-cargo">CPF/CNPJ: <?= htmlspecialchars($d['infrator_cpf_cnpj'] ?: '___________________') ?></div>
        </div>
    </div>

    <div style="margin-top: 28px; text-align: center; font-size: 10.5pt; color: #444;">
        Pau dos Ferros – RN, <?= $dataExtenso ?>
    </div>

    <!-- Rodapé -->
    <div class="rodape">
        Secretaria Municipal de Meio Ambiente – SEMA &nbsp;|&nbsp; Prefeitura de Pau dos Ferros/RN<br>
        Documento gerado eletronicamente em <?= $dataAtual ?> pelo Sistema de Protocolo Eletrônico de Alvará Ambiental.<br>
        Denúncia #<?= $numero ?> &nbsp;·&nbsp; Uso interno — Canal de Fiscalização Ambiental
    </div>

</div>

</body>
</html>

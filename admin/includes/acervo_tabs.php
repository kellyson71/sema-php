<?php
/**
 * Abas do Acervo — compartilhadas por documentos_assinados.php,
 * requerimentos_arquivados.php e logs_email.php.
 *
 * Estas três telas eram um submenu que precisava abrir na barra lateral antes
 * de escolher o destino. Agora a barra tem só "Documentos" e a navegação entre
 * elas acontece aqui, com um clique. Cada tela continua sendo sua própria
 * página — nada foi fundido; o que se compartilha é a navegação.
 *
 * Uso: incluir logo depois de header.php.
 */
$acervoAbas = [
    ['arquivo' => 'documentos_assinados.php',     'rotulo' => 'Documentos assinados', 'icone' => 'fa-file-signature'],
    ['arquivo' => 'requerimentos_arquivados.php', 'rotulo' => 'Arquivados',           'icone' => 'fa-box-archive'],
    ['arquivo' => 'logs_email.php',               'rotulo' => 'Histórico de envios',  'icone' => 'fa-envelope-open-text'],
];
$acervoAtual = basename($_SERVER['PHP_SELF']);
$acervoBase  = $adminBase ?? '';
?>
<nav class="acervo-abas" aria-label="Seções do acervo">
    <?php foreach ($acervoAbas as $aba): ?>
        <a href="<?= $acervoBase . $aba['arquivo'] ?>"
           class="acervo-aba <?= $acervoAtual === $aba['arquivo'] ? 'ativa' : '' ?>"
           <?= $acervoAtual === $aba['arquivo'] ? 'aria-current="page"' : '' ?>>
            <i class="fas <?= $aba['icone'] ?>"></i><?= $aba['rotulo'] ?>
        </a>
    <?php endforeach; ?>
</nav>

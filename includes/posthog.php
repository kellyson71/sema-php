<?php
/**
 * Snippet de analytics (PostHog). Tem dois modos, e a diferença entre eles é de segurança.
 *
 * PÚBLICO (cidadão): autocapture ligado. Ninguém é identificado — o distinct_id é anônimo,
 * de dispositivo, e person_profiles: 'identified_only' impede a criação de pessoa.
 *
 * ADMIN (equipe da SEMA): autocapture DESLIGADO. O autocapture manda o texto do elemento
 * clicado, e as telas do painel exibem nome e CPF de cidadão — ligá-lo aqui vazaria dado
 * pessoal em toda linha de tabela clicada. Em troca, o admin é identificado (admin_<id>),
 * o que dá o rastro de "quem fez o quê" e liga o erro do servidor à sessão do browser.
 *
 * A chave vem do ambiente (POSTHOG_KEY), via SetEnvIf no .htaccess, que só casa com os
 * domínios de produção. Sem chave, nada é renderizado — analytics desligado.
 *
 * Não pode ter efeitos colaterais: roda dentro do <head>, e páginas como privacidade e
 * acessibilidade já emitiram HTML aqui. Por isso não carrega o config.php (que dá session_start).
 */

$posthogKey = $_SERVER['POSTHOG_KEY'] ?? getenv('POSTHOG_KEY') ?: '';
if ($posthogKey === '' && defined('POSTHOG_KEY')) {
    $posthogKey = POSTHOG_KEY;
}

if (trim((string) $posthogKey) === '') {
    return;
}

$posthogHost = $_SERVER['POSTHOG_HOST'] ?? getenv('POSTHOG_HOST') ?: 'https://us.i.posthog.com';

// Área do painel: decidida pelo caminho, não pela sessão. Assim um admin logado que abre
// o site público continua contando como visita pública normal (com autocapture).
$posthogIsAdmin = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;

$posthogAdmin = null;
if ($posthogIsAdmin && !empty($_SESSION['admin_id'])) {
    $posthogAdmin = [
        // O ID continua sendo admin_<id>: precisa ser estável e único (nome repete e muda).
        // Quem vira rótulo na tela é a propriedade 'name', que o PostHog usa para exibir a pessoa.
        'distinct_id' => 'admin_' . $_SESSION['admin_id'],
        // Só dado funcional. CPF e e-mail do admin ficam de fora de propósito.
        'props' => [
            'name'  => $_SESSION['admin_nome'] ?? null,
            'nome'  => $_SESSION['admin_nome'] ?? null,
            'nivel' => $_SESSION['admin_nivel'] ?? null,
            'cargo' => $_SESSION['admin_cargo'] ?? null,
        ],
    ];
}
?>
<script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug getPageViewId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);

    posthog.init(<?= json_encode($posthogKey) ?>, {
        api_host: <?= json_encode($posthogHost, JSON_UNESCAPED_SLASHES) ?>,
        defaults: '2025-05-24',
        // O site é todo formulário com nome, CPF, e-mail e telefone: replay grava a tela
        // (inclusive o que a pessoa digita) e perfil de anônimo criaria pessoa por visitante.
        disable_session_recording: true,
        person_profiles: 'identified_only',
<?php if ($posthogIsAdmin): ?>
        // Painel: o texto dos elementos contém nome e CPF de cidadão. Sem autocapture.
        autocapture: false,
        capture_pageview: true,
<?php endif; ?>
        // Protocolo e CPF viajam na query string. Nunca viram propriedade de evento.
        sanitize_properties: function (properties) {
            ['$current_url', '$referrer', '$pathname'].forEach(function (k) {
                if (typeof properties[k] === 'string') {
                    properties[k] = properties[k].split('?')[0];
                }
            });
            return properties;
        }
    });
<?php if ($posthogAdmin): ?>

    // Identifica a pessoa da equipe. Faz o browser falar o mesmo distinct_id que o SDK PHP
    // usa nos erros — é isso que liga "quem estava na sessão" ao "quem se deparou com o erro".
    posthog.identify(<?= json_encode($posthogAdmin['distinct_id']) ?>, <?= json_encode($posthogAdmin['props'], JSON_UNESCAPED_UNICODE) ?>);
<?php endif; ?>
</script>

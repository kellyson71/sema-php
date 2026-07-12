<?php
/**
 * Snippet de analytics (PostHog) para as páginas públicas.
 *
 * A chave vem do ambiente (POSTHOG_KEY), definida por SetEnvIf no .htaccess da raiz,
 * que só casa com o domínio de produção. Em localhost e em homologação a variável não
 * existe, então nada é renderizado — analytics desligado sem editar código.
 *
 * Este arquivo é incluído dentro do <head> e não pode ter efeitos colaterais:
 * várias páginas (privacidade, acessibilidade, suporte) já emitiram HTML neste ponto,
 * então nada de session_start()/headers aqui — por isso ele não carrega o config.php.
 *
 * A Project API key é pública (write-only, vai no HTML). O segredo é o host, não ela.
 */

$posthogKey = $_SERVER['POSTHOG_KEY'] ?? getenv('POSTHOG_KEY') ?: '';
if ($posthogKey === '' && defined('POSTHOG_KEY')) {
    $posthogKey = POSTHOG_KEY;
}

if (trim((string) $posthogKey) === '') {
    return;
}

$posthogHost = $_SERVER['POSTHOG_HOST'] ?? getenv('POSTHOG_HOST') ?: 'https://us.i.posthog.com';
?>
<script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug getPageViewId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);

    posthog.init(<?= json_encode($posthogKey) ?>, {
        api_host: <?= json_encode($posthogHost, JSON_UNESCAPED_SLASHES) ?>,
        defaults: '2025-05-24',
        // O site inteiro é formulário com nome, CPF, e-mail e telefone do cidadão:
        // replay grava a tela (inclusive o que a pessoa digita) e perfil de anônimo
        // criaria pessoa para cada visitante. Ambos ficam fora.
        disable_session_recording: true,
        person_profiles: 'identified_only'
    });
</script>

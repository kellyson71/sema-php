<?php

function publicFormEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function emailRequerenteValido(string $email): bool
{
    $email = trim($email);
    return $email !== ''
        && strlen($email) <= 191
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function emailsRequerenteCoincidem(string $email, string $confirmacao): bool
{
    $email = trim($email);
    $confirmacao = trim($confirmacao);
    return $confirmacao !== '' && strcasecmp($email, $confirmacao) === 0;
}

function renderLocationComposer(string $name, string $label, bool $required = true, string $value = ''): string
{
    $nameEsc = publicFormEscape($name);
    $labelEsc = publicFormEscape($label);
    $valueEsc = publicFormEscape($value);
    $optionalAttr = $required ? '' : ' data-location-optional="true"';
    $requiredAttr = $required ? ' required data-required="true"' : '';
    $requiredMark = $required ? ' <span style="color:#f87171">*</span>' : '';
    $ruaPlaceholder = $required ? 'Rua / logradouro *' : 'Rua / logradouro';
    $bairroPlaceholder = $required ? 'Bairro *' : 'Bairro';

    return <<<HTML
<div class="public-location-composer" data-location-composer{$optionalAttr}>
    <div class="form-section-label" style="margin-top:0;">{$labelEsc}{$requiredMark}</div>
    <input type="hidden" name="{$nameEsc}" value="{$valueEsc}" data-location-output>
    <div class="form-grid-2">
        <input{$requiredAttr} data-location-field="rua" placeholder="{$ruaPlaceholder}" autocomplete="street-address">
        <input{$requiredAttr} data-location-field="bairro" placeholder="{$bairroPlaceholder}">
        <input data-location-field="lote" placeholder="Lote">
        <input data-location-field="quadra" placeholder="Quadra">
        <input data-location-field="numero" placeholder="Número (deixe vazio para SN)" inputmode="numeric"
            maxlength="10" title="Informe somente o número, como 123 ou 123A; deixe vazio para SN.">
    </div>
    <div class="public-location-preview">
        <span>Como ficará:</span>
        <strong data-location-preview>PAU DOS FERROS/RN.</strong>
    </div>
</div>
HTML;
}

function enderecoPauDosFerrosValido(string $endereco): bool
{
    $endereco = trim(preg_replace('/\s+/', ' ', $endereco));

    if ($endereco === '') {
        return false;
    }

    if (!preg_match('/PAU\s+DOS\s+FERROS\/RN\.?$/iu', $endereco)) {
        return false;
    }

    if (!preg_match('/^.+,\s*(?:\([^)]*\)\s*)?(SN|\d+[A-Z]?|S\/N),\s*BAIRRO\s+.+,\s*PAU\s+DOS\s+FERROS\/RN\.?$/iu', $endereco)) {
        return false;
    }

    return true;
}

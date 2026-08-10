<?php
/**
 * Ui — CSS e helpers de apresentacao compartilhados pelas views do modulo.
 *
 * As duas views mantinham copias divergentes do mesmo CSS (etiquetas, selects,
 * tabelas), e cada uma declarava sua propria funcao de badge no escopo global —
 * o que causa "Cannot redeclare" se a view for renderizada duas vezes no mesmo
 * request. Tudo foi centralizado aqui.
 *
 * Tema: o Zabbix 7.0 entrega os temas (Blue, Dark, HC light, HC dark) como
 * folhas de estilo separadas, sem classe no <body> nem variaveis CSS estaveis
 * para modulos. A deteccao e feita medindo a luminancia real do background do
 * <body> e aplicando a classe .is-dark no wrapper — funciona em qualquer tema,
 * inclusive customizados.
 *
 * @author Rafael M. A. Leao Ereno
 */

namespace Modules\UserMigrate;

class Ui {

    /** Bloco <style> completo do modulo. */
    public static function styles(): string {
        return <<<'CSS'
<style>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.zbx-migrate-wrap,
.zbx-report-wrap {
    max-width: 1080px;
    margin: 24px auto;
    padding: 0 16px;
    --zm-surface: var(--color-bg-primary, #fff);
    --zm-surface-alt: var(--color-bg-secondary, #f8f9fa);
    --zm-border: var(--color-border, #dcdcdc);
    --zm-text: var(--color-text-primary, #2b2b2b);
    --zm-muted: var(--color-text-secondary, #6b6b6b);
    --zm-link: var(--color-link, #1a7dc4);
    --zm-ok: #1a7f4b;
    --zm-ok-bg: rgba(26, 127, 75, .10);
    --zm-warn: #8a6100;
    --zm-warn-bg: rgba(255, 193, 7, .14);
    --zm-err: #b02a37;
    --zm-err-bg: rgba(176, 42, 55, .10);
    --zm-info: #1a6fb5;
    --zm-info-bg: rgba(26, 111, 181, .10);
    --zm-accent: #7a3fa8;
    --zm-accent-bg: rgba(122, 63, 168, .12);
    color: var(--zm-text);
}

/* Tema escuro: apenas os tons de texto precisam clarear — os fundos ja sao
   rgba() translucido e se adaptam sozinhos. */
.zbx-migrate-wrap.is-dark,
.zbx-report-wrap.is-dark {
    --zm-surface: rgba(255, 255, 255, .04);
    --zm-surface-alt: rgba(255, 255, 255, .07);
    --zm-border: rgba(255, 255, 255, .16);
    --zm-text: #e6e6e6;
    --zm-muted: #a8a8a8;
    --zm-link: #6fb2e8;
    --zm-ok: #63d19b;
    --zm-warn: #f0c05a;
    --zm-err: #f08b95;
    --zm-info: #78b8ef;
    --zm-accent: #c79ae8;
}

.zbx-migrate-title,
.zbx-report-title { font-size: 20px; font-weight: 600; margin: 0 0 6px; color: var(--zm-text); }
.zbx-migrate-subtitle,
.zbx-report-subtitle { color: var(--zm-muted); margin: 0 0 24px; font-size: 13px; line-height: 1.5; }

.zbx-migrate-form,
.zbx-report-form {
    background: var(--zm-surface);
    border: 1px solid var(--zm-border);
    border-radius: 6px;
    padding: 24px;
    margin-bottom: 24px;
}

/* ── Selecao origem/destino ──────────────────────────────────────────────── */
.zbx-migrate-selectors { display: grid; grid-template-columns: 1fr 44px 1fr; gap: 16px; align-items: start; }
@media (max-width: 760px) {
    .zbx-migrate-selectors { grid-template-columns: 1fr; }
    .zbx-migrate-arrow { transform: rotate(90deg); justify-self: center; padding: 0; }
}

.zbx-migrate-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.zbx-migrate-field > label { font-weight: 600; font-size: 13px; color: var(--zm-text); }
.zbx-migrate-field small { font-size: 11px; color: var(--zm-muted); }

.zbx-migrate-arrow {
    font-size: 26px;
    color: var(--zm-muted);
    user-select: none;
    align-self: center;
    text-align: center;
    padding-top: 26px;
}

/* Correcao do texto cortado: a altura do <select> era herdada do tema do Zabbix
   e ficava menor que o box do texto, cortando as descidas (g, ç, y). box-sizing
   + min-height + line-height explicitos resolvem em todos os temas. */
/* input.zbx-migrate-search (0,1,1) e nao apenas a classe: o Zabbix estiliza
   input[type="text"], que tem a mesma especificidade e venceria uma classe
   simples. O <select> nao sofre disso porque a regra nativa e so `select`. */
.zbx-migrate-select,
input.zbx-migrate-search {
    box-sizing: border-box;
    display: block;
    width: 100%;
    max-width: 100%;
    min-height: 34px;
    height: auto;
    padding: 6px 10px;
    line-height: 20px;
    font-size: 13px;
    font-family: inherit;
    color: var(--zm-text);
    background-color: var(--zm-surface);
    border: 1px solid var(--zm-border);
    border-radius: 4px;
    text-overflow: ellipsis;
}

.zbx-migrate-select { padding-right: 26px; }
.zbx-migrate-select:focus,
input.zbx-migrate-search:focus { outline: 2px solid var(--zm-link); outline-offset: -1px; }
.zbx-migrate-select option { padding: 3px 6px; line-height: 1.5; }

.zbx-migrate-search-wrap { position: relative; }

/* Abre espaco para a lupa sobreposta. !important porque o tema do Zabbix pode
   declarar o padding do input com especificidade igual ou maior, e o texto
   voltaria a nascer por baixo do icone. */
.zbx-migrate-search-wrap input.zbx-migrate-search { padding-left: 30px !important; }

.zbx-migrate-search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    font-size: 12px; line-height: 1; color: var(--zm-muted); pointer-events: none;
}
.zbx-migrate-count-hint { font-size: 11px; color: var(--zm-muted); min-height: 14px; }

/* ── Etiquetas de autenticacao ───────────────────────────────────────────── */
.zbx-migrate-badge-row {
    display: grid; grid-template-columns: 1fr 44px 1fr;
    gap: 16px; align-items: center; margin-top: 14px; min-height: 26px;
}
@media (max-width: 760px) { .zbx-migrate-badge-row { grid-template-columns: 1fr; } }

.zbx-migrate-badge-wrap {
    font-size: 12px; color: var(--zm-muted);
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap; min-width: 0;
}
.zbx-migrate-badge-wrap > .zbx-migrate-badge-label { white-space: nowrap; }
.zbx-migrate-arrow-small { font-size: 16px; color: var(--zm-muted); user-select: none; text-align: center; }

.zbx-migrate-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; padding: 3px 9px; border-radius: 11px;
    font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    border: 1px solid currentColor; white-space: nowrap;
}
.zbx-migrate-badge em { font-style: normal; font-weight: 500; opacity: .75; text-transform: none; letter-spacing: 0; }

.zbx-badge-local   { color: var(--zm-warn); background: var(--zm-warn-bg); }
.zbx-badge-ldap    { color: var(--zm-ok);   background: var(--zm-ok-bg); }
.zbx-badge-saml    { color: var(--zm-accent); background: var(--zm-accent-bg); }
.zbx-badge-unknown { color: var(--zm-muted); background: transparent; }

.zbx-migrate-chip {
    display: inline-flex; align-items: center;
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: var(--zm-surface-alt); border: 1px solid var(--zm-border);
    color: var(--zm-muted); max-width: 200px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.zbx-chip-jit     { color: var(--zm-info); background: var(--zm-info-bg); border-color: currentColor; }
.zbx-chip-blocked { color: var(--zm-err);  background: var(--zm-err-bg);  border-color: currentColor; }

/* ── Acoes ───────────────────────────────────────────────────────────────── */
.zbx-migrate-actions { margin-top: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.zbx-migrate-hint { font-size: 12px; color: var(--zm-muted); }
.zbx-migrate-wrap button[disabled] { opacity: .55; cursor: not-allowed; }

/* ── Preview ─────────────────────────────────────────────────────────────── */
.zbx-migrate-preview {
    background: var(--zm-surface); border: 1px solid var(--zm-border);
    border-radius: 6px; overflow: hidden;
}
.zbx-migrate-preview-header {
    background: var(--zm-surface-alt); border-bottom: 1px solid var(--zm-border);
    padding: 14px 20px; font-size: 14px; word-break: break-word;
}
.zbx-migrate-section { border-bottom: 1px solid var(--zm-border); padding: 14px 20px; }
.zbx-migrate-section:last-child { border-bottom: none; }
.zbx-migrate-section-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; cursor: pointer; user-select: none;
}
.zbx-migrate-section-header:focus-visible { outline: 2px solid var(--zm-link); outline-offset: 2px; }
.zbx-migrate-section-title { font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.zbx-migrate-count {
    background: var(--zm-info-bg); color: var(--zm-info); border: 1px solid currentColor;
    font-size: 11px; padding: 1px 8px; border-radius: 10px; font-weight: 700;
}
.zbx-migrate-section-desc { font-size: 12px; color: var(--zm-muted); margin-top: 4px; }
.zbx-migrate-items { display: none; margin-top: 8px; }
.zbx-migrate-items.open { display: block; }
.zbx-migrate-items ul { margin: 0; padding: 0 0 0 18px; list-style: disc; }
.zbx-migrate-items li { font-size: 12px; color: var(--zm-muted); padding: 2px 0; word-break: break-word; }
.zbx-migrate-toggle { font-size: 11px; color: var(--zm-link); cursor: pointer; white-space: nowrap; }
.zbx-migrate-confirm-bar {
    background: var(--zm-surface-alt); border-top: 1px solid var(--zm-border);
    padding: 14px 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
#zbx-migrate-total { flex: 1; font-size: 13px; font-weight: 600; min-width: 160px; }
.zbx-migrate-empty { padding: 32px 20px; text-align: center; color: var(--zm-muted); font-size: 13px; }

/* ── Avisos e resultados ─────────────────────────────────────────────────── */
.zbx-migrate-warnings { padding: 10px 20px; background: var(--zm-warn-bg); border-bottom: 1px solid var(--zm-border); }
.zbx-migrate-warning {
    font-size: 12px; color: var(--zm-warn); padding: 4px 0; font-weight: 600;
    display: flex; gap: 8px; align-items: flex-start; line-height: 1.45;
}
.zbx-migrate-warning-critical { color: var(--zm-err); }

.zbx-migrate-result {
    border-radius: 6px; padding: 18px 20px; margin-top: 16px;
    border: 1px solid currentColor; font-size: 13px;
}
.zbx-migrate-result-ok  { color: var(--zm-ok);  background: var(--zm-ok-bg); }
.zbx-migrate-result-err { color: var(--zm-err); background: var(--zm-err-bg); }
.zbx-migrate-result-title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; }
.zbx-migrate-result-badges { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; }
.zbx-migrate-result-badges span {
    background: var(--zm-ok); color: #fff; padding: 4px 12px;
    border-radius: 20px; font-size: 12px; font-weight: 600;
}
.zbx-migrate-result-total {
    margin-top: 12px; padding-top: 10px; border-top: 1px solid currentColor;
    font-size: 13px; font-weight: 700;
}
.zbx-migrate-result ul { margin: 8px 0 0 18px; padding: 0; }
.zbx-migrate-result li { margin: 4px 0; font-size: 13px; }

/* ── Modal de confirmacao ────────────────────────────────────────────────── */
.zbx-migrate-modal-overlay {
    position: fixed; inset: 0; background: rgba(0, 0, 0, .55);
    display: none; align-items: center; justify-content: center; z-index: 10000; padding: 16px;
}
.zbx-migrate-modal-overlay.open { display: flex; }
.zbx-migrate-modal {
    background: var(--color-bg-primary, #fff); color: var(--color-text-primary, #2b2b2b);
    border-radius: 8px; max-width: 520px; width: 100%;
    box-shadow: 0 12px 40px rgba(0, 0, 0, .35); overflow: hidden;
}
.zbx-migrate-modal-header {
    padding: 16px 20px; border-bottom: 1px solid var(--color-border, #ddd);
    font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px;
}
.zbx-migrate-modal-body { padding: 18px 20px; font-size: 13px; line-height: 1.6; }
.zbx-migrate-modal-body dl { margin: 12px 0; display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; }
.zbx-migrate-modal-body dt { font-weight: 600; color: var(--color-text-secondary, #666); }
.zbx-migrate-modal-body dd { margin: 0; word-break: break-word; }
.zbx-migrate-modal-warn {
    background: rgba(176, 42, 55, .10); border: 1px solid rgba(176, 42, 55, .4);
    color: #b02a37; border-radius: 4px; padding: 10px 12px; margin: 12px 0; font-weight: 600;
}
.zbx-migrate-modal-body input[type="text"] {
    box-sizing: border-box; width: 100%; min-height: 34px; padding: 6px 10px;
    line-height: 20px; font-size: 13px; font-family: inherit;
    border: 1px solid var(--color-border, #ccc); border-radius: 4px; margin-top: 6px;
}
.zbx-migrate-modal-error { color: #b02a37; font-size: 12px; margin-top: 6px; min-height: 16px; }
.zbx-migrate-modal-footer {
    padding: 14px 20px; border-top: 1px solid var(--color-border, #ddd);
    display: flex; justify-content: flex-end; gap: 10px;
    background: var(--color-bg-secondary, #f8f9fa);
}

/* ── Relatorio ───────────────────────────────────────────────────────────── */
.zbx-report-select-row { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.zbx-report-select-field { flex: 1 1 380px; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.zbx-report-select-field > label { font-weight: 600; font-size: 13px; }
.zbx-report-header {
    background: var(--zm-surface); border: 1px solid var(--zm-border); border-radius: 6px;
    padding: 16px 20px; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.zbx-report-user { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; flex-wrap: wrap; }
.zbx-report-fullname { font-weight: 400; font-size: 13px; color: var(--zm-muted); }
.zbx-report-summary { font-size: 13px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.zbx-report-total { font-weight: 600; }
.zbx-report-clean { color: var(--zm-ok);   background: var(--zm-ok-bg);   border: 1px solid currentColor; padding: 3px 10px; border-radius: 4px; font-size: 12px; }
.zbx-report-warn  { color: var(--zm-warn); background: var(--zm-warn-bg); border: 1px solid currentColor; padding: 3px 10px; border-radius: 4px; font-size: 12px; }
.zbx-report-table-wrap {
    background: var(--zm-surface); border: 1px solid var(--zm-border);
    border-radius: 6px; overflow-x: auto; margin-bottom: 16px;
}
.zbx-report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.zbx-report-table thead tr { background: var(--zm-surface-alt); }
.zbx-report-table th {
    padding: 10px 14px; text-align: left; font-weight: 600;
    border-bottom: 2px solid var(--zm-border); font-size: 12px;
    text-transform: uppercase; letter-spacing: .5px; color: var(--zm-muted); white-space: nowrap;
}
.zbx-report-table td { padding: 10px 14px; border-bottom: 1px solid var(--zm-border); vertical-align: top; }
.zbx-report-row-has td:first-child { font-weight: 600; }
.zbx-report-row-empty { opacity: .55; }
.zbx-report-icon { margin-right: 6px; }
.zbx-report-count-badge {
    background: var(--zm-info-bg); color: var(--zm-info); border: 1px solid currentColor;
    font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700;
}
.zbx-report-count-zero { color: var(--zm-muted); font-size: 12px; }
.zbx-report-desc { color: var(--zm-muted); font-size: 12px; }
.zbx-report-items { margin: 6px 0 0 16px; padding: 0; list-style: disc; }
.zbx-report-items li { font-size: 12px; color: var(--zm-muted); padding: 2px 0; word-break: break-word; }
.zbx-report-wrap details summary { cursor: pointer; font-size: 12px; color: var(--zm-link); }
.zbx-report-action {
    background: var(--zm-warn-bg); border: 1px solid var(--zm-border); border-radius: 6px;
    padding: 14px 18px; font-size: 13px;
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.zbx-report-action p { margin: 0; }
.zbx-report-error {
    padding: 16px 20px; color: var(--zm-err); background: var(--zm-err-bg);
    border: 1px solid currentColor; border-radius: 6px;
}

@media (prefers-reduced-motion: reduce) {
    .zbx-migrate-wrap *, .zbx-report-wrap * { scroll-behavior: auto !important; }
}
</style>
CSS;
    }

    /**
     * Deteccao de tema: mede a luminancia do <body> e marca o wrapper como
     * .is-dark. Independe de classe no body ou de variaveis CSS do tema.
     */
    public static function themeScript(string $selector): string {
        $sel = json_encode($selector);

        return <<<JS
<script>
(function () {
    var root = document.querySelector({$sel});
    if (!root) { return; }

    // Percorre os ancestrais ate achar um background opaco de verdade:
    // getComputedStyle devolve rgba(0,0,0,0) quando o elemento e transparente,
    // o que seria lido como "preto" e marcaria tema escuro por engano.
    function opaqueBg(el) {
        while (el) {
            var m = getComputedStyle(el).backgroundColor.match(/[\\d.]+/g);
            if (m && m.length >= 3 && (m.length < 4 || parseFloat(m[3]) > 0.5)) {
                return m;
            }
            el = el.parentElement;
        }
        return null;
    }

    try {
        var rgb = opaqueBg(document.body);
        if (rgb) {
            var lum = 0.299 * rgb[0] + 0.587 * rgb[1] + 0.114 * rgb[2];
            if (lum < 128) { root.classList.add('is-dark'); }
        }
    } catch (e) { /* tema nao detectavel — mantem claro */ }
})();
</script>
JS;
    }

    /** Nome de exibicao "username (Nome Sobrenome)". */
    public static function displayName(array $user): string {
        $full = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));

        return $full !== ''
            ? $user['username'] . ' (' . $full . ')'
            : (string) $user['username'];
    }
}

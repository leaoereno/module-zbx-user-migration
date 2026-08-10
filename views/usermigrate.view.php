<?php
/**
 * View: usermigrate.view
 * Interface de migracao de usuarios — multilingue via I18n.
 *
 * CSS e etiquetas de autenticacao vem de include/Ui.php e include/AuthResolver.php.
 */

require_once __DIR__ . '/../locale/I18n.php';
require_once __DIR__ . '/../include/AuthResolver.php';
require_once __DIR__ . '/../include/Ui.php';

use Modules\UserMigrate\I18n;
use Modules\UserMigrate\AuthResolver;
use Modules\UserMigrate\Ui;

$t    = I18n::get();
$lang = I18n::getLang();

/**
 * Monta as <option> de um select de usuario.
 * O marcador de autenticacao vai no texto porque <option> nao aceita HTML —
 * a etiqueta rica aparece abaixo do select, ja no formato de badge.
 */
$render_options = static function (array $users) use ($t): string {
    $html = '<option value="">' . htmlspecialchars($t('-- Select --'), ENT_QUOTES) . '</option>';

    foreach ($users as $u) {
        $label = Ui::displayName($u) . ' ' . AuthResolver::optionMarker($u['auth']);

        $html .= '<option value="' . htmlspecialchars((string) $u['userid'], ENT_QUOTES) . '"'
               . ' data-auth="' . htmlspecialchars(json_encode(AuthResolver::jsPayload($u['auth'])), ENT_QUOTES) . '"'
               . ' title="' . htmlspecialchars($label, ENT_QUOTES) . '">'
               . htmlspecialchars($label)
               . '</option>';
    }

    return $html;
};

$options_html = $render_options($data['users']);
$user_count   = count($data['users']);
?>
<div class="zbx-migrate-wrap">

    <h1 class="zbx-migrate-title"><?= $t('User Migration') ?></h1>
    <p class="zbx-migrate-subtitle"><?= $t('migrate_subtitle') ?></p>

    <div class="zbx-migrate-form">
        <div class="zbx-migrate-selectors">

            <div class="zbx-migrate-field">
                <label for="userid_src"><?= $t('Source User') ?></label>
                <div class="zbx-migrate-search-wrap">
                    <span class="zbx-migrate-search-icon" aria-hidden="true">&#128269;</span>
                    <input type="text" class="zbx-migrate-search" id="search_src"
                           placeholder="<?= htmlspecialchars($t('search_placeholder'), ENT_QUOTES) ?>"
                           aria-controls="userid_src" autocomplete="off">
                </div>
                <select id="userid_src" name="userid_src" class="zbx-migrate-select" size="1"><?= $options_html ?></select>
                <div class="zbx-migrate-count-hint" id="hint_src" aria-live="polite"></div>
                <small><?= $t('source_hint') ?></small>
            </div>

            <div class="zbx-migrate-arrow" aria-hidden="true">&rarr;</div>

            <div class="zbx-migrate-field">
                <label for="userid_dst"><?= $t('Destination User') ?></label>
                <div class="zbx-migrate-search-wrap">
                    <span class="zbx-migrate-search-icon" aria-hidden="true">&#128269;</span>
                    <input type="text" class="zbx-migrate-search" id="search_dst"
                           placeholder="<?= htmlspecialchars($t('search_placeholder'), ENT_QUOTES) ?>"
                           aria-controls="userid_dst" autocomplete="off">
                </div>
                <select id="userid_dst" name="userid_dst" class="zbx-migrate-select" size="1"><?= $options_html ?></select>
                <div class="zbx-migrate-count-hint" id="hint_dst" aria-live="polite"></div>
                <small><?= $t('destination_hint') ?></small>
            </div>

        </div>

        <div class="zbx-migrate-badge-row">
            <div id="src-badge-wrap" class="zbx-migrate-badge-wrap" style="display:none;">
                <span class="zbx-migrate-badge-label"><?= $t('Authentication type:') ?></span>
                <span id="src-badge"></span>
            </div>
            <div class="zbx-migrate-arrow-small" aria-hidden="true">&rarr;</div>
            <div id="dst-badge-wrap" class="zbx-migrate-badge-wrap" style="display:none;">
                <span class="zbx-migrate-badge-label"><?= $t('Authentication type:') ?></span>
                <span id="dst-badge"></span>
            </div>
        </div>

        <div class="zbx-migrate-actions">
            <button type="button" id="btn-preview" class="btn-alt" disabled>
                <?= $t('Check what will be migrated') ?>
            </button>
            <span class="zbx-migrate-hint" id="action-hint" aria-live="polite"><?= $t('hint_select_both') ?></span>
        </div>
    </div>

    <div id="zbx-migrate-preview" class="zbx-migrate-preview" style="display:none;">
        <div id="zbx-migrate-preview-header" class="zbx-migrate-preview-header"></div>
        <div id="zbx-migrate-preview-body"></div>
        <div class="zbx-migrate-confirm-bar">
            <span id="zbx-migrate-total"></span>
            <button type="button" id="btn-execute" class="btn-danger"><?= $t('Confirm Migration') ?></button>
            <button type="button" id="btn-cancel" class="btn-alt"><?= $t('Cancel') ?></button>
        </div>
    </div>

    <div id="zbx-migrate-result" style="display:none;" aria-live="polite"></div>

    <!-- Modal de confirmacao (substitui o prompt() nativo, que e bloqueavel pelo
         browser, nao estiliza e nao permite exibir o resumo da operacao) -->
    <div class="zbx-migrate-modal-overlay" id="confirm-modal" role="dialog" aria-modal="true"
         aria-labelledby="confirm-modal-title">
        <div class="zbx-migrate-modal">
            <div class="zbx-migrate-modal-header" id="confirm-modal-title">
                <span aria-hidden="true">&#9888;</span><?= $t('confirm_title') ?>
            </div>
            <div class="zbx-migrate-modal-body">
                <p style="margin:0"><?= $t('confirm_intro') ?></p>
                <dl>
                    <dt><?= $t('confirm_label_source') ?></dt><dd id="confirm-src"></dd>
                    <dt><?= $t('confirm_label_dest') ?></dt><dd id="confirm-dst"></dd>
                    <dt><?= $t('confirm_label_objects') ?></dt><dd id="confirm-total"></dd>
                </dl>
                <div class="zbx-migrate-modal-warn"><?= $t('confirm_irreversible') ?></div>
                <label for="confirm-input"><?= $t('confirm_type_username') ?>
                    <strong id="confirm-expected"></strong>
                </label>
                <input type="text" id="confirm-input" autocomplete="off" spellcheck="false">
                <div class="zbx-migrate-modal-error" id="confirm-error"></div>
            </div>
            <div class="zbx-migrate-modal-footer">
                <button type="button" class="btn-alt" id="confirm-cancel"><?= $t('Cancel') ?></button>
                <button type="button" class="btn-danger" id="confirm-ok" disabled><?= $t('Confirm Migration') ?></button>
            </div>
        </div>
    </div>

</div>

<?= Ui::styles() ?>
<?= Ui::themeScript('.zbx-migrate-wrap') ?>

<script>
// Strings i18n passadas do PHP para o JS
const ZBX_MIGRATE_I18N = <?= json_encode([
    'checking'           => $t('Checking...'),
    'check_btn'          => $t('Check what will be migrated'),
    'confirm_btn'        => $t('Confirm Migration'),
    'migrating'          => $t('Migrating...'),
    'cancel'             => $t('Cancel'),
    'no_objects'         => $t('No objects found linked to source user.'),
    'and_more'           => $t('...and more'),
    'items'              => $t('items'),
    'objects_to_migrate' => $t('objects to migrate'),
    'expand'             => '▼ ' . ($lang === 'pt_BR' ? 'expandir' : 'expand'),
    'collapse'           => '▲ ' . ($lang === 'pt_BR' ? 'ocultar'  : 'collapse'),
    'wrong_confirm'      => $t('Wrong confirmation.'),
    'confirm_mismatch'   => $t('confirm_mismatch'),
    'server_error'       => $t('Server communication error.'),
    'hint_select_both'   => $t('hint_select_both'),
    'hint_same_user'     => $t('hint_same_user'),
    'hint_ready'         => $t('hint_ready'),
    'showing_users'      => $t('showing_users'),
    'no_users_match'     => $t('no_users_match'),
    'auth_provider'      => $t('auth_provider'),
    'auth_jit'           => $t('auth_jit_provisioned'),
    'auth_no_frontend'   => $t('auth_no_frontend'),
    'auth_default_sfx'   => $t('auth_default_suffix'),
], JSON_UNESCAPED_UNICODE) ?>;

(function () {
    'use strict';

    const T = ZBX_MIGRATE_I18N;
    const TOTAL_USERS = <?= (int) $user_count ?>;

    let _srcUsername = '';
    let _dstUsername = '';
    let _total       = 0;

    const selSrc       = document.getElementById('userid_src');
    const selDst       = document.getElementById('userid_dst');
    const searchSrc    = document.getElementById('search_src');
    const searchDst    = document.getElementById('search_dst');
    const hintSrc      = document.getElementById('hint_src');
    const hintDst      = document.getElementById('hint_dst');
    const btnPreview   = document.getElementById('btn-preview');
    const btnExecute   = document.getElementById('btn-execute');
    const btnCancel    = document.getElementById('btn-cancel');
    const actionHint   = document.getElementById('action-hint');
    const divPreview   = document.getElementById('zbx-migrate-preview');
    const divHeader    = document.getElementById('zbx-migrate-preview-header');
    const divBody      = document.getElementById('zbx-migrate-preview-body');
    const divTotal     = document.getElementById('zbx-migrate-total');
    const divResult    = document.getElementById('zbx-migrate-result');
    const srcBadgeWrap = document.getElementById('src-badge-wrap');
    const dstBadgeWrap = document.getElementById('dst-badge-wrap');
    const srcBadge     = document.getElementById('src-badge');
    const dstBadge     = document.getElementById('dst-badge');

    const modal        = document.getElementById('confirm-modal');
    const modalInput   = document.getElementById('confirm-input');
    const modalOk      = document.getElementById('confirm-ok');
    const modalCancel  = document.getElementById('confirm-cancel');
    const modalError   = document.getElementById('confirm-error');

    // ── Filtro de usuarios ──────────────────────────────────────────────────
    // Em base real o <select> tem centenas de usuarios e rolar a lista nativa e
    // inviavel. Filtrar removendo/reinserindo <option> (em vez de display:none,
    // que o Safari ignora dentro de <select>).
    function snapshot(select) {
        return Array.from(select.options).map(function (o) {
            return { value: o.value, text: o.text, auth: o.getAttribute('data-auth'), title: o.title };
        });
    }

    const SRC_OPTIONS = snapshot(selSrc);
    const DST_OPTIONS = snapshot(selDst);

    function applyFilter(select, options, term, hint) {
        const keep    = select.value;
        const needle  = term.trim().toLowerCase();
        const frag    = document.createDocumentFragment();
        let   matches = 0;

        options.forEach(function (o) {
            if (o.value !== '' && needle !== '' && o.text.toLowerCase().indexOf(needle) === -1) {
                return;
            }

            const opt = document.createElement('option');
            opt.value = o.value;
            opt.text  = o.text;
            opt.title = o.title || '';
            if (o.auth) { opt.setAttribute('data-auth', o.auth); }
            frag.appendChild(opt);

            if (o.value !== '') { matches++; }
        });

        select.innerHTML = '';
        select.appendChild(frag);
        select.value = keep;

        // Se o usuario selecionado saiu do filtro, limpa a selecao.
        if (select.value !== keep) { select.value = ''; }

        if (needle === '') {
            hint.textContent = T.showing_users.replace('%1$s', TOTAL_USERS).replace('%2$s', TOTAL_USERS);
        }
        else if (matches === 0) {
            hint.textContent = T.no_users_match;
        }
        else {
            hint.textContent = T.showing_users.replace('%1$s', matches).replace('%2$s', TOTAL_USERS);
        }

        syncState();
    }

    let filterTimer = null;

    function debounceFilter(select, options, input, hint) {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(function () {
            applyFilter(select, options, input.value, hint);
        }, 120);
    }

    searchSrc.addEventListener('input', function () { debounceFilter(selSrc, SRC_OPTIONS, searchSrc, hintSrc); });
    searchDst.addEventListener('input', function () { debounceFilter(selDst, DST_OPTIONS, searchDst, hintDst); });

    // Enter no campo de busca nao deve submeter nada.
    [searchSrc, searchDst].forEach(function (el) {
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
    });

    // ── Etiquetas de autenticacao ───────────────────────────────────────────
    function renderBadge(auth) {
        let html = '<span class="zbx-migrate-badge ' + escAttr(auth.class) + '" title="' + escAttr(auth.title) + '">' +
                   escHtml(auth.label);

        if (auth.inherited) {
            html += '<em>' + escHtml(T.auth_default_sfx) + '</em>';
        }

        html += '</span>';

        if (auth.provider) {
            html += '<span class="zbx-migrate-chip" title="' + escAttr(T.auth_provider) + '">' +
                    escHtml(auth.provider) + '</span>';
        }

        if (auth.provisioned) {
            html += '<span class="zbx-migrate-chip zbx-chip-jit" title="' + escAttr(T.auth_jit) + '">JIT</span>';
        }

        if (auth.blocked) {
            html += '<span class="zbx-migrate-chip zbx-chip-blocked">' + escHtml(T.auth_no_frontend) + '</span>';
        }

        return html;
    }

    function updateBadge(select, badgeEl, badgeWrap) {
        const opt = select.options[select.selectedIndex];

        if (!opt || !opt.value) {
            badgeWrap.style.display = 'none';
            return;
        }

        let auth;
        try { auth = JSON.parse(opt.getAttribute('data-auth') || '{}'); }
        catch (e) { auth = {}; }

        badgeEl.innerHTML = renderBadge({
            label:       auth.label       || 'N/D',
            class:       auth.class       || 'zbx-badge-unknown',
            provider:    auth.provider    || '',
            provisioned: !!auth.provisioned,
            inherited:   !!auth.inherited,
            blocked:     !!auth.blocked,
            title:       auth.title       || ''
        });

        badgeWrap.style.display = '';
    }

    // ── Estado do formulario ────────────────────────────────────────────────
    function syncState() {
        updateBadge(selSrc, srcBadge, srcBadgeWrap);
        updateBadge(selDst, dstBadge, dstBadgeWrap);

        const bothSet = selSrc.value !== '' && selDst.value !== '';
        const same    = bothSet && selSrc.value === selDst.value;

        btnPreview.disabled = !bothSet || same;

        if (!bothSet)   { actionHint.textContent = T.hint_select_both; }
        else if (same)  { actionHint.textContent = T.hint_same_user; }
        else            { actionHint.textContent = T.hint_ready; }

        divPreview.style.display = 'none';
        divResult.style.display  = 'none';
    }

    selSrc.addEventListener('change', syncState);
    selDst.addEventListener('change', syncState);

    // ── Comunicacao com o backend ───────────────────────────────────────────
    // No Zabbix 7.0 o parametro "action" precisa ir na query string: enviado no
    // body ele conflita com o roteador do frontend.
    function post(action, params) {
        return fetch('zabbix.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(params).toString()
        }).then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        });
    }

    btnPreview.addEventListener('click', function () {
        btnPreview.disabled    = true;
        btnPreview.textContent = T.checking;
        divResult.style.display = 'none';

        post('usermigrate.preview', { userid_src: selSrc.value, userid_dst: selDst.value })
            .then(function (data) {
                btnPreview.disabled    = false;
                btnPreview.textContent = T.check_btn;

                if (data.error) {
                    showResult('error', data.error.title, data.error.messages || []);
                    return;
                }

                renderPreview(data);
            })
            .catch(function (err) {
                btnPreview.disabled    = false;
                btnPreview.textContent = T.check_btn;
                showResult('error', T.server_error, [err.message]);
            });
    });

    function renderPreview(data) {
        _srcUsername = data.user_src.username;
        _dstUsername = data.user_dst.username;
        _total       = data.total || 0;

        divHeader.innerHTML = '<strong>' + escHtml(formatUser(data.user_src)) + '</strong>' +
                              ' &nbsp;&rarr;&nbsp; ' +
                              '<strong>' + escHtml(formatUser(data.user_dst)) + '</strong>';

        let warningsHtml = '';

        if (data.warnings && data.warnings.length) {
            warningsHtml = '<div class="zbx-migrate-warnings">' +
                data.warnings.map(function (w) {
                    const text     = (typeof w === 'string') ? w : w.text;
                    const critical = (typeof w === 'object' && w.level === 'critical');
                    return '<div class="zbx-migrate-warning' + (critical ? ' zbx-migrate-warning-critical' : '') + '">' +
                           '<span aria-hidden="true">&#9888;</span><span>' + escHtml(text) + '</span></div>';
                }).join('') +
                '</div>';
        }

        if (!data.preview || data.preview.length === 0) {
            divBody.innerHTML   = warningsHtml + '<div class="zbx-migrate-empty">' + escHtml(T.no_objects) + '</div>';
            divTotal.textContent = '0 ' + T.objects_to_migrate;
            btnExecute.style.display = 'none';
        }
        else {
            divBody.innerHTML    = warningsHtml + data.preview.map(renderSection).join('');
            divTotal.textContent = data.total + ' ' + T.objects_to_migrate;
            btnExecute.style.display = '';

            divBody.querySelectorAll('.zbx-migrate-section-header').forEach(function (hdr) {
                hdr.setAttribute('tabindex', '0');
                hdr.setAttribute('role', 'button');

                const toggleSection = function () {
                    const items = hdr.closest('.zbx-migrate-section').querySelector('.zbx-migrate-items');
                    if (!items) { return; }
                    items.classList.toggle('open');
                    const toggle = hdr.querySelector('.zbx-migrate-toggle');
                    if (toggle) { toggle.textContent = items.classList.contains('open') ? T.collapse : T.expand; }
                    hdr.setAttribute('aria-expanded', items.classList.contains('open') ? 'true' : 'false');
                };

                hdr.addEventListener('click', toggleSection);
                hdr.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleSection(); }
                });
            });
        }

        divPreview.style.display = '';
        divPreview.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function formatUser(u) {
        const full = ((u.name || '') + ' ' + (u.surname || '')).trim();
        return full ? u.username + ' (' + full + ')' : u.username;
    }

    function renderSection(section) {
        const itemsHtml = section.items && section.items.length
            ? '<div class="zbx-migrate-items"><ul>' +
              section.items.slice(0, 50).map(function (i) { return '<li>' + escHtml(String(i)) + '</li>'; }).join('') +
              (section.items.length > 50
                  ? '<li><em>' + escHtml(T.and_more) + ' ' + (section.items.length - 50) + ' ' + escHtml(T.items) + '</em></li>'
                  : '') +
              '</ul></div>'
            : '';

        return '<div class="zbx-migrate-section">' +
            '<div class="zbx-migrate-section-header" aria-expanded="false">' +
                '<span class="zbx-migrate-section-title">' + escHtml(section.entity) +
                    '<span class="zbx-migrate-count">' + escHtml(String(section.count)) + '</span></span>' +
                (itemsHtml ? '<span class="zbx-migrate-toggle">' + escHtml(T.expand) + '</span>' : '') +
            '</div>' +
            '<div class="zbx-migrate-section-desc">' + escHtml(section.description) + '</div>' +
            itemsHtml +
        '</div>';
    }

    btnCancel.addEventListener('click', function () { divPreview.style.display = 'none'; });

    // ── Modal de confirmacao ────────────────────────────────────────────────
    let lastFocused = null;

    function openModal() {
        lastFocused = document.activeElement;

        document.getElementById('confirm-src').textContent      = formatUser({ username: _srcUsername });
        document.getElementById('confirm-dst').textContent      = formatUser({ username: _dstUsername });
        document.getElementById('confirm-total').textContent    = _total + ' ' + T.objects_to_migrate;
        document.getElementById('confirm-expected').textContent = _srcUsername;

        modalInput.value      = '';
        modalError.textContent = '';
        modalOk.disabled       = true;

        modal.classList.add('open');
        modalInput.focus();
    }

    function closeModal() {
        modal.classList.remove('open');
        if (lastFocused) { lastFocused.focus(); }
    }

    modalInput.addEventListener('input', function () {
        modalOk.disabled       = modalInput.value.trim() !== _srcUsername;
        modalError.textContent = '';
    });

    modalInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !modalOk.disabled) { e.preventDefault(); modalOk.click(); }
    });

    modalCancel.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) { closeModal(); }
    });

    btnExecute.addEventListener('click', openModal);

    modalOk.addEventListener('click', function () {
        if (modalInput.value.trim() !== _srcUsername) {
            modalError.textContent = T.confirm_mismatch;
            return;
        }

        closeModal();
        execute();
    });

    function execute() {
        btnExecute.disabled    = true;
        btnExecute.textContent = T.migrating;
        btnCancel.disabled     = true;

        post('usermigrate.execute', { userid_src: selSrc.value, userid_dst: selDst.value })
            .then(function (data) {
                divPreview.style.display = 'none';
                btnExecute.disabled      = false;
                btnExecute.textContent   = T.confirm_btn;
                btnCancel.disabled       = false;

                if (data.error) {
                    showResult('error', data.error.title, data.error.messages || []);
                    return;
                }

                showResult('success', data.success.title, data.success.messages || []);

                selSrc.value = '';
                selDst.value = '';
                srcBadgeWrap.style.display = 'none';
                dstBadgeWrap.style.display = 'none';
                syncState();
                divResult.style.display = '';
            })
            .catch(function (err) {
                btnExecute.disabled    = false;
                btnExecute.textContent = T.confirm_btn;
                btnCancel.disabled     = false;
                showResult('error', T.server_error, [err.message]);
            });
    }

    // ── Resultado ───────────────────────────────────────────────────────────
    function showResult(type, title, messages) {
        if (type === 'success') {
            const items = messages.filter(function (m, i) { return i < messages.length - 1 && m; });
            const total = messages[messages.length - 1] || '';

            divResult.innerHTML =
                '<div class="zbx-migrate-result zbx-migrate-result-ok">' +
                    '<div class="zbx-migrate-result-title"><span aria-hidden="true">&#10004;</span>' + escHtml(title) + '</div>' +
                    (items.length
                        ? '<div class="zbx-migrate-result-badges">' +
                          items.map(function (m) { return '<span>&#10004; ' + escHtml(m) + '</span>'; }).join('') +
                          '</div>'
                        : '') +
                    (total ? '<div class="zbx-migrate-result-total">' + escHtml(total) + '</div>' : '') +
                '</div>';
        }
        else {
            divResult.innerHTML =
                '<div class="zbx-migrate-result zbx-migrate-result-err">' +
                    '<div class="zbx-migrate-result-title"><span aria-hidden="true">&#10006;</span>' + escHtml(title) + '</div>' +
                    (messages.length
                        ? '<ul>' + messages.map(function (m) { return '<li>' + escHtml(m) + '</li>'; }).join('') + '</ul>'
                        : '') +
                '</div>';
        }

        divResult.style.display = '';
        divResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function escHtml(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function escAttr(str) { return escHtml(str); }

    // Estado inicial
    hintSrc.textContent = T.showing_users.replace('%1$s', TOTAL_USERS).replace('%2$s', TOTAL_USERS);
    hintDst.textContent = hintSrc.textContent;
    syncState();

})();
</script>

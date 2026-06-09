<?php
/**
 * View: usermigrate.view
 * Interface de migração de usuários — multilíngue via I18n.
 */

require_once __DIR__ . '/../locale/I18n.php';
use Modules\UserMigrate\I18n;

$t          = I18n::get();
$module_dir = basename(dirname(__DIR__));
$lang       = I18n::getLang();

function getAuthBadgeMigrate(int $gui_access): array {
    switch ($gui_access) {
        case 1:  return ['label' => 'LOCAL',    'class' => 'zbx-badge-local'];
        case 2:  return ['label' => 'LDAP',     'class' => 'zbx-badge-ldap'];
        case 3:  return ['label' => 'DISABLED', 'class' => 'zbx-badge-disabled'];
        default: return ['label' => 'SYSTEM',   'class' => 'zbx-badge-system'];
    }
}
?>
<div class="zbx-migrate-wrap">

    <h1 class="zbx-migrate-title"><?= $t('User Migration') ?></h1>
    <p class="zbx-migrate-subtitle"><?= $t('migrate_subtitle') ?></p>

    <div class="zbx-migrate-form">
        <div class="zbx-migrate-selectors">

            <div class="zbx-migrate-field">
                <label for="userid_src"><?= $t('Source User') ?></label>
                <select id="userid_src" name="userid_src" class="zbx-migrate-select">
                    <option value=""><?= $t('-- Select --') ?></option>
                    <?php foreach ($data['users'] as $u):
                        $badge = getAuthBadgeMigrate((int)$u['gui_access']);
                    ?>
                        <option value="<?= htmlspecialchars($u['userid']) ?>"
                                data-badge="<?= $badge['label'] ?>"
                                data-badge-class="<?= $badge['class'] ?>">
                            <?= htmlspecialchars($u['username']) ?>
                            <?= ($u['name'] || $u['surname'])
                                ? '(' . htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) . ')'
                                : '' ?>
                            [<?= $badge['label'] ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
                <small><?= $t('source_hint') ?></small>
            </div>

            <div class="zbx-migrate-arrow">→</div>

            <div class="zbx-migrate-field">
                <label for="userid_dst"><?= $t('Destination User') ?></label>
                <select id="userid_dst" name="userid_dst" class="zbx-migrate-select">
                    <option value=""><?= $t('-- Select --') ?></option>
                    <?php foreach ($data['users'] as $u):
                        $badge = getAuthBadgeMigrate((int)$u['gui_access']);
                    ?>
                        <option value="<?= htmlspecialchars($u['userid']) ?>"
                                data-badge="<?= $badge['label'] ?>"
                                data-badge-class="<?= $badge['class'] ?>">
                            <?= htmlspecialchars($u['username']) ?>
                            <?= ($u['name'] || $u['surname'])
                                ? '(' . htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) . ')'
                                : '' ?>
                            [<?= $badge['label'] ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
                <small><?= $t('destination_hint') ?></small>
            </div>

        </div>

        <div class="zbx-migrate-badge-row">
            <div id="src-badge-wrap" class="zbx-migrate-badge-wrap" style="display:none;">
                <?= $t('Authentication type:') ?> <span id="src-badge" class="zbx-migrate-badge"></span>
            </div>
            <div class="zbx-migrate-arrow-small">→</div>
            <div id="dst-badge-wrap" class="zbx-migrate-badge-wrap" style="display:none;">
                <?= $t('Authentication type:') ?> <span id="dst-badge" class="zbx-migrate-badge"></span>
            </div>
        </div>

        <div class="zbx-migrate-actions">
            <button id="btn-preview" class="btn-alt" disabled>
                <?= $t('Check what will be migrated') ?>
            </button>
        </div>
    </div>

    <div id="zbx-migrate-preview" class="zbx-migrate-preview" style="display:none;">
        <div id="zbx-migrate-preview-header" class="zbx-migrate-preview-header"></div>
        <div id="zbx-migrate-preview-body"></div>
        <div class="zbx-migrate-confirm-bar">
            <span id="zbx-migrate-total"></span>
            <button id="btn-execute" class="btn-danger"><?= $t('Confirm Migration') ?></button>
            <button id="btn-cancel" class="btn-alt"><?= $t('Cancel') ?></button>
        </div>
    </div>

    <div id="zbx-migrate-result" style="display:none;"></div>

</div>

<style>
.zbx-migrate-wrap { max-width: 900px; margin: 24px auto; padding: 0 16px; }
.zbx-migrate-title { font-size: 20px; font-weight: 600; margin-bottom: 6px; color: var(--color-text-primary, #333); }
.zbx-migrate-subtitle { color: var(--color-text-secondary, #666); margin-bottom: 24px; font-size: 13px; }
.zbx-migrate-form { background: var(--color-bg-primary, #fff); border: 1px solid var(--color-border, #ddd); border-radius: 4px; padding: 24px; margin-bottom: 24px; }
.zbx-migrate-selectors { display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
.zbx-migrate-field { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 6px; }
.zbx-migrate-field label { font-weight: 600; font-size: 13px; }
.zbx-migrate-field small { font-size: 11px; color: var(--color-text-secondary, #888); }
.zbx-migrate-select { width: 100%; padding: 6px 8px; border: 1px solid var(--color-border, #ccc); border-radius: 3px; font-size: 13px; }
.zbx-migrate-arrow { font-size: 28px; color: var(--color-text-secondary, #aaa); padding-bottom: 20px; user-select: none; }
.zbx-migrate-arrow-small { font-size: 18px; color: var(--color-text-secondary, #aaa); user-select: none; }
.zbx-migrate-badge-row { display: flex; align-items: center; gap: 16px; margin-top: 12px; min-height: 24px; flex-wrap: wrap; }
.zbx-migrate-badge-wrap { flex: 1; font-size: 12px; color: var(--color-text-secondary, #666); display: flex; align-items: center; gap: 6px; }
.zbx-migrate-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.zbx-badge-local    { background: #fff3cd; color: #856404; }
.zbx-badge-ldap     { background: #d1e7dd; color: #0a4f2e; }
.zbx-badge-system   { background: #e2e3e5; color: #383d41; }
.zbx-badge-disabled { background: #f8d7da; color: #58151c; }
.zbx-migrate-actions { margin-top: 20px; }
.zbx-migrate-preview { background: var(--color-bg-primary, #fff); border: 1px solid var(--color-border, #ddd); border-radius: 4px; overflow: hidden; }
.zbx-migrate-preview-header { background: var(--color-bg-secondary, #f8f9fa); border-bottom: 1px solid var(--color-border, #ddd); padding: 14px 20px; font-size: 14px; }
.zbx-migrate-section { border-bottom: 1px solid var(--color-border, #eee); padding: 16px 20px; }
.zbx-migrate-section:last-child { border-bottom: none; }
.zbx-migrate-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; cursor: pointer; user-select: none; }
.zbx-migrate-section-title { font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.zbx-migrate-count { background: #d35400; color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 600; }
.zbx-migrate-section-desc { font-size: 12px; color: var(--color-text-secondary, #888); margin-bottom: 8px; }
.zbx-migrate-items { display: none; margin-top: 8px; }
.zbx-migrate-items.open { display: block; }
.zbx-migrate-items ul { margin: 0; padding: 0 0 0 18px; list-style: disc; }
.zbx-migrate-items li { font-size: 12px; color: var(--color-text-secondary, #555); padding: 2px 0; }
.zbx-migrate-toggle { font-size: 11px; color: var(--color-link, #1a7dc4); cursor: pointer; }
.zbx-migrate-confirm-bar { background: var(--color-bg-secondary, #f8f9fa); border-top: 1px solid var(--color-border, #ddd); padding: 14px 20px; display: flex; align-items: center; gap: 12px; }
#zbx-migrate-total { flex: 1; font-size: 13px; font-weight: 600; }
.zbx-migrate-result-ok { padding: 14px 18px; border-radius: 4px; background: #d1e7dd; border: 1px solid #a3cfbb; color: #0a4f2e; font-size: 13px; }
.zbx-migrate-result-ok strong { display: block; margin-bottom: 4px; }
.zbx-migrate-result-err { padding: 14px 18px; border-radius: 4px; background: #f8d7da; border: 1px solid #f1aeb5; color: #58151c; font-size: 13px; }
.zbx-migrate-result-err strong { display: block; margin-bottom: 4px; }
.zbx-migrate-empty { padding: 32px 20px; text-align: center; color: var(--color-text-secondary, #888); font-size: 13px; }
.zbx-migrate-warnings { padding: 12px 20px; background: #fff3cd; border-bottom: 1px solid #ffc107; }
.zbx-migrate-warning { font-size: 12px; color: #856404; padding: 3px 0; font-weight: 600; }
</style>

<script>
// Strings i18n passadas do PHP para o JS
const ZBX_MIGRATE_I18N = <?= json_encode([
    'checking'         => $t('Checking...'),
    'check_btn'        => $t('Check what will be migrated'),
    'confirm_btn'      => $t('Confirm Migration'),
    'migrating'        => $t('Migrating...'),
    'cancel'           => $t('Cancel'),
    'no_objects'       => $t('No objects found linked to source user.'),
    'and_more'         => $t('...and more'),
    'items'            => $t('items'),
    'objects_to_migrate' => $t('objects to migrate'),
    'expand'           => '▼ ' . ($lang === 'pt_BR' ? 'expandir' : 'expand'),
    'collapse'         => '▲ ' . ($lang === 'pt_BR' ? 'ocultar'  : 'collapse'),
    'confirm_prompt'   => $t('confirm_prompt'),
    'wrong_confirm'    => $t('Wrong confirmation.'),
    'confirm_mismatch' => $t('confirm_mismatch'),
    'server_error'     => $t('Server communication error.'),
    'origin'           => $t('origin'),
    'destination'      => $t('destination'),
]) ?>;

(function () {
    'use strict';

    const T = ZBX_MIGRATE_I18N;

    let _srcUsername = '';
    let _dstUsername = '';

    const selSrc       = document.getElementById('userid_src');
    const selDst       = document.getElementById('userid_dst');
    const btnPreview   = document.getElementById('btn-preview');
    const btnExecute   = document.getElementById('btn-execute');
    const btnCancel    = document.getElementById('btn-cancel');
    const divPreview   = document.getElementById('zbx-migrate-preview');
    const divHeader    = document.getElementById('zbx-migrate-preview-header');
    const divBody      = document.getElementById('zbx-migrate-preview-body');
    const divTotal     = document.getElementById('zbx-migrate-total');
    const divResult    = document.getElementById('zbx-migrate-result');
    const srcBadgeWrap = document.getElementById('src-badge-wrap');
    const dstBadgeWrap = document.getElementById('dst-badge-wrap');
    const srcBadge     = document.getElementById('src-badge');
    const dstBadge     = document.getElementById('dst-badge');

    function updateBadge(select, badgeEl, badgeWrap) {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) { badgeWrap.style.display = 'none'; return; }
        const label = opt.getAttribute('data-badge') || 'SYSTEM';
        const cls   = opt.getAttribute('data-badge-class') || 'zbx-badge-system';
        badgeEl.textContent = label;
        badgeEl.className   = 'zbx-migrate-badge ' + cls;
        badgeWrap.style.display = '';
    }

    function syncPreviewButton() {
        updateBadge(selSrc, srcBadge, srcBadgeWrap);
        updateBadge(selDst, dstBadge, dstBadgeWrap);
        const ready = selSrc.value && selDst.value && selSrc.value !== selDst.value;
        btnPreview.disabled = !ready;
        divPreview.style.display = 'none';
        divResult.style.display  = 'none';
    }

    selSrc.addEventListener('change', syncPreviewButton);
    selDst.addEventListener('change', syncPreviewButton);

    function post(action, params) {
        const body = new URLSearchParams({ action, ...params });
        return fetch('zabbix.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(r => r.json());
    }

    btnPreview.addEventListener('click', function () {
        btnPreview.disabled = true;
        btnPreview.textContent = T.checking;
        divResult.style.display = 'none';

        post('usermigrate.preview', { userid_src: selSrc.value, userid_dst: selDst.value })
        .then(function (data) {
            btnPreview.disabled = false;
            btnPreview.textContent = T.check_btn;
            if (data.error) { showResult('error', data.error.title, data.error.messages || []); return; }
            renderPreview(data);
        })
        .catch(function (err) {
            btnPreview.disabled = false;
            btnPreview.textContent = T.check_btn;
            showResult('error', T.server_error, [err.message]);
        });
    });

    function renderPreview(data) {
        _srcUsername = data.user_src.username;
        _dstUsername = data.user_dst.username;

        const srcName = formatUser(data.user_src);
        const dstName = formatUser(data.user_dst);
        divHeader.innerHTML = '<strong>' + escHtml(srcName) + '</strong> &nbsp;→&nbsp; <strong>' + escHtml(dstName) + '</strong>';

        let warningsHtml = '';
        if (data.warnings && data.warnings.length) {
            warningsHtml = '<div class="zbx-migrate-warnings">' +
                data.warnings.map(w => '<div class="zbx-migrate-warning">⚠ ' + escHtml(w) + '</div>').join('') +
                '</div>';
        }

        if (!data.preview || data.preview.length === 0) {
            divBody.innerHTML = warningsHtml + '<div class="zbx-migrate-empty">' + escHtml(T.no_objects) + '</div>';
            divTotal.textContent = '0 ' + T.objects_to_migrate;
            btnExecute.style.display = 'none';
        } else {
            divBody.innerHTML = warningsHtml + data.preview.map(renderSection).join('');
            divTotal.textContent = data.total + ' ' + T.objects_to_migrate;
            btnExecute.style.display = '';

            divBody.querySelectorAll('.zbx-migrate-section-header').forEach(function (hdr) {
                hdr.addEventListener('click', function () {
                    const items  = hdr.closest('.zbx-migrate-section').querySelector('.zbx-migrate-items');
                    if (items) items.classList.toggle('open');
                    const toggle = hdr.querySelector('.zbx-migrate-toggle');
                    if (toggle) toggle.textContent = items.classList.contains('open') ? T.collapse : T.expand;
                });
            });
        }

        divPreview.style.display = '';
        divPreview.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function formatUser(u) {
        return u.username + ((u.name || u.surname) ? ' (' + (u.name + ' ' + u.surname).trim() + ')' : '');
    }

    function renderSection(section) {
        const itemsHtml = section.items && section.items.length
            ? '<div class="zbx-migrate-items"><ul>' +
              section.items.slice(0, 50).map(i => '<li>' + escHtml(String(i)) + '</li>').join('') +
              (section.items.length > 50 ? '<li><em>' + T.and_more + ' ' + (section.items.length - 50) + ' ' + T.items + '</em></li>' : '') +
              '</ul></div>'
            : '';

        return '<div class="zbx-migrate-section">' +
            '<div class="zbx-migrate-section-header">' +
                '<span class="zbx-migrate-section-title">' + escHtml(section.entity) +
                    '<span class="zbx-migrate-count">' + section.count + '</span></span>' +
                (itemsHtml ? '<span class="zbx-migrate-toggle">' + T.expand + '</span>' : '') +
            '</div>' +
            '<div class="zbx-migrate-section-desc">' + escHtml(section.description) + '</div>' +
            itemsHtml +
        '</div>';
    }

    btnCancel.addEventListener('click', function () { divPreview.style.display = 'none'; });

    btnExecute.addEventListener('click', function () {
        const srcUsername = _srcUsername || '';
        const dstUsername = _dstUsername || '';

        const typed = prompt(T.confirm_prompt.replace('%s', srcUsername).replace('%s', dstUsername));
        if (typed === null) return;

        if (typed.trim() !== srcUsername) {
            showResult('error', T.wrong_confirm, [T.confirm_mismatch]);
            return;
        }

        btnExecute.disabled = true;
        btnExecute.textContent = T.migrating;
        btnCancel.disabled = true;

        post('usermigrate.execute', { userid_src: selSrc.value, userid_dst: selDst.value })
        .then(function (data) {
            divPreview.style.display = 'none';
            btnExecute.disabled = false;
            btnExecute.textContent = T.confirm_btn;
            btnCancel.disabled = false;

            if (data.error) {
                showResult('error', data.error.title, data.error.messages || []);
            } else {
                showResult('success', data.success.title, data.success.messages || []);
                selSrc.value = ''; selDst.value = '';
                srcBadgeWrap.style.display = 'none';
                dstBadgeWrap.style.display = 'none';
                syncPreviewButton();
            }
        })
        .catch(function (err) {
            btnExecute.disabled = false;
            btnExecute.textContent = T.confirm_btn;
            btnCancel.disabled = false;
            showResult('error', T.server_error, [err.message]);
        });
    });

    function showResult(type, title, messages) {
        const cls = type === 'success' ? 'zbx-migrate-result-ok' : 'zbx-migrate-result-err';
        let msgs = '';
        if (messages.length) {
            if (type === 'success') {
                const items = messages.slice(0, -1);
                const total = messages[messages.length - 1];
                msgs = '<div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px">' +
                    items.map(m => '<span style="background:#0a4f2e;color:#fff;padding:2px 10px;border-radius:10px;font-size:12px">' + escHtml(m) + '</span>').join('') +
                    '</div>';
                if (total) msgs += '<div style="margin-top:8px;font-size:12px;font-weight:600">' + escHtml(total) + '</div>';
            } else {
                msgs = '<ul style="margin:6px 0 0 16px">' + messages.map(m => '<li>' + escHtml(m) + '</li>').join('') + '</ul>';
            }
        }
        divResult.innerHTML = '<div class="' + cls + '"><strong>' + escHtml(title) + '</strong>' + msgs + '</div>';
        divResult.style.display = '';
        divResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})();
</script>

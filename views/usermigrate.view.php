<?php
/**
 * View: usermigrate.view
 *
 * O JS e carregado via tag <script src> apontando para assets/js/,
 * que e servido estaticamente pelo Nginx/Apache.
 * O caminho usa o nome do diretorio fisico do modulo (MODULE_DIR),
 * nao o id do manifest, para garantir compatibilidade entre instalacoes.
 *
 * gui_access values (from usrgrp):
 *   0 = GROUP_GUI_ACCESS_SYSTEM  (default do sistema)
 *   1 = GROUP_GUI_ACCESS_INTERNAL (local)
 *   2 = GROUP_GUI_ACCESS_LDAP
 *   3 = GROUP_GUI_ACCESS_DISABLED
 *
 * @var array $data['users']     Lista de usuarios com gui_access resolvido
 * @var array $data['user_data'] Dados do usuario logado
 */

$module_dir = basename(dirname(__DIR__));
$js_src = 'modules/' . $module_dir . '/assets/js/usermigrate.js?v=1.1.0';

/**
 * Retorna o label e a classe CSS do badge de autenticacao
 * com base no gui_access do usuario.
 */
function getAuthBadge(int $gui_access): array {
    switch ($gui_access) {
        case 1:  return ['label' => 'LOCAL',    'class' => 'zbx-badge-local'];
        case 2:  return ['label' => 'LDAP',     'class' => 'zbx-badge-ldap'];
        case 3:  return ['label' => 'DISABLED', 'class' => 'zbx-badge-disabled'];
        default: return ['label' => 'SYSTEM',   'class' => 'zbx-badge-system'];
    }
}
?>
<div class="zbx-migrate-wrap">

    <h1 class="zbx-migrate-title">Migração de Usuário</h1>
    <p class="zbx-migrate-subtitle">
        Transfere dashboards, mapas, relatórios, mídias, actions e grupos
        do usuário de <strong>origem</strong> para o usuário de <strong>destino</strong>.
    </p>

    <!-- ── Formulário de seleção ── -->
    <div class="zbx-migrate-form">

        <div class="zbx-migrate-selectors">

            <div class="zbx-migrate-field">
                <label for="userid_src">Usuário de Origem</label>
                <select id="userid_src" name="userid_src" class="zbx-migrate-select">
                    <option value="">-- Selecione --</option>
                    <?php foreach ($data['users'] as $u):
                        $badge = getAuthBadge((int)$u['gui_access']);
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
                <small>Usuário local cujos objetos serão transferidos</small>
            </div>

            <div class="zbx-migrate-arrow">→</div>

            <div class="zbx-migrate-field">
                <label for="userid_dst">Usuário de Destino</label>
                <select id="userid_dst" name="userid_dst" class="zbx-migrate-select">
                    <option value="">-- Selecione --</option>
                    <?php foreach ($data['users'] as $u):
                        $badge = getAuthBadge((int)$u['gui_access']);
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
                <small>Usuário que receberá os objetos</small>
            </div>

        </div>

        <!-- Badge dinamico exibido abaixo dos selects apos selecao -->
        <div class="zbx-migrate-badge-row">
            <div id="src-badge-wrap" class="zbx-migrate-badge-wrap" style="display:none;">
                Tipo de autenticação: <span id="src-badge" class="zbx-migrate-badge"></span>
            </div>
            <div class="zbx-migrate-arrow-small">→</div>
            <div id="dst-badge-wrap" class="zbx-migrate-badge-wrap" style="display:none;">
                Tipo de autenticação: <span id="dst-badge" class="zbx-migrate-badge"></span>
            </div>
        </div>

        <div class="zbx-migrate-actions">
            <button id="btn-preview" class="btn-alt" disabled>
                Verificar o que será migrado
            </button>
        </div>

    </div>

    <!-- ── Área de preview ── -->
    <div id="zbx-migrate-preview" class="zbx-migrate-preview" style="display:none;">

        <div id="zbx-migrate-preview-header" class="zbx-migrate-preview-header"></div>
        <div id="zbx-migrate-preview-body"></div>

        <div class="zbx-migrate-confirm-bar">
            <span id="zbx-migrate-total"></span>
            <button id="btn-execute" class="btn-danger">Confirmar Migração</button>
            <button id="btn-cancel" class="btn-alt">Cancelar</button>
        </div>

    </div>

    <!-- ── Resultado ── -->
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
.zbx-badge-saml     { background: #cfe2ff; color: #084298; }
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
</style>

<script>
/**
 * zbx-user-migrate — usermigrate.js v1.2.0
 *
 * - Badge de autenticacao dinamico por IdP
 * - CSRF token enviado no execute
 * - Avisos de Super Admin exibidos no preview
 * - Confirmacao por digitacao de username antes de executar
 */

(function () {
    'use strict';

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

    // ── Badge de autenticacao ────────────────────────────────────────────────
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

    // ── POST helper ──────────────────────────────────────────────────────────
    function post(action, params) {
        const body = new URLSearchParams({ action, ...params });
        return fetch('zabbix.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString()
        }).then(r => r.json());
    }

    // ── Preview ──────────────────────────────────────────────────────────────
    btnPreview.addEventListener('click', function () {
        btnPreview.disabled = true;
        btnPreview.textContent = 'Verificando...';
        divResult.style.display = 'none';

        post('usermigrate.preview', {
            userid_src: selSrc.value,
            userid_dst: selDst.value
        })
        .then(function (data) {
            btnPreview.disabled = false;
            btnPreview.textContent = 'Verificar o que será migrado';

            if (data.error) {
                showResult('error', data.error.title, data.error.messages || []);
                return;
            }
            renderPreview(data);
        })
        .catch(function (err) {
            btnPreview.disabled = false;
            btnPreview.textContent = 'Verificar o que será migrado';
            showResult('error', 'Erro ao comunicar com o servidor.', [err.message]);
        });
    });

    // ── Renderiza preview ────────────────────────────────────────────────────
    function renderPreview(data) {
        const srcName = formatUser(data.user_src);
        const dstName = formatUser(data.user_dst);

        // Guarda usernames para confirmacao na execucao
        divPreview.dataset.srcUsername = data.user_src.username;
        divPreview.dataset.dstUsername = data.user_dst.username;

        divHeader.innerHTML =
            '<strong>' + escHtml(srcName) + '</strong> &nbsp;→&nbsp; <strong>' + escHtml(dstName) + '</strong>';

        // Exibe avisos de Super Admin / Admin nativo
        let warningsHtml = '';
        if (data.warnings && data.warnings.length) {
            warningsHtml = '<div class="zbx-migrate-warnings">' +
                data.warnings.map(w => '<div class="zbx-migrate-warning">⚠ ' + escHtml(w) + '</div>').join('') +
                '</div>';
        }

        if (!data.preview || data.preview.length === 0) {
            divBody.innerHTML = warningsHtml +
                '<div class="zbx-migrate-empty">Nenhum objeto encontrado vinculado ao usuário de origem.</div>';
            divTotal.textContent = '0 objetos a migrar';
            btnExecute.style.display = 'none';
        } else {
            divBody.innerHTML = warningsHtml + data.preview.map(renderSection).join('');
            divTotal.textContent = data.total + ' objeto(s) a migrar';
            btnExecute.style.display = '';

            divBody.querySelectorAll('.zbx-migrate-section-header').forEach(function (hdr) {
                hdr.addEventListener('click', function () {
                    const items = hdr.closest('.zbx-migrate-section').querySelector('.zbx-migrate-items');
                    if (items) items.classList.toggle('open');
                    const toggle = hdr.querySelector('.zbx-migrate-toggle');
                    if (toggle) toggle.textContent = items.classList.contains('open') ? '▲ ocultar' : '▼ expandir';
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
              (section.items.length > 50 ? '<li><em>...e mais ' + (section.items.length - 50) + ' itens</em></li>' : '') +
              '</ul></div>'
            : '';

        return '<div class="zbx-migrate-section">' +
            '<div class="zbx-migrate-section-header">' +
                '<span class="zbx-migrate-section-title">' + escHtml(section.entity) +
                    '<span class="zbx-migrate-count">' + section.count + '</span></span>' +
                (itemsHtml ? '<span class="zbx-migrate-toggle">▼ expandir</span>' : '') +
            '</div>' +
            '<div class="zbx-migrate-section-desc">' + escHtml(section.description) + '</div>' +
            itemsHtml +
        '</div>';
    }

    // ── Cancelar ─────────────────────────────────────────────────────────────
    btnCancel.addEventListener('click', function () {
        divPreview.style.display = 'none';
    });

    // ── Executar migracao ────────────────────────────────────────────────────
    btnExecute.addEventListener('click', function () {
        const srcUsername = divPreview.dataset.srcUsername || '';
        const dstUsername = divPreview.dataset.dstUsername || '';

        // Confirmacao por digitacao do username de origem
        const typed = prompt(
            'Para confirmar a migração, digite o username do usuário de ORIGEM:\n\n' +
            'Origem:  ' + srcUsername + '\n' +
            'Destino: ' + dstUsername + '\n\n' +
            'Esta operação é irreversível.'
        );

        if (typed === null) return; // cancelou

        if (typed.trim() !== srcUsername) {
            showResult('error', 'Confirmação incorreta.', ['O username digitado não corresponde ao usuário de origem.']);
            return;
        }

        btnExecute.disabled = true;
        btnExecute.textContent = 'Migrando...';
        btnCancel.disabled = true;

        console.log('[migrate] chamando execute, src=' + selSrc.value + ' dst=' + selDst.value + ' srcUsername=' + srcUsername);
        post('usermigrate.execute', {
            userid_src: selSrc.value,
            userid_dst: selDst.value
        })
        .then(function (data) {
            divPreview.style.display = 'none';
            btnExecute.disabled = false;
            btnExecute.textContent = 'Confirmar Migração';
            btnCancel.disabled = false;

            if (data.error) {
                showResult('error', data.error.title, data.error.messages || []);
            } else {
                showResult('success', data.success.title, data.success.messages || []);
                selSrc.value = '';
                selDst.value = '';
                srcBadgeWrap.style.display = 'none';
                dstBadgeWrap.style.display = 'none';
                syncPreviewButton();
            }
        })
        .catch(function (err) {
            btnExecute.disabled = false;
            btnExecute.textContent = 'Confirmar Migração';
            btnCancel.disabled = false;
            showResult('error', 'Erro ao comunicar com o servidor.', [err.message]);
        });
    });

    // ── Resultado ────────────────────────────────────────────────────────────
    function showResult(type, title, messages) {
        const cls  = type === 'success' ? 'zbx-migrate-result-ok' : 'zbx-migrate-result-err';
        const msgs = messages.length
            ? '<ul style="margin:6px 0 0 16px">' + messages.map(m => '<li>' + escHtml(m) + '</li>').join('') + '</ul>'
            : '';
        divResult.innerHTML = '<div class="' + cls + '"><strong>' + escHtml(title) + '</strong>' + msgs + '</div>';
        divResult.style.display = '';
        divResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})();

</script>

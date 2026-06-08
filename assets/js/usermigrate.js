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

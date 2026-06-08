/**
 * zbx-user-migrate — usermigrate.view.js
 *
 * Gerencia toda a lógica de interação da página de migração:
 *   - Habilita botão "Verificar" quando ambos os selects têm valor
 *   - Chama usermigrate.preview e renderiza a tabela de preview
 *   - Chama usermigrate.execute após confirmação
 *   - Exibe resultado de sucesso ou erro
 */

(function () {
    'use strict';

    const selSrc     = document.getElementById('userid_src');
    const selDst     = document.getElementById('userid_dst');
    const btnPreview = document.getElementById('btn-preview');
    const btnExecute = document.getElementById('btn-execute');
    const btnCancel  = document.getElementById('btn-cancel');
    const divPreview = document.getElementById('zbx-migrate-preview');
    const divHeader  = document.getElementById('zbx-migrate-preview-header');
    const divBody    = document.getElementById('zbx-migrate-preview-body');
    const divTotal   = document.getElementById('zbx-migrate-total');
    const divResult  = document.getElementById('zbx-migrate-result');

    // ── Habilita botão Preview quando ambos os selects têm valor ────────────
    function syncPreviewButton() {
        const ready = selSrc.value && selDst.value && selSrc.value !== selDst.value;
        btnPreview.disabled = !ready;

        // Esconde preview anterior ao trocar seleção
        divPreview.style.display = 'none';
        divResult.style.display  = 'none';
    }

    selSrc.addEventListener('change', syncPreviewButton);
    selDst.addEventListener('change', syncPreviewButton);

    // ── POST helper ──────────────────────────────────────────────────────────
    function post(action, params) {
        const body = new URLSearchParams({ action, ...params });
        return fetch('zabbix.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
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
        const srcName = data.user_src.username + (data.user_src.name ? ' (' + data.user_src.name + ' ' + data.user_src.surname + ')' : '');
        const dstName = data.user_dst.username + (data.user_dst.name ? ' (' + data.user_dst.name + ' ' + data.user_dst.surname + ')' : '');

        divHeader.innerHTML =
            '<strong>' + escHtml(srcName) + '</strong>' +
            ' &nbsp;→&nbsp; ' +
            '<strong>' + escHtml(dstName) + '</strong>';

        if (!data.preview || data.preview.length === 0) {
            divBody.innerHTML = '<div class="zbx-migrate-empty">Nenhum objeto encontrado vinculado ao usuário de origem.</div>';
            divTotal.textContent = '0 objetos a migrar';
            btnExecute.style.display = 'none';
        } else {
            divBody.innerHTML = data.preview.map(renderSection).join('');
            divTotal.textContent = data.total + ' objeto(s) a migrar';
            btnExecute.style.display = '';

            // Toggle de expansão das listas
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

    function renderSection(section) {
        const itemsHtml = section.items && section.items.length
            ? '<div class="zbx-migrate-items">' +
              '<ul>' + section.items.slice(0, 50).map(i => '<li>' + escHtml(String(i)) + '</li>').join('') +
              (section.items.length > 50 ? '<li><em>...e mais ' + (section.items.length - 50) + ' itens</em></li>' : '') +
              '</ul></div>'
            : '';

        return '<div class="zbx-migrate-section">' +
            '<div class="zbx-migrate-section-header">' +
                '<span class="zbx-migrate-section-title">' +
                    escHtml(section.entity) +
                    '<span class="zbx-migrate-count">' + section.count + '</span>' +
                '</span>' +
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

    // ── Executar migração ────────────────────────────────────────────────────
    btnExecute.addEventListener('click', function () {
        const srcLabel = selSrc.options[selSrc.selectedIndex].text;
        const dstLabel = selDst.options[selDst.selectedIndex].text;

        const confirmed = confirm(
            'CONFIRMAR MIGRAÇÃO\n\n' +
            'Origem:  ' + srcLabel + '\n' +
            'Destino: ' + dstLabel + '\n\n' +
            'Esta operação é irreversível. Todos os objetos listados serão\n' +
            'transferidos para o usuário destino.\n\n' +
            'Deseja continuar?'
        );

        if (!confirmed) return;

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
                // Reseta os selects
                selSrc.value = '';
                selDst.value = '';
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

    // ── Exibe resultado ──────────────────────────────────────────────────────
    function showResult(type, title, messages) {
        const cls = type === 'success' ? 'zbx-migrate-result-ok' : 'zbx-migrate-result-err';
        const msgs = messages.length
            ? '<ul style="margin:6px 0 0 16px">' + messages.map(m => '<li>' + escHtml(m) + '</li>').join('') + '</ul>'
            : '';

        divResult.innerHTML = '<div class="' + cls + '"><strong>' + escHtml(title) + '</strong>' + msgs + '</div>';
        divResult.style.display = '';
        divResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Escape HTML ──────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})();

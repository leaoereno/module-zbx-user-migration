<?php
/**
 * View: usermigrate.view
 * Renderiza a interface de migração de usuários.
 *
 * @var array $data['users']     Lista de usuários para os selects
 * @var array $data['user_data'] Dados do usuário logado
 */

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
                <label for="userid_src">Usuário de Origem <span class="zbx-migrate-badge zbx-badge-src">Local</span></label>
                <select id="userid_src" name="userid_src" class="zbx-migrate-select">
                    <option value="">-- Selecione --</option>
                    <?php foreach ($data['users'] as $u): ?>
                        <option value="<?= htmlspecialchars($u['userid']) ?>"
                                data-username="<?= htmlspecialchars($u['username']) ?>">
                            <?= htmlspecialchars($u['username']) ?>
                            <?= ($u['name'] || $u['surname']) ? '(' . htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Usuário local cujos objetos serão transferidos</small>
            </div>

            <div class="zbx-migrate-arrow">→</div>

            <div class="zbx-migrate-field">
                <label for="userid_dst">Usuário de Destino <span class="zbx-migrate-badge zbx-badge-dst">LDAP</span></label>
                <select id="userid_dst" name="userid_dst" class="zbx-migrate-select">
                    <option value="">-- Selecione --</option>
                    <?php foreach ($data['users'] as $u): ?>
                        <option value="<?= htmlspecialchars($u['userid']) ?>"
                                data-username="<?= htmlspecialchars($u['username']) ?>">
                            <?= htmlspecialchars($u['username']) ?>
                            <?= ($u['name'] || $u['surname']) ? '(' . htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Usuário LDAP que receberá os objetos</small>
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
            <button id="btn-execute" class="btn-danger">
                Confirmar Migração
            </button>
            <button id="btn-cancel" class="btn-alt">
                Cancelar
            </button>
        </div>

    </div>

    <!-- ── Resultado ── -->
    <div id="zbx-migrate-result" style="display:none;"></div>

</div>

<style>
.zbx-migrate-wrap {
    max-width: 900px;
    margin: 24px auto;
    padding: 0 16px;
}
.zbx-migrate-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--color-text-primary, #333);
}
.zbx-migrate-subtitle {
    color: var(--color-text-secondary, #666);
    margin-bottom: 24px;
    font-size: 13px;
}
.zbx-migrate-form {
    background: var(--color-bg-primary, #fff);
    border: 1px solid var(--color-border, #ddd);
    border-radius: 4px;
    padding: 24px;
    margin-bottom: 24px;
}
.zbx-migrate-selectors {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}
.zbx-migrate-field {
    flex: 1;
    min-width: 220px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.zbx-migrate-field label {
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.zbx-migrate-field small {
    font-size: 11px;
    color: var(--color-text-secondary, #888);
}
.zbx-migrate-select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--color-border, #ccc);
    border-radius: 3px;
    font-size: 13px;
}
.zbx-migrate-arrow {
    font-size: 28px;
    color: var(--color-text-secondary, #aaa);
    padding-bottom: 20px;
    user-select: none;
}
.zbx-migrate-badge {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.zbx-badge-src { background: #fff3cd; color: #856404; }
.zbx-badge-dst { background: #d1e7dd; color: #0a4f2e; }
.zbx-migrate-actions {
    margin-top: 20px;
}
.zbx-migrate-preview {
    background: var(--color-bg-primary, #fff);
    border: 1px solid var(--color-border, #ddd);
    border-radius: 4px;
    overflow: hidden;
}
.zbx-migrate-preview-header {
    background: var(--color-bg-secondary, #f8f9fa);
    border-bottom: 1px solid var(--color-border, #ddd);
    padding: 14px 20px;
    font-size: 14px;
}
.zbx-migrate-section {
    border-bottom: 1px solid var(--color-border, #eee);
    padding: 16px 20px;
}
.zbx-migrate-section:last-child { border-bottom: none; }
.zbx-migrate-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    cursor: pointer;
    user-select: none;
}
.zbx-migrate-section-title {
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.zbx-migrate-count {
    background: #d35400;
    color: #fff;
    font-size: 11px;
    padding: 1px 7px;
    border-radius: 10px;
    font-weight: 600;
}
.zbx-migrate-section-desc {
    font-size: 12px;
    color: var(--color-text-secondary, #888);
    margin-bottom: 8px;
}
.zbx-migrate-items {
    display: none;
    margin-top: 8px;
}
.zbx-migrate-items.open { display: block; }
.zbx-migrate-items ul {
    margin: 0;
    padding: 0 0 0 18px;
    list-style: disc;
}
.zbx-migrate-items li {
    font-size: 12px;
    color: var(--color-text-secondary, #555);
    padding: 2px 0;
}
.zbx-migrate-toggle {
    font-size: 11px;
    color: var(--color-link, #1a7dc4);
    cursor: pointer;
}
.zbx-migrate-confirm-bar {
    background: var(--color-bg-secondary, #f8f9fa);
    border-top: 1px solid var(--color-border, #ddd);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
#zbx-migrate-total {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
}
.zbx-migrate-result-ok {
    padding: 14px 18px;
    border-radius: 4px;
    background: #d1e7dd;
    border: 1px solid #a3cfbb;
    color: #0a4f2e;
    font-size: 13px;
}
.zbx-migrate-result-ok strong { display: block; margin-bottom: 4px; }
.zbx-migrate-result-err {
    padding: 14px 18px;
    border-radius: 4px;
    background: #f8d7da;
    border: 1px solid #f1aeb5;
    color: #58151c;
    font-size: 13px;
}
.zbx-migrate-result-err strong { display: block; margin-bottom: 4px; }
.zbx-migrate-empty {
    padding: 32px 20px;
    text-align: center;
    color: var(--color-text-secondary, #888);
    font-size: 13px;
}
</style>

<script src="modules/zbx-user-migrate/assets/js/usermigrate.js?v=1.0"></script>

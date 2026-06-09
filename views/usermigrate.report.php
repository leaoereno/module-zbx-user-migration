<?php
/**
 * View: usermigrate.report
 * Relatório de objetos vinculados a um usuário — multilíngue via I18n.
 */

use Modules\UserMigrate\I18n;

$t = I18n::get();

function getAuthBadgeReport2(int $gui_access): array {
    switch ($gui_access) {
        case 1:  return ['label' => 'LOCAL',    'class' => 'zbx-badge-local'];
        case 2:  return ['label' => 'LDAP',     'class' => 'zbx-badge-ldap'];
        case 3:  return ['label' => 'DISABLED', 'class' => 'zbx-badge-disabled'];
        default: return ['label' => 'SYSTEM',   'class' => 'zbx-badge-system'];
    }
}
?>
<div class="zbx-report-wrap">

    <h1 class="zbx-report-title"><?= $t('User Objects Report') ?></h1>
    <p class="zbx-report-subtitle"><?= $t('report_subtitle') ?></p>

    <div class="zbx-report-form">
        <form method="get" action="zabbix.php">
            <input type="hidden" name="action" value="usermigrate.report">
            <div class="zbx-report-select-row">
                <label for="userid"><?= $t('User') ?></label>
                <select id="userid" name="userid" class="zbx-migrate-select" style="max-width:420px">
                    <option value=""><?= $t('-- Select a user --') ?></option>
                    <?php foreach ($data['users'] as $u):
                        $badge    = getAuthBadgeReport2((int)$u['gui_access']);
                        $selected = ($data['userid'] == $u['userid']) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($u['userid']) ?>" <?= $selected ?>>
                            <?= htmlspecialchars($u['username']) ?>
                            <?= ($u['name'] || $u['surname'])
                                ? '(' . htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) . ')'
                                : '' ?>
                            [<?= $badge['label'] ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-alt"><?= $t('Generate Report') ?></button>
            </div>
        </form>
    </div>

    <?php if ($data['user_info'] && $data['report']): ?>
        <?php
        $u     = $data['user_info'];
        $badge = getAuthBadgeReport2((int)$u['gui_access']);
        $total = array_sum(array_column($data['report'], 'count'));
        ?>

        <div class="zbx-report-header">
            <div class="zbx-report-user">
                <strong><?= htmlspecialchars($u['username']) ?></strong>
                <?php if ($u['name'] || $u['surname']): ?>
                    <span class="zbx-report-fullname"><?= htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) ?></span>
                <?php endif; ?>
                <span class="zbx-migrate-badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
            </div>
            <div class="zbx-report-summary">
                <span class="zbx-report-total"><?= $t('linked_objects', $total) ?></span>
                <?php if ($total === 0): ?>
                    <span class="zbx-report-clean"><?= $t('safe_to_remove') ?></span>
                <?php else: ?>
                    <span class="zbx-report-warn"><?= $t('has_objects_warn') ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="zbx-report-table-wrap">
            <table class="zbx-report-table">
                <thead>
                    <tr>
                        <th><?= $t('Entity') ?></th>
                        <th><?= $t('Count') ?></th>
                        <th><?= $t('Description') ?></th>
                        <th><?= $t('Items') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['report'] as $section): ?>
                        <tr class="<?= $section['count'] > 0 ? 'zbx-report-row-has' : 'zbx-report-row-empty' ?>">
                            <td>
                                <span class="zbx-report-icon"><?= $section['icon'] ?></span>
                                <?= htmlspecialchars($section['entity']) ?>
                            </td>
                            <td>
                                <?php if ($section['count'] > 0): ?>
                                    <span class="zbx-report-count-badge"><?= $section['count'] ?></span>
                                <?php else: ?>
                                    <span class="zbx-report-count-zero">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="zbx-report-desc"><?= htmlspecialchars($section['description']) ?></td>
                            <td>
                                <?php if ($section['count'] > 0 && !empty($section['items'])): ?>
                                    <details>
                                        <summary><?= $t('items_expand', count($section['items'])) ?></summary>
                                        <ul class="zbx-report-items">
                                            <?php foreach (array_slice($section['items'], 0, 50) as $item): ?>
                                                <li><?= htmlspecialchars((string)$item) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($section['items']) > 50): ?>
                                                <li><em><?= $t('more_items', count($section['items']) - 50) ?></em></li>
                                            <?php endif; ?>
                                        </ul>
                                    </details>
                                <?php elseif ($section['count'] > 0): ?>
                                    <span class="zbx-report-desc">—</span>
                                <?php else: ?>
                                    <span class="zbx-report-desc"><?= $t('None') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > 0): ?>
        <div class="zbx-report-action">
            <p><?= $t('report_action_msg') ?></p>
            <a href="zabbix.php?action=usermigrate.view" class="btn-alt"><?= $t('Go to User Migration') ?></a>
        </div>
        <?php endif; ?>

    <?php elseif ($data['userid'] && !$data['user_info']): ?>
        <div class="zbx-report-error"><?= $t('User not found.') ?></div>
    <?php endif; ?>

</div>

<style>
.zbx-report-wrap { max-width: 1000px; margin: 24px auto; padding: 0 16px; }
.zbx-report-title { font-size: 20px; font-weight: 600; margin-bottom: 6px; color: var(--color-text-primary, #333); }
.zbx-report-subtitle { color: var(--color-text-secondary, #666); margin-bottom: 24px; font-size: 13px; }
.zbx-report-form { background: var(--color-bg-primary, #fff); border: 1px solid var(--color-border, #ddd); border-radius: 4px; padding: 20px 24px; margin-bottom: 24px; }
.zbx-report-select-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.zbx-report-select-row label { font-weight: 600; font-size: 13px; white-space: nowrap; }
.zbx-migrate-select { padding: 6px 8px; border: 1px solid var(--color-border, #ccc); border-radius: 3px; font-size: 13px; }
.zbx-report-header { background: var(--color-bg-primary, #fff); border: 1px solid var(--color-border, #ddd); border-radius: 4px; padding: 16px 20px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.zbx-report-user { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; }
.zbx-report-fullname { font-weight: 400; font-size: 13px; color: var(--color-text-secondary, #666); }
.zbx-migrate-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }
.zbx-badge-local    { background: #fff3cd; color: #856404; }
.zbx-badge-ldap     { background: #d1e7dd; color: #0a4f2e; }
.zbx-badge-system   { background: #e2e3e5; color: #383d41; }
.zbx-badge-disabled { background: #f8d7da; color: #58151c; }
.zbx-report-summary { font-size: 13px; }
.zbx-report-total { font-weight: 600; margin-right: 12px; }
.zbx-report-clean { color: #0a4f2e; background: #d1e7dd; padding: 3px 10px; border-radius: 3px; font-size: 12px; }
.zbx-report-warn  { color: #856404; background: #fff3cd; padding: 3px 10px; border-radius: 3px; font-size: 12px; }
.zbx-report-table-wrap { background: var(--color-bg-primary, #fff); border: 1px solid var(--color-border, #ddd); border-radius: 4px; overflow: hidden; margin-bottom: 16px; }
.zbx-report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.zbx-report-table thead tr { background: var(--color-bg-secondary, #f8f9fa); }
.zbx-report-table th { padding: 10px 14px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--color-border, #ddd); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-secondary, #666); }
.zbx-report-table td { padding: 10px 14px; border-bottom: 1px solid var(--color-border, #eee); vertical-align: top; }
.zbx-report-row-has td:first-child { font-weight: 600; }
.zbx-report-row-empty { opacity: 0.5; }
.zbx-report-icon { margin-right: 6px; }
.zbx-report-count-badge { background: #d35400; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
.zbx-report-count-zero { color: var(--color-text-secondary, #aaa); font-size: 12px; }
.zbx-report-desc { color: var(--color-text-secondary, #666); font-size: 12px; }
.zbx-report-items { margin: 6px 0 0 16px; padding: 0; list-style: disc; }
.zbx-report-items li { font-size: 12px; color: var(--color-text-secondary, #555); padding: 2px 0; }
details summary { cursor: pointer; font-size: 12px; color: var(--color-link, #1a7dc4); }
.zbx-report-action { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 14px 18px; font-size: 13px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.zbx-report-error { padding: 20px; color: #58151c; background: #f8d7da; border-radius: 4px; }
</style>

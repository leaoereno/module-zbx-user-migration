<?php
/**
 * View: usermigrate.report
 * Relatorio de objetos vinculados a um usuario — multilingue via I18n.
 *
 * CSS e etiquetas de autenticacao vem de include/Ui.php e include/AuthResolver.php.
 */

require_once __DIR__ . '/../locale/I18n.php';
require_once __DIR__ . '/../include/AuthResolver.php';
require_once __DIR__ . '/../include/Ui.php';

use Modules\UserMigrate\I18n;
use Modules\UserMigrate\AuthResolver;
use Modules\UserMigrate\Ui;

$t = I18n::get();
?>
<div class="zbx-report-wrap">

    <h1 class="zbx-report-title"><?= $t('User Objects Report') ?></h1>
    <p class="zbx-report-subtitle"><?= $t('report_subtitle') ?></p>

    <div class="zbx-report-form">
        <form method="get" action="zabbix.php">
            <input type="hidden" name="action" value="usermigrate.report">
            <div class="zbx-report-select-row">
                <div class="zbx-report-select-field">
                    <label for="userid"><?= $t('User') ?></label>
                    <div class="zbx-migrate-search-wrap">
                        <span class="zbx-migrate-search-icon" aria-hidden="true">&#128269;</span>
                        <input type="text" class="zbx-migrate-search" id="search_user"
                               placeholder="<?= htmlspecialchars($t('search_placeholder'), ENT_QUOTES) ?>"
                               aria-controls="userid" autocomplete="off">
                    </div>
                    <select id="userid" name="userid" class="zbx-migrate-select">
                        <option value=""><?= $t('-- Select a user --') ?></option>
                        <?php foreach ($data['users'] as $u):
                            $label    = Ui::displayName($u) . ' ' . AuthResolver::optionMarker($u['auth']);
                            $selected = ((string) $data['userid'] === (string) $u['userid']) ? ' selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars((string) $u['userid'], ENT_QUOTES) ?>"<?= $selected ?>
                                    title="<?= htmlspecialchars($label, ENT_QUOTES) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="zbx-migrate-count-hint" id="hint_user" aria-live="polite"></div>
                </div>
                <button type="submit" class="btn-alt"><?= $t('Generate Report') ?></button>
            </div>
        </form>
    </div>

    <?php if ($data['user_info'] && $data['report']): ?>
        <?php
        $u     = $data['user_info'];
        $total = array_sum(array_column($data['report'], 'count'));
        ?>

        <div class="zbx-report-header">
            <div class="zbx-report-user">
                <strong><?= htmlspecialchars($u['username']) ?></strong>
                <?php if ($u['name'] || $u['surname']): ?>
                    <span class="zbx-report-fullname"><?= htmlspecialchars(trim($u['name'] . ' ' . $u['surname'])) ?></span>
                <?php endif; ?>
                <?= AuthResolver::badgeHtml($u['auth']) ?>
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
                        <th scope="col"><?= $t('Entity') ?></th>
                        <th scope="col"><?= $t('Count') ?></th>
                        <th scope="col"><?= $t('Description') ?></th>
                        <th scope="col"><?= $t('Items') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['report'] as $section): ?>
                        <tr class="<?= $section['count'] > 0 ? 'zbx-report-row-has' : 'zbx-report-row-empty' ?>">
                            <td>
                                <span class="zbx-report-icon" aria-hidden="true"><?= $section['icon'] ?></span>
                                <?= htmlspecialchars($section['entity']) ?>
                            </td>
                            <td>
                                <?php if ($section['count'] > 0): ?>
                                    <span class="zbx-report-count-badge"><?= (int) $section['count'] ?></span>
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
                                                <li><?= htmlspecialchars((string) $item) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($section['items']) > 50): ?>
                                                <li><em><?= $t('more_items', count($section['items']) - 50) ?></em></li>
                                            <?php endif; ?>
                                        </ul>
                                    </details>
                                <?php elseif ($section['count'] > 0): ?>
                                    <span class="zbx-report-desc">&mdash;</span>
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

<?= Ui::styles() ?>
<?= Ui::themeScript('.zbx-report-wrap') ?>

<script>
(function () {
    'use strict';

    const STRINGS = <?= json_encode([
        'showing'  => $t('showing_users'),
        'no_match' => $t('no_users_match'),
    ], JSON_UNESCAPED_UNICODE) ?>;

    const select = document.getElementById('userid');
    const search = document.getElementById('search_user');
    const hint   = document.getElementById('hint_user');

    if (!select || !search) { return; }

    // Snapshot das options: filtrar removendo/reinserindo em vez de display:none,
    // que o Safari ignora dentro de <select>.
    const ALL = Array.from(select.options).map(function (o) {
        return { value: o.value, text: o.text, title: o.title, selected: o.selected };
    });

    const TOTAL = ALL.length - 1;

    function setHint(shown) {
        hint.textContent = (shown === 0)
            ? STRINGS.no_match
            : STRINGS.showing.replace('%1$s', shown).replace('%2$s', TOTAL);
    }

    let timer = null;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            const keep   = select.value;
            const needle = search.value.trim().toLowerCase();
            const frag   = document.createDocumentFragment();
            let   shown  = 0;

            ALL.forEach(function (o) {
                if (o.value !== '' && needle !== '' && o.text.toLowerCase().indexOf(needle) === -1) {
                    return;
                }

                const opt = document.createElement('option');
                opt.value = o.value;
                opt.text  = o.text;
                opt.title = o.title || '';
                frag.appendChild(opt);

                if (o.value !== '') { shown++; }
            });

            select.innerHTML = '';
            select.appendChild(frag);
            select.value = keep;
            if (select.value !== keep) { select.value = ''; }

            setHint(needle === '' ? TOTAL : shown);
        }, 120);
    });

    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });

    setHint(TOTAL);
})();
</script>

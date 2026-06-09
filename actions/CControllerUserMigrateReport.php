<?php

namespace Modules\UserMigrate\Actions;

use Modules\UserMigrate\I18n;

use CController;
use CControllerResponseData;
use CRoleHelper;

class CControllerUserMigrateReport extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'userid' => 'db users.userid'
        ];
        $ret = $this->validateInput($fields);
        if (!$ret) {
            $this->setResponse(new \CControllerResponseFatal());
        }
        return $ret;
    }

    protected function checkPermissions(): bool {
        return in_array(\CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]);
    }

    protected function doAction(): void {
        $users = DBfetchArray(DBselect(
            'SELECT u.userid, u.username, u.name, u.surname,' .
            ' COALESCE(MAX(g.gui_access), 0) AS gui_access' .
            ' FROM users u' .
            ' LEFT JOIN users_groups ug ON ug.userid = u.userid' .
            ' LEFT JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid' .
            ' GROUP BY u.userid, u.username, u.name, u.surname' .
            ' ORDER BY u.username ASC'
        ));

        $report    = null;
        $user_info = null;
        $userid    = $this->getInput('userid', '');

        if ($userid) {
            $user_info = DBfetch(DBselect(
                'SELECT u.userid, u.username, u.name, u.surname,' .
                ' COALESCE(MAX(g.gui_access), 0) AS gui_access' .
                ' FROM users u' .
                ' LEFT JOIN users_groups ug ON ug.userid = u.userid' .
                ' LEFT JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid' .
                ' WHERE u.userid=' . zbx_dbstr($userid) .
                ' GROUP BY u.userid, u.username, u.name, u.surname'
            ));

            if ($user_info) {
                $report = $this->buildReport($userid);
            }
        }

        $this->setResponse(new CControllerResponseData([
            'users'     => $users,
            'userid'    => $userid,
            'user_info' => $user_info,
            'report'    => $report,
            'user_data' => \CWebUser::$data
        ]));
    }

    private function buildReport(string $userid): array {
        $sections = [];

        // Dashboards
        $rows = DBfetchArray(DBselect(
            'SELECT name FROM dashboard WHERE userid=' . zbx_dbstr($userid) . ' AND templateid IS NULL'
        ));
        $sections[] = [
            'entity' => I18n::get()('Dashboards'),
            'icon'        => '📊',
            'count'       => count($rows),
            'description' => I18n::get()('dash_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Permissoes de Dashboard
        $rows = DBfetchArray(DBselect(
            'SELECT d.name FROM dashboard_user du' .
            ' JOIN dashboard d ON d.dashboardid = du.dashboardid' .
            ' WHERE du.userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Dashboard Permissions'),
            'icon'        => '🔑',
            'count'       => count($rows),
            'description' => I18n::get()('dash_perm_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Mapas de Rede
        $rows = DBfetchArray(DBselect(
            'SELECT name FROM sysmaps WHERE userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Network Maps'),
            'icon'        => '🗺️',
            'count'       => count($rows),
            'description' => I18n::get()('map_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Permissoes de Mapa
        $rows = DBfetchArray(DBselect(
            'SELECT s.name FROM sysmap_user su' .
            ' JOIN sysmaps s ON s.sysmapid = su.sysmapid' .
            ' WHERE su.userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Map Permissions'),
            'icon'        => '🔑',
            'count'       => count($rows),
            'description' => I18n::get()('map_perm_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Relatorios
        $rows = DBfetchArray(DBselect(
            'SELECT name FROM report WHERE userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Scheduled Reports'),
            'icon'        => '📋',
            'count'       => count($rows),
            'description' => I18n::get()('report_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Destinatarios de Relatorio
        $rows = DBfetchArray(DBselect(
            'SELECT r.name FROM report_user ru' .
            ' JOIN report r ON r.reportid = ru.reportid' .
            ' WHERE ru.userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Report Recipients'),
            'icon'        => '📨',
            'count'       => count($rows),
            'description' => I18n::get()('report_recip_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Midias
        $rows = DBfetchArray(DBselect(
            'SELECT sendto FROM media WHERE userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Notification Media'),
            'icon'        => '🔔',
            'count'       => count($rows),
            'description' => I18n::get()('media_desc'),
            'items'       => array_column($rows, 'sendto')
        ];

        // Action Triggers
        $rows = DBfetchArray(DBselect(
            'SELECT op.operationid FROM opmessage_usr o' .
            ' JOIN operations op ON op.operationid = o.operationid' .
            ' WHERE o.userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Action Recipients'),
            'icon'        => '⚡',
            'count'       => count($rows),
            'description' => I18n::get()('action_desc'),
            'items'       => array_map(fn($r) => 'Operation ID: ' . $r['operationid'], $rows)
        ];

        // API Tokens
        $rows = DBfetchArray(DBselect(
            'SELECT name FROM token WHERE userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('API Tokens'),
            'icon'        => '🔐',
            'count'       => count($rows),
            'description' => I18n::get()('token_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Grupos
        $rows = DBfetchArray(DBselect(
            'SELECT g.name FROM users_groups ug' .
            ' JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid' .
            ' WHERE ug.userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('User Groups'),
            'icon'        => '👥',
            'count'       => count($rows),
            'description' => I18n::get()('group_desc'),
            'items'       => array_column($rows, 'name')
        ];

        // Preferencias
        $row = DBfetch(DBselect(
            'SELECT COUNT(*) AS cnt FROM profiles WHERE userid=' . zbx_dbstr($userid)
        ));
        $sections[] = [
            'entity' => I18n::get()('Interface Preferences'),
            'icon'        => '⚙️',
            'count'       => (int)($row['cnt'] ?? 0),
            'description' => I18n::get()('pref_desc'),
            'items'       => []
        ];

        // Historico de migracao
        $rows = DBfetchArray(DBselect(
            'SELECT auditid, clock, details FROM auditlog' .
            ' WHERE resourcetype=11 AND resourceid=' . zbx_dbstr($userid) .
            ' AND details LIKE \'%migration%\'' .
            ' ORDER BY clock DESC' .
            ' LIMIT 10'
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Migration History'),
                'icon'        => '📜',
                'count'       => count($rows),
                'description' => I18n::get()('history_desc'),
                'items'       => array_map(fn($r) => date('d/m/Y H:i', $r['clock']) . ' — ' . $r['details'], $rows)
            ];
        }

        return $sections;
    }
}

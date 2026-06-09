<?php

namespace Modules\UserMigrate\Actions;

require_once __DIR__ . '/../locale/I18n.php';
use Modules\UserMigrate\I18n;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CRoleHelper;

class CControllerUserMigratePreview extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'userid_src' => 'required|db users.userid',
            'userid_dst' => 'required|db users.userid',
        ];

        $ret = $this->validateInput($fields);
        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }
        return $ret;
    }

    protected function checkPermissions(): bool {
        return in_array(\CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]);
    }

    protected function doAction(): void {
        $src = $this->getInput('userid_src');
        $dst = $this->getInput('userid_dst');

        if ($src === $dst) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => ['title' => I18n::get()('err_same_user')]
                ])
            ]));
            return;
        }

        $user_src = DBfetch(DBselect(
            'SELECT userid, username, name, surname FROM users WHERE userid=' . zbx_dbstr($src)
        ));
        $user_dst = DBfetch(DBselect(
            'SELECT userid, username, name, surname FROM users WHERE userid=' . zbx_dbstr($dst)
        ));

        if (!$user_src || !$user_dst) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => ['title' => I18n::get()('err_user_not_found')]
                ])
            ]));
            return;
        }

        // Aviso extra se usuario de origem for Admin nativo
        $warnings = [];
        if ((int)$src === 1) {
            $warnings[] = I18n::get()('warn_admin_native');
        }

        // Aviso se usuario de origem tiver role privilegiada
        $role_row = DBfetch(DBselect(
            'SELECT r.name FROM users u' .
            ' JOIN role r ON r.roleid = u.roleid' .
            ' WHERE u.userid=' . zbx_dbstr($src)
        ));
        if ($role_row && stripos($role_row['name'], 'super') !== false) {
            $warnings[] = 'ATENÇÃO: O usuário de origem possui role de Super Admin (' . $role_row['name'] . '). Revise antes de confirmar.';
        }

        $preview = $this->buildPreview($src, $dst);

        $this->setResponse(new CControllerResponseData([
            'main_block' => json_encode([
                'success'  => true,
                'user_src' => $user_src,
                'user_dst' => $user_dst,
                'preview'  => $preview,
                'warnings' => $warnings,
                'total'    => array_sum(array_column($preview, 'count'))
            ])
        ]));
    }

    private function buildPreview(string $src, string $dst): array {
        $sections = [];

        // ── Dashboards (ownership) ──────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT dashboardid, name FROM dashboard' .
            ' WHERE userid=' . zbx_dbstr($src) .
            ' AND templateid IS NULL'
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Dashboards'),
                'count'       => count($rows),
                'description' => I18n::get()('dash_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Dashboards (permissoes compartilhadas) ──────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT du.dashboard_userid, d.name FROM dashboard_user du' .
            ' JOIN dashboard d ON d.dashboardid = du.dashboardid' .
            ' WHERE du.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Dashboard Permissions'),
                'count'       => count($rows),
                'description' => I18n::get()('dash_perm_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Mapas de rede (ownership) ───────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT sysmapid, name FROM sysmaps WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Network Maps'),
                'count'       => count($rows),
                'description' => I18n::get()('map_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Mapas de rede (permissoes compartilhadas) ───────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT su.sysmapuserid, s.name FROM sysmap_user su' .
            ' JOIN sysmaps s ON s.sysmapid = su.sysmapid' .
            ' WHERE su.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Map Permissions'),
                'count'       => count($rows),
                'description' => I18n::get()('map_perm_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Relatorios agendados ────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT reportid, name FROM report WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Scheduled Reports'),
                'count'       => count($rows),
                'description' => I18n::get()('report_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Relatorios (destinatarios) ──────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT ru.reportuserid, r.name FROM report_user ru' .
            ' JOIN report r ON r.reportid = ru.reportid' .
            ' WHERE ru.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Report Recipients'),
                'count'       => count($rows),
                'description' => I18n::get()('report_recip_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Midias de notificacao ───────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT mediaid, sendto FROM media WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Notification Media'),
                'count'       => count($rows),
                'description' => I18n::get()('media_desc'),
                'items'       => array_column($rows, 'sendto')
            ];
        }

        // ── Action operations ───────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT o.opmessage_usrid, op.operationid FROM opmessage_usr o' .
            ' JOIN operations op ON op.operationid = o.operationid' .
            ' WHERE o.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Action Recipients'),
                'count'       => count($rows),
                'description' => I18n::get()('action_desc'),
                'items'       => array_map(fn($r) => 'Operation ID: ' . $r['operationid'], $rows)
            ];
        }

        // ── API Tokens ──────────────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT tokenid, name FROM token WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('API Tokens'),
                'count'       => count($rows),
                'description' => I18n::get()('token_desc'),
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Grupos de usuario ───────────────────────────────────────────────
        $src_groups = DBfetchArray(DBselect(
            'SELECT ug.usrgrpid, g.name FROM users_groups ug' .
            ' JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid' .
            ' WHERE ug.userid=' . zbx_dbstr($src)
        ));
        if ($src_groups) {
            $dst_groups = DBfetchArray(DBselect(
                'SELECT usrgrpid FROM users_groups WHERE userid=' . zbx_dbstr($dst)
            ));
            $dst_gids  = array_column($dst_groups, 'usrgrpid');
            $to_add    = array_filter($src_groups, fn($g) => !in_array($g['usrgrpid'], $dst_gids));

            if ($to_add) {
                $sections[] = [
                    'entity' => I18n::get()('User Groups'),
                    'count'       => count($to_add),
                    'description' => I18n::get()('group_desc'),
                    'items'       => array_column(array_values($to_add), 'name')
                ];
            }
        }

        // ── Preferencias de interface ───────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT profileid, idx FROM profiles WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Interface Preferences'),
                'count'       => count($rows),
                'description' => 'Migra as preferências de interface (filtros salvos, colunas, etc). Preferências existentes no destino são preservadas.',
                'items'       => array_unique(array_map(fn($r) => explode('.', $r['idx'])[0], $rows))
            ];
        }

        // ── Plantao — Phones ────────────────────────────────────────────────
        if ($this->tableExists('module_plantao_phones')) {
            $rows = DBfetchArray(DBselect(
                'SELECT userid, phone FROM module_plantao_phones WHERE userid=' . zbx_dbstr($src)
            ));
            if ($rows) {
                $sections[] = [
                    'entity' => 'Plantão — Telefones',
                    'count'       => count($rows),
                    'description' => I18n::get()('media_desc'),
                    'items'       => array_column($rows, 'phone')
                ];
            }
        }

        // ── Plantao — Schedule ──────────────────────────────────────────────
        if ($this->tableExists('module_plantao_schedule')) {
            $rows = DBfetchArray(DBselect(
                'SELECT scheduleid FROM module_plantao_schedule WHERE userid=' . zbx_dbstr($src)
            ));
            if ($rows) {
                $sections[] = [
                    'entity' => 'Plantão — Escalas',
                    'count'       => count($rows),
                    'description' => I18n::get()('history_desc'),
                    'items'       => array_map(fn($r) => 'Escala ID: ' . $r['scheduleid'], $rows)
                ];
            }
        }

        return $sections;
    }

    private function tableExists(string $table): bool {
        $row = DBfetch(DBselect(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES" .
            " WHERE TABLE_SCHEMA = DATABASE()" .
            " AND TABLE_NAME = " . zbx_dbstr($table)
        ));
        return $row && (int)$row['cnt'] > 0;
    }
}

<?php

namespace Modules\UserMigrate\Actions;

require_once __DIR__ . '/../locale/I18n.php';
require_once __DIR__ . '/../include/AuthResolver.php';

use Modules\UserMigrate\I18n;
use Modules\UserMigrate\AuthResolver;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;

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

        // AuthResolver devolve o usuario ja com o metodo de autenticacao real
        // resolvido (userdirectory > gui_access > default do sistema).
        $user_src = AuthResolver::fetchUser($src);
        $user_dst = AuthResolver::fetchUser($dst);

        if (!$user_src || !$user_dst) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => ['title' => I18n::get()('err_user_not_found')]
                ])
            ]));
            return;
        }

        $warnings = $this->buildWarnings($src, $user_src, $user_dst);
        $preview  = $this->buildPreview($src, $dst);

        $this->setResponse(new CControllerResponseData([
            'main_block' => json_encode([
                'success'  => true,
                'user_src' => $this->publicUser($user_src),
                'user_dst' => $this->publicUser($user_dst),
                'preview'  => $preview,
                'warnings' => $warnings,
                'total'    => array_sum(array_column($preview, 'count'))
            ])
        ]));
    }

    /** Campos do usuario expostos ao frontend (sem colunas internas de schema). */
    private function publicUser(array $user): array {
        return [
            'userid'   => $user['userid'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'surname'  => $user['surname'],
            'auth'     => AuthResolver::jsPayload($user['auth'])
        ];
    }

    /**
     * Avisos exibidos acima do preview.
     * Cada aviso e {text, level} — 'critical' pinta em vermelho na view.
     */
    private function buildWarnings(string $src, array $user_src, array $user_dst): array {
        $t        = I18n::get();
        $warnings = [];

        // Admin nativo — a execucao bloqueia, avisa antes para nao perder tempo.
        if ((int) $src === 1) {
            $warnings[] = ['text' => $t('warn_admin_native'), 'level' => 'critical'];
        }

        // Role privilegiada na origem.
        $role_row = \DBfetch(\DBselect(
            'SELECT r.name FROM users u' .
            ' JOIN role r ON r.roleid = u.roleid' .
            ' WHERE u.userid=' . \zbx_dbstr($src)
        ));

        if ($role_row && stripos($role_row['name'], 'super') !== false) {
            $warnings[] = ['text' => $t('warn_super_admin', $role_row['name']), 'level' => 'critical'];
        }

        // Provisionamento JIT: o Zabbix reescreve grupos e midias do usuario
        // provisionado a cada login. Migrar para dentro de um usuario JIT sem
        // saber disso e a forma mais comum de "a migracao sumiu no dia seguinte".
        if (!empty($user_dst['auth']['provisioned'])) {
            $warnings[] = [
                'text'  => $t('warn_dst_jit', $user_dst['auth']['provider'] ?: $user_dst['auth']['label']),
                'level' => 'critical'
            ];
        }

        if (!empty($user_src['auth']['provisioned'])) {
            $warnings[] = [
                'text'  => $t('warn_src_jit', $user_src['auth']['provider'] ?: $user_src['auth']['label']),
                'level' => 'warning'
            ];
        }

        // Origem e destino com o mesmo metodo de autenticacao: normalmente a
        // intencao e migrar LOCAL -> LDAP, entao vale confirmar.
        if ($user_src['auth']['code'] === $user_dst['auth']['code']) {
            $warnings[] = [
                'text'  => $t('warn_same_auth', $user_src['auth']['label']),
                'level' => 'warning'
            ];
        }

        // Destino sem acesso ao frontend — recebe os objetos mas nao consegue usar.
        if (!empty($user_dst['auth']['frontend_disabled'])) {
            $warnings[] = ['text' => $t('warn_dst_no_frontend'), 'level' => 'critical'];
        }

        return $warnings;
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
                'items'       => array_map(
                    fn($r) => I18n::get()('operation_id_label', $r['operationid']),
                    $rows
                )
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
        // Conta apenas as chaves que o destino ainda NAO possui — mesmo filtro
        // usado no execute, para que o total do preview bata com o resultado.
        $rows = DBfetchArray(DBselect(
            'SELECT profileid, idx FROM profiles p' .
            ' WHERE p.userid=' . zbx_dbstr($src) .
            ' AND p.idx NOT IN (SELECT idx FROM (SELECT idx FROM profiles WHERE userid=' .
            zbx_dbstr($dst) . ') AS dst_idx)'
        ));
        if ($rows) {
            $sections[] = [
                'entity' => I18n::get()('Interface Preferences'),
                'count'       => count($rows),
                'description' => I18n::get()('pref_desc'),
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
                    'entity' => I18n::get()('Plantao Phones'),
                    'count'       => count($rows),
                    'description' => I18n::get()('plantao_phones_desc'),
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
                    'entity' => I18n::get()('Plantao Schedules'),
                    'count'       => count($rows),
                    'description' => I18n::get()('plantao_schedule_desc'),
                    'items'       => array_map(
                        fn($r) => I18n::get()('schedule_id_label', $r['scheduleid']),
                        $rows
                    )
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

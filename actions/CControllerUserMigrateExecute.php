<?php

namespace Modules\UserMigrate\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CRoleHelper;
use CWebUser;

class CControllerUserMigrateExecute extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
        error_reporting(E_ALL);
        ini_set("log_errors", "1");
    }

    protected function checkInput(): bool {
        $fields = [
            'userid_src'  => 'required|db users.userid',
            'userid_dst'  => 'required|db users.userid',
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
                    'error' => ['title' => 'Usuário de origem e destino não podem ser iguais.']
                ])
            ]));
            return;
        }

        $user_src = DBfetch(DBselect(
            'SELECT u.userid, u.username, u.roleid, r.name AS rolename' .
            ' FROM users u' .
            ' LEFT JOIN role r ON r.roleid = u.roleid' .
            ' WHERE u.userid=' . zbx_dbstr($src)
        ));
        $user_dst = DBfetch(DBselect(
            'SELECT u.userid, u.username, u.roleid, r.name AS rolename' .
            ' FROM users u' .
            ' LEFT JOIN role r ON r.roleid = u.roleid' .
            ' WHERE u.userid=' . zbx_dbstr($dst)
        ));

        if (!$user_src || !$user_dst) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => ['title' => 'Usuário não encontrado.']
                ])
            ]));
            return;
        }

        // Bloqueia migração do usuário Admin nativo (userid=1)
        if ((int)$src === 1) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => [
                        'title'    => 'Operação não permitida.',
                        'messages' => ['O usuário Admin nativo (ID 1) não pode ser migrado.']
                    ]
                ])
            ]));
            return;
        }

        $migrated = [];
        $errors    = [];

        DBstart();

        try {
            // ── 1. Dashboards (ownership) ───────────────────────────────────
            $count = $this->migrateSimple('dashboard', 'userid',
                'templateid IS NULL AND userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} dashboard(s)";

            // ── 2. Dashboard (permissoes) — evita duplicatas via JOIN ────────
            DBexecute(
                'DELETE du FROM dashboard_user du' .
                ' INNER JOIN dashboard_user du2 ON du2.dashboardid = du.dashboardid' .
                '   AND du2.userid = ' . zbx_dbstr($dst) .
                ' WHERE du.userid = ' . zbx_dbstr($src)
            );
            $count = $this->migrateSimple('dashboard_user', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} permissão(ões) de dashboard";

            // ── 3. Mapas de rede (ownership) ───────────────────────────────
            $count = $this->migrateSimple('sysmaps', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} mapa(s) de rede";

            // ── 4. Mapas de rede (permissoes) — evita duplicatas via JOIN ───
            DBexecute(
                'DELETE su FROM sysmap_user su' .
                ' INNER JOIN sysmap_user su2 ON su2.sysmapid = su.sysmapid' .
                '   AND su2.userid = ' . zbx_dbstr($dst) .
                ' WHERE su.userid = ' . zbx_dbstr($src)
            );
            $count = $this->migrateSimple('sysmap_user', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} permissão(ões) de mapa";

            // ── 5. Relatorios ───────────────────────────────────────────────
            $count = $this->migrateSimple('report', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} relatório(s) agendado(s)";

            // ── 6. Relatorios (destinatarios) — evita duplicatas via JOIN ───
            DBexecute(
                'DELETE ru FROM report_user ru' .
                ' INNER JOIN report_user ru2 ON ru2.reportid = ru.reportid' .
                '   AND ru2.userid = ' . zbx_dbstr($dst) .
                ' WHERE ru.userid = ' . zbx_dbstr($src)
            );
            $count = $this->migrateSimple('report_user', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} destinatário(s) de relatório";

            // ── 7. Midias de notificacao ────────────────────────────────────
            $count = $this->migrateSimple('media', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} mídia(s) de notificação";

            // ── 8. Action operations ────────────────────────────────────────
            $count = $this->migrateSimple('opmessage_usr', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} destinatário(s) de action";

            // ── 9. API Tokens ───────────────────────────────────────────────
            $count = $this->migrateSimple('token', 'userid',
                'userid=' . zbx_dbstr($src), $dst);
            if ($count > 0) $migrated[] = "{$count} token(s) de API";

            // ── 10. Grupos — INSERT apenas dos que o destino nao tem ────────
            // id em users_groups NAO e auto_increment — gera manualmente
            $src_groups = DBfetchArray(DBselect(
                'SELECT ug.id, ug.usrgrpid, g.name' .
                ' FROM users_groups ug' .
                ' JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid' .
                ' WHERE ug.userid=' . zbx_dbstr($src)
            ));
            $dst_groups = DBfetchArray(DBselect(
                'SELECT usrgrpid FROM users_groups WHERE userid=' . zbx_dbstr($dst)
            ));
            $dst_gids = array_column($dst_groups, 'usrgrpid');
            $added_groups = 0;

            foreach ($src_groups as $g) {
                if (!in_array($g['usrgrpid'], $dst_gids)) {
                    // Gera novo id seguro para users_groups
                    $max_row = DBfetch(DBselect('SELECT MAX(id) AS maxid FROM users_groups'));
                    $new_id  = (int)($max_row['maxid'] ?? 0) + 1;

                    DBexecute(
                        'INSERT INTO users_groups (id, userid, usrgrpid)' .
                        ' VALUES (' . zbx_dbstr($new_id) . ',' .
                        zbx_dbstr($dst) . ',' . zbx_dbstr($g['usrgrpid']) . ')'
                    );
                    $added_groups++;
                }
            }
            if ($added_groups > 0) $migrated[] = "{$added_groups} grupo(s) de usuário";

            // ── 11. Preferencias de interface — UPDATE em batch
            DBexecute(
                'UPDATE profiles p SET p.userid=' . zbx_dbstr($dst) .
                ' WHERE p.userid=' . zbx_dbstr($src) .
                ' AND p.idx NOT IN (SELECT idx FROM (SELECT idx FROM profiles WHERE userid=' . zbx_dbstr($dst) . ') AS dst_idx)'
            );
            $migrated[] = "preferencias de interface";

            // ── 12. Plantao — Phones ────────────────────────────────────────
            if ($this->tableExists('module_plantao_phones')) {
                // userid e PK em module_plantao_phones — usa INSERT/DELETE
                // para evitar duplicate key se destino ja tiver registro
                $phones = DBfetchArray(DBselect(
                    'SELECT phone FROM module_plantao_phones WHERE userid=' . zbx_dbstr($src)
                ));
                $dst_phone = DBfetch(DBselect(
                    'SELECT userid FROM module_plantao_phones WHERE userid=' . zbx_dbstr($dst)
                ));
                if ($phones && !$dst_phone) {
                    DBexecute(
                        'UPDATE module_plantao_phones SET userid=' . zbx_dbstr($dst) .
                        ' WHERE userid=' . zbx_dbstr($src)
                    );
                    $migrated[] = count($phones) . ' telefone(s) de plantão';
                } elseif ($phones && $dst_phone) {
                    // Destino ja tem registro — apenas remove o origem
                    DBexecute('DELETE FROM module_plantao_phones WHERE userid=' . zbx_dbstr($src));
                }
            }

            // ── 13. Plantao — Schedule ──────────────────────────────────────
            if ($this->tableExists('module_plantao_schedule')) {
                $count = $this->migrateSimple('module_plantao_schedule', 'userid',
                    'userid=' . zbx_dbstr($src), $dst);
                if ($count > 0) $migrated[] = "{$count} escala(s) de plantão";
            }

            // ── Auditoria no formato nativo do Zabbix ───────────────────────
            $this->writeAuditLog($src, $dst, $user_src, $user_dst, $migrated);

            DBend(true);

        } catch (\Exception $e) {
            DBend(false);
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => [
                        'title'    => 'Migração falhou — nenhuma alteração foi salva.',
                        'messages' => [
                            $e->getMessage(),
                            'Todas as alterações foram revertidas automaticamente (rollback).',
                            'Verifique os logs do servidor para mais detalhes.'
                        ]
                    ]
                ])
            ]));
            return;
        }

        if (empty($migrated)) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'success' => [
                        'title'    => "Migração concluída: {$user_src['username']} → {$user_dst['username']}",
                        'messages' => ['Nenhum objeto encontrado para migrar no usuário de origem.']
                    ]
                ])
            ]));
            return;
        }

        // Monta resumo detalhado por categoria
        $summary_lines = $migrated;
        $summary_lines[] = 'Total: ' . array_sum(array_map(fn($m) => (int)preg_replace('/[^0-9]/', '', $m), $migrated)) . ' objeto(s) transferido(s).';

        $this->setResponse(new CControllerResponseData([
            'main_block' => json_encode([
                'success' => [
                    'title'    => "✔ Migração concluída com sucesso: {$user_src['username']} → {$user_dst['username']}",
                    'messages' => $summary_lines
                ]
            ])
        ]));
    }

    /**
     * UPDATE simples de userid em uma tabela.
     * Retorna numero de linhas afetadas via SELECT COUNT antes do UPDATE.
     */
    private function migrateSimple(string $table, string $field, string $where, string $dst): int {
        // Conta antes para nao depender de ROW_COUNT() (MariaDB-only)
        $count_row = DBfetch(DBselect("SELECT COUNT(*) AS cnt FROM {$table} WHERE {$where}"));
        $count = (int)($count_row['cnt'] ?? 0);

        if ($count > 0) {
            DBexecute("UPDATE {$table} SET {$field}=" . zbx_dbstr($dst) . " WHERE {$where}");
        }

        return $count;
    }

    /**
     * Verifica se uma tabela existe no banco.
     */
    private function tableExists(string $table): bool {
        // Compativel com MySQL e MariaDB
        // TABLE_SCHEMA dinamico via SELECT DATABASE() para nao hardcodar 'zabbix'
        $row = DBfetch(DBselect(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES" .
            " WHERE TABLE_SCHEMA = DATABASE()" .
            " AND TABLE_NAME = " . zbx_dbstr($table)
        ));
        return $row && (int)$row['cnt'] > 0;
    }

    /**
     * Registra a migracao no auditlog nativo do Zabbix.
     *
     * Formato do auditlog no Zabbix 7.0:
     *   auditid      = varchar(25) — ID unico (timestamp + random)
     *   userid       = ID do admin que executou
     *   username     = username do admin
     *   clock        = unix timestamp
     *   ip           = IP do cliente
     *   action       = 2 (AUDIT_ACTION_UPDATE)
     *   resourcetype = 11 (AUDIT_RESOURCE_USER)
     *   resourceid   = userid do usuario de origem
     *   resourcename = username do usuario de origem
     *   recordsetid  = mesmo auditid
     *   details      = descricao da operacao
     */
    private function writeAuditLog(
        string $src,
        string $dst,
        array $user_src,
        array $user_dst,
        array $migrated
    ): void {
        $auditid    = uniqid('', true);
        $now        = time();
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
        $admin_id   = CWebUser::$data['userid'];
        $admin_name = CWebUser::$data['username'];

        $summary = empty($migrated)
            ? 'Nenhum objeto migrado.'
            : implode(', ', $migrated);

        $details = sprintf(
            'User migration executed by %s. Source: %s (ID %s) -> Destination: %s (ID %s). Objects: %s',
            $admin_name,
            $user_src['username'], $src,
            $user_dst['username'], $dst,
            $summary
        );

        DBexecute(
            'INSERT INTO auditlog' .
            ' (auditid, userid, username, clock, ip, action, resourcetype,' .
            '  resourceid, resource_cuid, resourcename, recordsetid, details)' .
            ' VALUES (' .
            zbx_dbstr($auditid) . ',' .
            zbx_dbstr($admin_id) . ',' .
            zbx_dbstr($admin_name) . ',' .
            zbx_dbstr($now) . ',' .
            zbx_dbstr($ip) . ',' .
            '2,' .   // AUDIT_ACTION_UPDATE
            '11,' .  // AUDIT_RESOURCE_USER
            zbx_dbstr($src) . ',' .
            zbx_dbstr('') . ',' .
            zbx_dbstr($user_src['username']) . ',' .
            zbx_dbstr($auditid) . ',' .
            zbx_dbstr($details) .
            ')'
        );
    }
}

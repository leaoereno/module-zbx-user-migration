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
        return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
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

        // Valida existência de ambos os usuários
        $user_src = DBfetch(DBselect(
            'SELECT userid, username FROM users WHERE userid=' . zbx_dbstr($src)
        ));
        $user_dst = DBfetch(DBselect(
            'SELECT userid, username FROM users WHERE userid=' . zbx_dbstr($dst)
        ));

        if (!$user_src || !$user_dst) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => ['title' => 'Usuário não encontrado.']
                ])
            ]));
            return;
        }

        $migrated = [];
        $errors    = [];

        DBbegin();

        try {
            // ── 1. Dashboards (ownership) ───────────────────────────────────
            $count = $this->migrateSimple('dashboard', 'userid', 'templateid IS NULL', $src, $dst);
            if ($count > 0) $migrated[] = "{$count} dashboard(s)";

            // ── 2. Dashboard (permissões) ───────────────────────────────────
            // Evita duplicatas: remove permissão do src em dashboards onde dst já tem acesso
            DBexecute(
                'DELETE FROM dashboard_user' .
                ' WHERE userid=' . zbx_dbstr($src) .
                ' AND dashboardid IN (' .
                '   SELECT dashboardid FROM dashboard_user WHERE userid=' . zbx_dbstr($dst) .
                ' )'
            );
            $count = $this->migrateSimple('dashboard_user', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} permissão(ões) de dashboard";

            // ── 3. Mapas de rede (ownership) ───────────────────────────────
            $count = $this->migrateSimple('sysmaps', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} mapa(s) de rede";

            // ── 4. Mapas de rede (permissões) ──────────────────────────────
            DBexecute(
                'DELETE FROM sysmap_user' .
                ' WHERE userid=' . zbx_dbstr($src) .
                ' AND sysmapid IN (' .
                '   SELECT sysmapid FROM sysmap_user WHERE userid=' . zbx_dbstr($dst) .
                ' )'
            );
            $count = $this->migrateSimple('sysmap_user', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} permissão(ões) de mapa";

            // ── 5. Relatórios ───────────────────────────────────────────────
            $count = $this->migrateSimple('report', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} relatório(s) agendado(s)";

            // ── 6. Relatórios (destinatários) ───────────────────────────────
            DBexecute(
                'DELETE FROM report_user' .
                ' WHERE userid=' . zbx_dbstr($src) .
                ' AND reportid IN (' .
                '   SELECT reportid FROM report_user WHERE userid=' . zbx_dbstr($dst) .
                ' )'
            );
            $count = $this->migrateSimple('report_user', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} destinatário(s) de relatório";

            // ── 7. Mídias de notificação ────────────────────────────────────
            $count = $this->migrateSimple('media', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} mídia(s) de notificação";

            // ── 8. Action operations ────────────────────────────────────────
            $count = $this->migrateSimple('opmessage_usr', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} destinatário(s) de action";

            // ── 9. API Tokens ───────────────────────────────────────────────
            $count = $this->migrateSimple('token', 'userid', null, $src, $dst);
            if ($count > 0) $migrated[] = "{$count} token(s) de API";

            // ── 10. Grupos — INSERT dos que o destino ainda não tem ─────────
            $src_groups = DBfetchArray(DBselect(
                'SELECT usrgrpid FROM users_groups WHERE userid=' . zbx_dbstr($src)
            ));
            $dst_groups = DBfetchArray(DBselect(
                'SELECT usrgrpid FROM users_groups WHERE userid=' . zbx_dbstr($dst)
            ));
            $dst_gids = array_column($dst_groups, 'usrgrpid');
            $added_groups = 0;

            foreach ($src_groups as $g) {
                if (!in_array($g['usrgrpid'], $dst_gids)) {
                    DBexecute(
                        'INSERT INTO users_groups (userid, usrgrpid)' .
                        ' VALUES (' . zbx_dbstr($dst) . ',' . zbx_dbstr($g['usrgrpid']) . ')'
                    );
                    $added_groups++;
                }
            }
            if ($added_groups > 0) $migrated[] = "{$added_groups} grupo(s) de usuário";

            // ── 11. Preferências de interface (apenas as que o dst não tem) ─
            $dst_profiles = DBfetchArray(DBselect(
                'SELECT idx FROM profiles WHERE userid=' . zbx_dbstr($dst)
            ));
            $dst_idxs = array_column($dst_profiles, 'idx');

            $src_profiles = DBfetchArray(DBselect(
                'SELECT * FROM profiles WHERE userid=' . zbx_dbstr($src)
            ));
            $added_profiles = 0;

            foreach ($src_profiles as $p) {
                if (!in_array($p['idx'], $dst_idxs)) {
                    DBexecute(
                        'UPDATE profiles SET userid=' . zbx_dbstr($dst) .
                        ' WHERE profileid=' . zbx_dbstr($p['profileid'])
                    );
                    $added_profiles++;
                }
            }
            if ($added_profiles > 0) $migrated[] = "{$added_profiles} preferência(s) de interface";

            // ── 12. Plantão — Phones ────────────────────────────────────────
            if ($this->tableExists('module_plantao_phones')) {
                $count = $this->migrateSimple('module_plantao_phones', 'userid', null, $src, $dst);
                if ($count > 0) $migrated[] = "{$count} telefone(s) de plantão";
            }

            // ── 13. Plantão — Schedule ──────────────────────────────────────
            if ($this->tableExists('module_plantao_schedule')) {
                $count = $this->migrateSimple('module_plantao_schedule', 'userid', null, $src, $dst);
                if ($count > 0) $migrated[] = "{$count} escala(s) de plantão";
            }

            DBcommit();

        } catch (\Exception $e) {
            DBrollback();
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode([
                    'error' => [
                        'title'    => 'Erro durante a migração. Nenhuma alteração foi salva.',
                        'messages' => [$e->getMessage()]
                    ]
                ])
            ]));
            return;
        }

        $summary = empty($migrated)
            ? 'Nenhum objeto encontrado para migrar.'
            : implode(', ', $migrated) . ' migrado(s) com sucesso.';

        $this->setResponse(new CControllerResponseData([
            'main_block' => json_encode([
                'success' => [
                    'title'    => "Migração concluída: {$user_src['username']} → {$user_dst['username']}",
                    'messages' => [$summary]
                ]
            ])
        ]));
    }

    /**
     * Executa UPDATE simples de userid em uma tabela.
     * Retorna o número de linhas afetadas.
     */
    private function migrateSimple(
        string $table,
        string $field,
        ?string $extra_where,
        string $src,
        string $dst
    ): int {
        $where = "{$field}=" . zbx_dbstr($src);
        if ($extra_where) {
            $where .= ' AND ' . $extra_where;
        }

        DBexecute("UPDATE {$table} SET {$field}=" . zbx_dbstr($dst) . " WHERE {$where}");

        $result = DBfetch(DBselect("SELECT ROW_COUNT() as cnt"));
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Verifica se uma tabela existe no banco zabbix.
     */
    private function tableExists(string $table): bool {
        $row = DBfetch(DBselect(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES" .
            " WHERE TABLE_SCHEMA='zabbix' AND TABLE_NAME=" . zbx_dbstr($table)
        ));
        return $row && (int)$row['cnt'] > 0;
    }
}

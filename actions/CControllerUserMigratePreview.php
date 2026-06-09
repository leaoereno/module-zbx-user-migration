<?php

namespace Modules\UserMigrate\Actions;

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
                    'error' => ['title' => 'Usuário de origem e destino não podem ser iguais.']
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
                    'error' => ['title' => 'Usuário não encontrado.']
                ])
            ]));
            return;
        }

        // Aviso extra se usuario de origem for Admin nativo
        $warnings = [];
        if ((int)$src === 1) {
            $warnings[] = 'ATENÇÃO: O usuário de origem é o Admin nativo (ID 1). A migração será bloqueada na execução.';
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
                'entity'      => 'Dashboards',
                'count'       => count($rows),
                'description' => 'Transfere a propriedade do dashboard para o usuário destino.',
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
                'entity'      => 'Permissões de Dashboard',
                'count'       => count($rows),
                'description' => 'Reatribui as permissões de acesso a dashboards compartilhados.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Mapas de rede (ownership) ───────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT sysmapid, name FROM sysmaps WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Mapas de Rede',
                'count'       => count($rows),
                'description' => 'Transfere a propriedade dos mapas de rede.',
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
                'entity'      => 'Permissões de Mapa',
                'count'       => count($rows),
                'description' => 'Reatribui as permissões de acesso a mapas compartilhados.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Relatorios agendados ────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT reportid, name FROM report WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Relatórios Agendados',
                'count'       => count($rows),
                'description' => 'Transfere a propriedade dos relatórios agendados.',
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
                'entity'      => 'Destinatários de Relatório',
                'count'       => count($rows),
                'description' => 'Reatribui o usuário como destinatário dos relatórios.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Midias de notificacao ───────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT mediaid, sendto FROM media WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Mídias de Notificação',
                'count'       => count($rows),
                'description' => 'Transfere as configurações de mídia (e-mail, SMS, etc).',
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
                'entity'      => 'Destinatários de Action (Trigger)',
                'count'       => count($rows),
                'description' => 'Substitui o usuário como destinatário em operações de Actions.',
                'items'       => array_map(fn($r) => 'Operation ID: ' . $r['operationid'], $rows)
            ];
        }

        // ── API Tokens ──────────────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT tokenid, name FROM token WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'API Tokens',
                'count'       => count($rows),
                'description' => 'Transfere os tokens de API para o usuário destino.',
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
                    'entity'      => 'Grupos de Usuário',
                    'count'       => count($to_add),
                    'description' => 'Adiciona o usuário destino nos mesmos grupos do usuário origem (grupos já existentes são ignorados).',
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
                'entity'      => 'Preferências de Interface',
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
                    'entity'      => 'Plantão — Telefones',
                    'count'       => count($rows),
                    'description' => 'Transfere os registros de telefone do módulo de plantão.',
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
                    'entity'      => 'Plantão — Escalas',
                    'count'       => count($rows),
                    'description' => 'Transfere as escalas de plantão vinculadas ao usuário.',
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

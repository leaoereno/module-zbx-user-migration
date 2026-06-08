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

        $preview = $this->buildPreview($src, $dst);

        $this->setResponse(new CControllerResponseData([
            'main_block' => json_encode([
                'success' => true,
                'user_src' => $user_src,
                'user_dst' => $user_dst,
                'preview'  => $preview,
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
                'table'       => 'dashboard',
                'field'       => 'userid',
                'pk'          => 'dashboardid',
                'count'       => count($rows),
                'description' => 'Transfere a propriedade do dashboard para o usuário destino.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Dashboards (permissões compartilhadas) ──────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT du.dashboard_userid, d.name FROM dashboard_user du' .
            ' JOIN dashboard d ON d.dashboardid=du.dashboardid' .
            ' WHERE du.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Permissões de Dashboard',
                'table'       => 'dashboard_user',
                'field'       => 'userid',
                'pk'          => 'dashboard_userid',
                'count'       => count($rows),
                'description' => 'Reatribui as permissões de acesso a dashboards compartilhados.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Mapas de rede (ownership) ───────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT sysmapid, name FROM sysmaps' .
            ' WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Mapas de Rede',
                'table'       => 'sysmaps',
                'field'       => 'userid',
                'pk'          => 'sysmapid',
                'count'       => count($rows),
                'description' => 'Transfere a propriedade dos mapas de rede.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Mapas de rede (permissões compartilhadas) ───────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT su.sysmap_userid, s.name FROM sysmap_user su' .
            ' JOIN sysmaps s ON s.sysmapid=su.sysmapid' .
            ' WHERE su.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Permissões de Mapa',
                'table'       => 'sysmap_user',
                'field'       => 'userid',
                'pk'          => 'sysmap_userid',
                'count'       => count($rows),
                'description' => 'Reatribui as permissões de acesso a mapas compartilhados.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Relatórios agendados ────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT reportid, name FROM report' .
            ' WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Relatórios Agendados',
                'table'       => 'report',
                'field'       => 'userid',
                'pk'          => 'reportid',
                'count'       => count($rows),
                'description' => 'Transfere a propriedade dos relatórios agendados.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Relatórios (destinatários) ──────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT ru.report_userid, r.name FROM report_user ru' .
            ' JOIN report r ON r.reportid=ru.reportid' .
            ' WHERE ru.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Destinatários de Relatório',
                'table'       => 'report_user',
                'field'       => 'userid',
                'pk'          => 'report_userid',
                'count'       => count($rows),
                'description' => 'Reatribui o usuário como destinatário dos relatórios.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Mídias de notificação ───────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT mediaid, sendto FROM media' .
            ' WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Mídias de Notificação',
                'table'       => 'media',
                'field'       => 'userid',
                'pk'          => 'mediaid',
                'count'       => count($rows),
                'description' => 'Transfere as configurações de mídia (e-mail, SMS, etc).',
                'items'       => array_column($rows, 'sendto')
            ];
        }

        // ── Action operations (destinatário de mensagem) ────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT o.opmessage_usrid, op.operationid FROM opmessage_usr o' .
            ' JOIN operations op ON op.operationid=o.operationid' .
            ' WHERE o.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Destinatários de Action (Trigger)',
                'table'       => 'opmessage_usr',
                'field'       => 'userid',
                'pk'          => 'opmessage_usrid',
                'count'       => count($rows),
                'description' => 'Substitui o usuário como destinatário em operações de Actions.',
                'items'       => array_map(fn($r) => 'Operation ID: ' . $r['operationid'], $rows)
            ];
        }

        // ── API Tokens ──────────────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT tokenid, name FROM token' .
            ' WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'API Tokens',
                'table'       => 'token',
                'field'       => 'userid',
                'pk'          => 'tokenid',
                'count'       => count($rows),
                'description' => 'Transfere os tokens de API para o usuário destino.',
                'items'       => array_column($rows, 'name')
            ];
        }

        // ── Grupos de usuário ───────────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT ug.id, g.name FROM users_groups ug' .
            ' JOIN usrgrp g ON g.usrgrpid=ug.usrgrpid' .
            ' WHERE ug.userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            // Filtra grupos que o destino já tem
            $dst_groups = DBfetchArray(DBselect(
                'SELECT usrgrpid FROM users_groups WHERE userid=' . zbx_dbstr($dst)
            ));
            $dst_group_ids = array_column($dst_groups, 'usrgrpid');

            $to_add = array_filter($rows, fn($r) => !in_array($r['usrgrpid'] ?? '', $dst_group_ids));

            if ($to_add) {
                $sections[] = [
                    'entity'      => 'Grupos de Usuário',
                    'table'       => 'users_groups',
                    'field'       => 'userid',
                    'pk'          => 'id',
                    'count'       => count($to_add),
                    'description' => 'Adiciona o usuário destino nos mesmos grupos do usuário origem (grupos já existentes são ignorados).',
                    'items'       => array_column(array_values($to_add), 'name')
                ];
            }
        }

        // ── Perfil / Preferências ───────────────────────────────────────────
        $rows = DBfetchArray(DBselect(
            'SELECT profileid, idx FROM profiles' .
            ' WHERE userid=' . zbx_dbstr($src)
        ));
        if ($rows) {
            $sections[] = [
                'entity'      => 'Preferências de Interface',
                'table'       => 'profiles',
                'field'       => 'userid',
                'pk'          => 'profileid',
                'count'       => count($rows),
                'description' => 'Migra as preferências de interface (filtros salvos, colunas, etc). Preferências existentes no destino serão mantidas.',
                'items'       => array_unique(array_map(fn($r) => explode('.', $r['idx'])[0], $rows))
            ];
        }

        // ── Módulo Plantão — Phones ─────────────────────────────────────────
        $has_plantao = DBfetch(DBselect(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES" .
            " WHERE TABLE_SCHEMA='zabbix' AND TABLE_NAME='module_plantao_phones'"
        ));
        if ($has_plantao && (int)$has_plantao['cnt'] > 0) {
            $rows = DBfetchArray(DBselect(
                'SELECT id, phone FROM module_plantao_phones' .
                ' WHERE userid=' . zbx_dbstr($src)
            ));
            if ($rows) {
                $sections[] = [
                    'entity'      => 'Plantão — Telefones',
                    'table'       => 'module_plantao_phones',
                    'field'       => 'userid',
                    'pk'          => 'id',
                    'count'       => count($rows),
                    'description' => 'Transfere os registros de telefone do módulo de plantão.',
                    'items'       => array_column($rows, 'phone')
                ];
            }
        }

        // ── Módulo Plantão — Schedule ───────────────────────────────────────
        $has_schedule = DBfetch(DBselect(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES" .
            " WHERE TABLE_SCHEMA='zabbix' AND TABLE_NAME='module_plantao_schedule'"
        ));
        if ($has_schedule && (int)$has_schedule['cnt'] > 0) {
            $rows = DBfetchArray(DBselect(
                'SELECT id FROM module_plantao_schedule' .
                ' WHERE userid=' . zbx_dbstr($src)
            ));
            if ($rows) {
                $sections[] = [
                    'entity'      => 'Plantão — Escalas',
                    'table'       => 'module_plantao_schedule',
                    'field'       => 'userid',
                    'pk'          => 'id',
                    'count'       => count($rows),
                    'description' => 'Transfere as escalas de plantão vinculadas ao usuário.',
                    'items'       => array_map(fn($r) => 'Escala ID: ' . $r['id'], $rows)
                ];
            }
        }

        return $sections;
    }
}

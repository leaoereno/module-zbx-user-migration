<?php

namespace Modules\UserMigrate;

require_once __DIR__ . '/locale/I18n.php';

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CView;
use CWebUser;

class Module extends CModule {

    public function init(): void {
        // Guard umbrella Usuarios
        if (defined('ZBX_USUARIOS_ACTIVE') && ZBX_USUARIOS_ACTIVE === true) { return; }
        CView::registerDirectory(__DIR__ . '/views');

        // Exibe o menu apenas para Admin (type=2) e Super Admin (type=3).
        // Usuários com perfil User (type=1) não devem ver estas opções.
        $userType = (int) CWebUser::getType();

        if (!in_array($userType, [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
            return;
        }

        // Traducao via I18n proprio do modulo (nao usar a _() nativa do Zabbix:
        // ela so resolve strings que existem no catalogo .po do core, entao as
        // labels deste modulo nunca seriam traduzidas por ela).
        $t = I18n::get();

        $menu = APP::Component()->get('menu.main');

        $migrationItem = (new CMenuItem($t('User Migration')))
            ->setAction('usermigrate.view');

        $reportItem = (new CMenuItem($t('User Objects Report')))
            ->setAction('usermigrate.report');

        // Agrupa as duas entradas sob um unico item "Assistente de migração de usuário"
        // (nome do modulo), em vez de duas entradas soltas direto em "Usuários".
        $assistantMenuItem = new CMenuItem($t('User Migration Assistant'));
        $assistantMenuItem->getSubMenu()
            ->add($migrationItem)
            ->add($reportItem);

        // O Zabbix registra o menu "Usuários" com setId('users-menu') e setIcon(ZBX_ICON_USERS)
        // dentro de CMenuHelper. Buscar por esse id é o único jeito confiável de encontrar
        // o item nativo (com ícone) para ambos os perfis Admin e Super Admin.
        $usersMenuItem = null;

        foreach ($menu->getMenuItems() as $item) {
            if ($item->getId() === 'users-menu') {
                $usersMenuItem = $item;
                break;
            }
        }

        if ($usersMenuItem !== null) {
            // Encontrou o item nativo — preserva ícone ZBX_ICON_USERS já definido
            $usersMenuItem->getSubMenu()
                ->add($assistantMenuItem);
        }
        else {
            // Fallback: cria o item com ícone explícito
            // (não deve ocorrer em instalação Zabbix 7.0 padrão)
            // "Users" aqui é o label nativo do Zabbix (catalogo .po do core),
            // por isso usa a _() nativa e não o $t() deste módulo.
            $menu->findOrAdd(_('Users'))
                ->setIcon(ZBX_ICON_USERS)
                ->getSubMenu()
                ->add($assistantMenuItem);
        }
    }
}

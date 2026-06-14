<?php

namespace Modules\UserMigrate;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CView;
use CWebUser;

class Module extends CModule {

    public function init(): void {
        CView::registerDirectory(__DIR__ . '/views');

        // Exibe o menu apenas para Admin (type=2) e Super Admin (type=3).
        // Usuários com perfil User (type=1) não devem ver estas opções.
        $userType = (int) CWebUser::getType();

        if (!in_array($userType, [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
            return;
        }

        $menu = APP::Component()->get('menu.main');

        $migrationItem = (new CMenuItem(_('User Migration')))
            ->setAction('usermigrate.view');

        $reportItem = (new CMenuItem(_('User Objects Report')))
            ->setAction('usermigrate.report');

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
                ->add($migrationItem)
                ->add($reportItem);
        }
        else {
            // Fallback: cria o item com ícone explícito
            // (não deve ocorrer em instalação Zabbix 7.0 padrão)
            $menu->findOrAdd(_('Users'))
                ->setIcon(ZBX_ICON_USERS)
                ->getSubMenu()
                ->add($migrationItem)
                ->add($reportItem);
        }
    }
}

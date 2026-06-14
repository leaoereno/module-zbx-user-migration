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

        if ($userType === USER_TYPE_SUPER_ADMIN) {
            // Super Admin: menu "Usuários" fica dentro de "Administration"
            // Busca pelo label (funciona em PT-BR e EN porque o Zabbix registra com _())
            foreach ($menu->getMenuItems() as $item) {
                if ($item->getLabel() === _('Administration') || $item->getLabel() === 'Administration') {
                    $item->getSubMenu()
                        ->add($migrationItem)
                        ->add($reportItem);
                    return;
                }
            }
            // Fallback: Administration não encontrado, adiciona direto no root
            $menu->findOrAdd(_('Administration'))
                ->getSubMenu()
                ->add($migrationItem)
                ->add($reportItem);
        }
        else {
            // Admin (type=2): menu "Usuários" existe nativamente sem Administration
            // findOrAdd encontra o item existente com ícone correto
            $menu->findOrAdd(_('Users'))
                ->getSubMenu()
                ->add($migrationItem)
                ->add($reportItem);
        }
    }
}

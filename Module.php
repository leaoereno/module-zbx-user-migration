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
        $userType = (int) CWebUser::$data['type'];

        if (!in_array($userType, [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
            return;
        }

        $mainMenu = APP::Component()->get('menu.main');

        // Busca o item de menu nativo "Usuários" iterando pelos itens do menu principal.
        // Usar findOrAdd(_('Users')) direto causa bug para perfil Admin: o Zabbix registra
        // "Usuários" de forma diferente por perfil, e a busca por label falha — criando
        // um item duplicado sem ícone. A busca por action 'user.list' é estável e
        // independente do idioma configurado.
        $usersMenuItem = null;

        foreach ($mainMenu->getItems() as $item) {
            if ($item->getAction() === 'user.list') {
                $usersMenuItem = $item;
                break;
            }
            // "Usuários" é um item pai (sem action própria), busca dentro dos submenus
            $submenu = $item->getSubmenu();
            if ($submenu !== null) {
                foreach ($submenu->getItems() as $subItem) {
                    if ($subItem->getAction() === 'user.list') {
                        $usersMenuItem = $item; // retorna o pai (o item "Usuários")
                        break 2;
                    }
                }
            }
        }

        if ($usersMenuItem !== null) {
            // Menu nativo encontrado — preserva ícone e estrutura existentes
            $usersMenuItem->getSubmenu()
                ->add(
                    (new CMenuItem(_('User Migration')))
                        ->setAction('usermigrate.view')
                )
                ->add(
                    (new CMenuItem(_('User Objects Report')))
                        ->setAction('usermigrate.report')
                );
        }
        else {
            // Fallback: cria entrada própria (não deve ocorrer em instalação padrão)
            $mainMenu
                ->findOrAdd(_('Users'))
                ->getSubmenu()
                ->add(
                    (new CMenuItem(_('User Migration')))
                        ->setAction('usermigrate.view')
                )
                ->add(
                    (new CMenuItem(_('User Objects Report')))
                        ->setAction('usermigrate.report')
                );
        }
    }
}

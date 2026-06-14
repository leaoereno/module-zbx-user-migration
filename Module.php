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

        APP::Component()->get('menu.main')
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

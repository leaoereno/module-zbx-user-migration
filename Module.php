<?php

namespace Modules\UserMigrate;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CView;

class Module extends CModule {

    public function init(): void {
        CView::registerDirectory(__DIR__ . '/views');

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

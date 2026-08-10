<?php

namespace Modules\UserMigrate\Actions;

require_once __DIR__ . '/../locale/I18n.php';
require_once __DIR__ . '/../include/AuthResolver.php';

use Modules\UserMigrate\AuthResolver;

use CController;
use CControllerResponseData;

class CControllerUserMigrateView extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return in_array(\CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]);
    }

    protected function doAction(): void {
        // A resolucao do metodo de autenticacao (LOCAL / LDAP / SAML) fica toda
        // em AuthResolver — ver o cabecalho daquele arquivo para a ordem de
        // precedencia (userdirectory > gui_access > default do sistema).
        $users = AuthResolver::fetchUsers();

        $this->setResponse(new CControllerResponseData([
            'users'     => $users,
            'user_data' => \CWebUser::$data
        ]));
    }
}

<?php

namespace Modules\UserMigrate\Actions;

use CController;
use CControllerResponseData;
use CRoleHelper;

class CControllerUserMigrateView extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
    }

    protected function doAction(): void {
        // Busca todos os usuários para popular os selects
        $users = DBfetchArray(DBselect(
            'SELECT userid, username, name, surname, gui_access' .
            ' FROM users' .
            ' ORDER BY username ASC'
        ));

        $this->setResponse(new CControllerResponseData([
            'users'      => $users,
            'user_data'  => \CWebUser::$data
        ]));
    }
}

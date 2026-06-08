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
        // gui_access em usrgrp:
        //   0 = default do sistema
        //   1 = internal (local)
        //   2 = LDAP
        //   3 = disabled
        //
        // Se o usuario pertence a grupos com gui_access misto,
        // usamos o MAX para determinar o metodo predominante.
        // Usuarios sem grupo ficam com gui_access = 0 (default do sistema).
        $users = DBfetchArray(DBselect(
            'SELECT u.userid, u.username, u.name, u.surname,' .
            ' COALESCE(MAX(g.gui_access), 0) AS gui_access' .
            ' FROM users u' .
            ' LEFT JOIN users_groups ug ON ug.userid = u.userid' .
            ' LEFT JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid' .
            ' GROUP BY u.userid, u.username, u.name, u.surname' .
            ' ORDER BY u.username ASC'
        ));

        $this->setResponse(new CControllerResponseData([
            'users'     => $users,
            'user_data' => \CWebUser::$data
        ]));
    }
}

<?php
/**
 * AuthResolver — resolve o metodo de autenticacao REAL de um usuario Zabbix.
 *
 * Motivacao
 * ---------
 * A versao anterior do modulo usava apenas MAX(usrgrp.gui_access), o que produz
 * a etiqueta "SYSTEM" para a maioria dos usuarios de uma instalacao real — porque
 * gui_access = 0 significa literalmente "use o default do sistema", e nao
 * "autenticacao interna". Um usuario LDAP herdando o default aparecia como SYSTEM
 * (ou pior, como LOCAL), levando o operador a migrar o par errado.
 *
 * Ordem de resolucao (Zabbix 6.4 / 7.0)
 * -------------------------------------
 *   1. users.userdirectoryid > 0
 *        -> userdirectory.idp_type  1 = LDAP, 2 = SAML
 *        -> userdirectory.name      nome do provedor (ex: "AD-Embratel")
 *        -> userdirectory.provision_status  1 = provisionamento JIT ativo
 *   2. MAX(usrgrp.gui_access) entre os grupos do usuario
 *        -> 2 = LDAP, 1 = Internal (LOCAL)
 *   3. gui_access = 0 (System default)
 *        -> config.authentication_type  0 = Internal (LOCAL), 1 = LDAP
 *
 * gui_access = 3 (Disabled) NAO e um metodo de autenticacao — e bloqueio de
 * acesso ao frontend. Por isso e devolvido em uma flag separada, para nao
 * mascarar o metodo real como a versao anterior fazia.
 *
 * Tolerancia a schema
 * -------------------
 * users.userdirectoryid e a tabela userdirectory so existem a partir do Zabbix
 * 6.4. Todas as capacidades sao detectadas em runtime e cacheadas; em schema
 * antigo o resolvedor degrada para o caminho gui_access + config sem quebrar.
 *
 * @author Rafael M. A. Leao Ereno
 */

namespace Modules\UserMigrate;

require_once __DIR__ . '/../locale/I18n.php';

class AuthResolver {

    public const LOCAL    = 'local';
    public const LDAP     = 'ldap';
    public const SAML     = 'saml';
    public const UNKNOWN  = 'unknown';

    /** Cache de capacidades do schema (uma deteccao por request). */
    private static ?array $caps = null;

    /** Cache do default de autenticacao do sistema. */
    private static ?array $system_default = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Deteccao de schema
    // ─────────────────────────────────────────────────────────────────────────

    private static function caps(): array {
        if (self::$caps === null) {
            self::$caps = [
                'userdirectory'  => self::tableExists('userdirectory')
                                    && self::columnExists('users', 'userdirectoryid'),
                'idp_type'       => self::columnExists('userdirectory', 'idp_type'),
                'provision'      => self::columnExists('userdirectory', 'provision_status'),
                'auth_type'      => self::columnExists('config', 'authentication_type'),
                'ldap_directory' => self::columnExists('config', 'ldap_userdirectoryid'),
            ];
        }

        return self::$caps;
    }

    private static function tableExists(string $table): bool {
        $row = \DBfetch(\DBselect(
            'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES' .
            ' WHERE TABLE_SCHEMA = DATABASE()' .
            ' AND TABLE_NAME = ' . \zbx_dbstr($table)
        ));

        return $row && (int) $row['cnt'] > 0;
    }

    private static function columnExists(string $table, string $column): bool {
        $row = \DBfetch(\DBselect(
            'SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS' .
            ' WHERE TABLE_SCHEMA = DATABASE()' .
            ' AND TABLE_NAME = ' . \zbx_dbstr($table) .
            ' AND COLUMN_NAME = ' . \zbx_dbstr($column)
        ));

        return $row && (int) $row['cnt'] > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Default de autenticacao do sistema (config.authentication_type)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{code:string, provider:string}
     */
    private static function systemDefault(): array {
        if (self::$system_default !== null) {
            return self::$system_default;
        }

        $caps = self::caps();
        $code = self::LOCAL;
        $provider = '';

        if ($caps['auth_type']) {
            $cols = 'authentication_type';

            if ($caps['ldap_directory']) {
                $cols .= ', ldap_userdirectoryid';
            }

            $row = \DBfetch(\DBselect('SELECT ' . $cols . ' FROM config'));

            if ($row && (int) $row['authentication_type'] === 1) {
                $code = self::LDAP;

                // Nome do diretorio LDAP default, quando configurado.
                if ($caps['userdirectory'] && !empty($row['ldap_userdirectoryid'])) {
                    $dir = \DBfetch(\DBselect(
                        'SELECT name FROM userdirectory WHERE userdirectoryid=' .
                        \zbx_dbstr($row['ldap_userdirectoryid'])
                    ));

                    if ($dir && $dir['name'] !== '') {
                        $provider = $dir['name'];
                    }
                }
            }
        }

        self::$system_default = ['code' => $code, 'provider' => $provider];

        return self::$system_default;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SQL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Monta a query de usuarios ja com todos os campos necessarios para resolve().
     *
     * Compativel com ONLY_FULL_GROUP_BY (MariaDB 10.11): toda coluna nao agregada
     * aparece no GROUP BY.
     *
     * @param string $where_userid  Se informado, filtra por um userid especifico.
     */
    public static function usersQuery(string $where_userid = ''): string {
        $caps = self::caps();

        $select = [
            'u.userid',
            'u.username',
            'u.name',
            'u.surname',
            // Metodo de autenticacao: considera apenas 1 (internal) e 2 (LDAP).
            // gui_access = 3 (disabled) e tratado a parte para nao mascarar o metodo.
            'COALESCE(MAX(CASE WHEN g.gui_access IN (1,2) THEN g.gui_access END), 0) AS auth_gui_access',
            'MAX(CASE WHEN g.gui_access = 3 THEN 1 ELSE 0 END) AS frontend_disabled',
            'COUNT(ug.usrgrpid) AS group_count',
        ];

        $group_by = ['u.userid', 'u.username', 'u.name', 'u.surname'];

        $from = 'FROM users u' .
                ' LEFT JOIN users_groups ug ON ug.userid = u.userid' .
                ' LEFT JOIN usrgrp g ON g.usrgrpid = ug.usrgrpid';

        if ($caps['userdirectory']) {
            $select[]   = 'u.userdirectoryid';
            $group_by[] = 'u.userdirectoryid';

            $from .= ' LEFT JOIN userdirectory ud ON ud.userdirectoryid = u.userdirectoryid';

            $select[]   = 'ud.name AS provider_name';
            $group_by[] = 'ud.name';

            if ($caps['idp_type']) {
                $select[]   = 'ud.idp_type';
                $group_by[] = 'ud.idp_type';
            }

            if ($caps['provision']) {
                $select[]   = 'ud.provision_status';
                $group_by[] = 'ud.provision_status';
            }
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' ' . $from;

        if ($where_userid !== '') {
            $sql .= ' WHERE u.userid=' . \zbx_dbstr($where_userid);
        }

        $sql .= ' GROUP BY ' . implode(', ', $group_by) .
                ' ORDER BY u.username ASC';

        return $sql;
    }

    /** Lista completa de usuarios, ja resolvidos. */
    public static function fetchUsers(): array {
        $users = \DBfetchArray(\DBselect(self::usersQuery()));

        foreach ($users as &$user) {
            $user['auth'] = self::resolve($user);
        }
        unset($user);

        return $users;
    }

    /** Um unico usuario, ja resolvido. Devolve null se nao existir. */
    public static function fetchUser(string $userid): ?array {
        $user = \DBfetch(\DBselect(self::usersQuery($userid)));

        if (!$user) {
            return null;
        }

        $user['auth'] = self::resolve($user);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolucao
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve o metodo de autenticacao a partir de uma linha de usersQuery().
     *
     * @return array{
     *     code:string, label:string, class:string, provider:string,
     *     provisioned:bool, inherited:bool, frontend_disabled:bool, title:string
     * }
     */
    public static function resolve(array $row): array {
        $caps = self::caps();

        $code        = self::UNKNOWN;
        $provider    = '';
        $provisioned = false;
        $inherited   = false;

        $directory_id = isset($row['userdirectoryid']) ? (int) $row['userdirectoryid'] : 0;

        // 1. Usuario vinculado a um user directory (LDAP ou SAML) — fonte autoritativa.
        if ($caps['userdirectory'] && $directory_id > 0) {
            $idp_type = isset($row['idp_type']) ? (int) $row['idp_type'] : 1;
            $code     = ($idp_type === 2) ? self::SAML : self::LDAP;
            $provider = (string) ($row['provider_name'] ?? '');

            if ($caps['provision']) {
                $provisioned = ((int) ($row['provision_status'] ?? 0) === 1);
            }
        }
        else {
            // 2. gui_access dos grupos do usuario.
            $gui_access = (int) ($row['auth_gui_access'] ?? 0);

            if ($gui_access === 2) {
                $code = self::LDAP;
                $provider = self::systemDefault()['provider'];
            }
            elseif ($gui_access === 1) {
                $code = self::LOCAL;
            }
            else {
                // 3. gui_access = 0 → herda o default do sistema.
                $default   = self::systemDefault();
                $code      = $default['code'];
                $provider  = $default['provider'];
                $inherited = true;
            }
        }

        return [
            'code'              => $code,
            'label'             => self::label($code),
            'class'             => 'zbx-badge-' . $code,
            'provider'          => $provider,
            'provisioned'       => $provisioned,
            'inherited'         => $inherited,
            'frontend_disabled' => (int) ($row['frontend_disabled'] ?? 0) === 1,
            'title'             => self::describe($code, $provider, $inherited, $provisioned),
        ];
    }

    private static function label(string $code): string {
        switch ($code) {
            case self::LOCAL: return 'LOCAL';
            case self::LDAP:  return 'LDAP';
            case self::SAML:  return 'SAML';
            default:          return 'N/D';
        }
    }

    /** Texto de tooltip explicando de onde a etiqueta veio. */
    private static function describe(string $code, string $provider, bool $inherited, bool $provisioned): string {
        $t = I18n::get();

        $parts = [];

        if ($inherited) {
            $parts[] = $t('auth_from_system_default');
        }
        else {
            $parts[] = $t('auth_from_user_config');
        }

        if ($provider !== '') {
            $parts[] = $t('auth_provider') . ': ' . $provider;
        }

        if ($provisioned) {
            $parts[] = $t('auth_jit_provisioned');
        }

        return implode(' · ', $parts);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Renderizacao
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Marcador curto usado dentro de <option>, onde nao e possivel usar HTML.
     * Ex: "[LDAP · AD-Embratel]"
     */
    public static function optionMarker(array $auth): string {
        $marker = $auth['label'];

        if ($auth['provider'] !== '') {
            $marker .= ' · ' . $auth['provider'];
        }

        if ($auth['frontend_disabled']) {
            $marker .= ' · ' . I18n::get()('auth_no_frontend_short');
        }

        return '[' . $marker . ']';
    }

    /** Conjunto de etiquetas HTML (metodo + provedor + JIT + bloqueio). */
    public static function badgeHtml(array $auth): string {
        $t    = I18n::get();
        $html = '<span class="zbx-migrate-badge ' . $auth['class'] . '"'
              . ' title="' . htmlspecialchars($auth['title'], ENT_QUOTES) . '">'
              . htmlspecialchars($auth['label'], ENT_QUOTES);

        if ($auth['inherited']) {
            $html .= '<em>' . htmlspecialchars($t('auth_default_suffix'), ENT_QUOTES) . '</em>';
        }

        $html .= '</span>';

        if ($auth['provider'] !== '') {
            $html .= '<span class="zbx-migrate-chip" title="' . htmlspecialchars($t('auth_provider'), ENT_QUOTES) . '">'
                   . htmlspecialchars($auth['provider'], ENT_QUOTES) . '</span>';
        }

        if ($auth['provisioned']) {
            $html .= '<span class="zbx-migrate-chip zbx-chip-jit" title="'
                   . htmlspecialchars($t('auth_jit_provisioned'), ENT_QUOTES) . '">JIT</span>';
        }

        if ($auth['frontend_disabled']) {
            $html .= '<span class="zbx-migrate-chip zbx-chip-blocked">'
                   . htmlspecialchars($t('auth_no_frontend'), ENT_QUOTES) . '</span>';
        }

        return $html;
    }

    /** Payload enxuto para o JS (usado nos data-attributes das <option>). */
    public static function jsPayload(array $auth): array {
        return [
            'label'       => $auth['label'],
            'class'       => $auth['class'],
            'provider'    => $auth['provider'],
            'provisioned' => $auth['provisioned'],
            'inherited'   => $auth['inherited'],
            'blocked'     => $auth['frontend_disabled'],
            'title'       => $auth['title'],
        ];
    }
}

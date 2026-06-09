<?php
/**
 * i18n helper para o módulo zbx-user-migrate.
 *
 * Detecta o idioma da sessão do Zabbix e carrega as strings correspondentes.
 * Fallback: en_US.
 *
 * Uso:
 *   $t = I18n::get();
 *   echo $t('User Migration');
 *   echo $t('migration_success', $src, $dst);
 */

namespace Modules\UserMigrate;

class I18n {

    private static ?array $strings = null;
    private static string $lang    = 'en_US';

    /**
     * Retorna a função de tradução carregada para o idioma atual.
     */
    public static function get(): \Closure {
        if (self::$strings === null) {
            self::load();
        }

        return function(string $key, ...$args): string {
            $str = self::$strings[$key] ?? $key;
            if (!empty($args)) {
                return vsprintf($str, $args);
            }
            return $str;
        };
    }

    /**
     * Detecta o idioma do Zabbix e carrega as strings.
     */
    private static function load(): void {
        // Tenta detectar o idioma da sessão do Zabbix
        $zbx_lang = 'en_US';

        if (isset(\CWebUser::$data['lang']) && \CWebUser::$data['lang']) {
            $zbx_lang = \CWebUser::$data['lang'];
        } elseif (function_exists('CProfile') || class_exists('CProfile')) {
            try {
                $profile_lang = \CProfile::get('web.user.lang');
                if ($profile_lang) $zbx_lang = $profile_lang;
            } catch (\Throwable $e) { /* ignora */ }
        }

        // Mapeia pt_BR e variantes
        $lang_map = [
            'pt_BR' => 'pt_BR',
            'pt'    => 'pt_BR',
            'en_US' => 'en_US',
            'en_GB' => 'en_US',
            'en'    => 'en_US',
        ];

        $locale  = $lang_map[$zbx_lang] ?? 'en_US';
        $file    = __DIR__ . '/' . $locale . '/strings.php';
        $default = __DIR__ . '/en_US/strings.php';

        if (file_exists($file)) {
            self::$strings = require $file;
            self::$lang    = $locale;
        } elseif (file_exists($default)) {
            self::$strings = require $default;
            self::$lang    = 'en_US';
        } else {
            self::$strings = [];
        }
    }

    public static function getLang(): string {
        if (self::$strings === null) self::load();
        return self::$lang;
    }
}

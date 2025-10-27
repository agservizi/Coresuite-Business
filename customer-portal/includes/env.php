<?php
declare(strict_types=1);

/**
 * Carica le variabili ambiente specifiche del portale clienti.
 * In primis include il loader principale dell'applicazione e poi
 * aggiunge le configurazioni dedicate al dominio pickup.coresuite.it.
 */

require_once CORESUITE_ROOT . 'includes/env.php';

const PORTAL_ENV_FILES = [
    CORESUITE_ROOT . '.env',
    PORTAL_ROOT . '/.env',
];

if (!function_exists('load_portal_env')) {
    function load_portal_env(): void
    {
        foreach (PORTAL_ENV_FILES as $envPath) {
            load_env($envPath);
        }

        $defaults = [
            'PORTAL_URL' => 'https://pickup.coresuite.it',
            'PORTAL_SESSION_DOMAIN' => '.coresuite.it',
        ];

        foreach ($defaults as $key => $value) {
            if (env($key) === null) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                if (!isset($_SERVER[$key])) {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

load_portal_env();

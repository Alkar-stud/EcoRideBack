<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

date_default_timezone_set('Europe/Paris');

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

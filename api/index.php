<?php

declare(strict_types=1);

$viewCachePath = sys_get_temp_dir().'/dapur-rapi-views';

if (! is_dir($viewCachePath)) {
    mkdir($viewCachePath, 0775, true);
}

putenv('VIEW_COMPILED_PATH='.$viewCachePath);
putenv('LOG_CHANNEL=stderr');

$_ENV['VIEW_COMPILED_PATH'] = $viewCachePath;
$_SERVER['VIEW_COMPILED_PATH'] = $viewCachePath;
$_ENV['LOG_CHANNEL'] = 'stderr';
$_SERVER['LOG_CHANNEL'] = 'stderr';

require dirname(__DIR__).'/public/index.php';

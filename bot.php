<?php
require_once __DIR__ . '/vendor/autoload.php';

$bot = new \UploaderBot\UploaderBot(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.ini');
$bot->run($argc, $argv);
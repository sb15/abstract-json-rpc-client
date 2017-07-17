<?php

include dirname(__DIR__) . "/vendor/autoload.php";

$api = new FakeApi('endpoint');

$api->action('name', 'param');
$api->namespace__method('field');



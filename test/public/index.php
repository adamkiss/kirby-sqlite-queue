<?php

use Kirby\Cms\App;

define('KIRBY_HELPER_DUMP', false);
define('KIRBY_HELPER_GO', false);

include __DIR__ . '/../../vendor/autoload.php';

$kirby = new App([
    'roots' => [
        'index'    => __DIR__,
        'base'     => $base = dirname(__DIR__, 1),
        'content'  => $base . '/content',
        'site'     => $base . '/site',
	],
	'options' => [
		'adamkiss.sqlite-queue' => [
			'queues' => [
				'default' => fn($data) => ray()->pass($data)['param1'],
			],
		]
	]
]);

echo $kirby->render();

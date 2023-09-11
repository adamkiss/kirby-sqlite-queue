<?php

use Kirby\Cms\App;

App::plugin("adamkiss/defines-a-queue", [
	'hooks' => [
		'system.loadPlugins:after' => function() {
			if (kirby()->option('adamkiss.defines-a-queue') !== true) {
				return;
			}

			kirby()->extend([
				'options' => [
					'adamkiss.kirby-sqlite-queue' => [
						'queues' => [
							'queue-from-plugin' => [
								'handler' => fn($data) => null,
								'priority' => '3',
								'retries' => '2',
							],
							'other-queue-from-plugin' => fn($data) => null,
						]
					]
				]
			]);
		}
	],
]);

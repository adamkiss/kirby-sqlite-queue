<?php

use Adamkiss\SqliteQueue\Plugin;
use Adamkiss\SqliteQueue\Queue;
use Kirby\Cms\App;

load([
	'Adamkiss\\SqliteQueue\\Plugin' => 'src/Plugin.php',
	'Adamkiss\\SqliteQueue\\Database' => 'src/Database.php',
	'Adamkiss\\SqliteQueue\\Queue' => 'src/Queue.php',
	'Adamkiss\\SqliteQueue\\Job' => 'src/Job.php',
	'Adamkiss\\SqliteQueue\\Stats' => 'src/Stats.php',
]);

App::plugin('adamkiss/kirby-sqlite-queue', [
	'options' => [
		// plugin options
		'database' => kirby()->root('site') . '/db/queue.sqlite',
		'tables' => [
			'jobs' => 'jobs',
			'results' => 'results'
		],

		// queue options (defaults)
		'sync' => false,
		'retries' => 3,
		'backoff' => 15 * 60,
		'priority' => 0,

		// queues
		'queues' => [],
	],

	'siteMethods' => [
		'queue' => function (?string $q = null): Plugin|Queue|null {
			$plugin = Plugin::instance($this->kirby());

			if (is_null($q)) {
				return $plugin;
			}

			return $plugin->get($q);
		}
	],

	'commands' => [
		'queue:stats' => [
			'description' => 'Queue: show stats',
			'args' => [],
			'command' => function (Kirby\CLI\CLI $cli, bool $test = false) {
				$stats = queue()->stats();
				if (!empty($stats) && is_array($stats)) {
					$cli->table($stats);
					return;
				} else {
					$cli->out('No queues found / no jobs ran yet.'); // @codeCoverageIgnore
				}
			}
		],
		'queue:work' => [
			'description' => 'Queue: work',
			'args' => [
				'sleep' => [
					'description' => 'Sleep time',
					'required' => false,
					'defaultValue' => 5
				]
			],
			'command' => function (Kirby\CLI\CLI $cli) {
				pcntl_async_signals(true);
				set_time_limit(0);
				pcntl_signal(SIGTERM, fn () => exit());
				pcntl_signal(SIGINT, fn () => exit());

				$sleep = $cli->climate()->arguments->get('sleep');

				while (true) {
					while ($job = queue()->next_job()) {
						$job->execute();
					}

					if (is_null($sleep)) {
						break;
					}

					sleep($sleep); // @codeCoverageIgnore
				}
			}
		]
	]
]);

if (!function_exists('queue') && (defined('KIRBY_HELPER_QUEUE') !== true || constant('KIRBY_HELPER_QUEUE') === false)) {
	function queue(?string $queue = null): Plugin|Queue|null
	{
		return kirby()->site()->queue($queue);
	}
}

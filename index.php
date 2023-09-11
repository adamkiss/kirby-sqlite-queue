<?php

use Kirby\Cms\App;
use Adamkiss\SqliteQueue\Queue;
use Adamkiss\SqliteQueue\Plugin;

load([
	'Adamkiss\\SqliteQueue\\Plugin' => 'src/Plugin.php',
	'Adamkiss\\SqliteQueue\\Database' => 'src/Database.php',
	'Adamkiss\\SqliteQueue\\Queue' => 'src/Queue.php',
	'Adamkiss\\SqliteQueue\\Job' => 'src/Job.php',
	'Adamkiss\\SqliteQueue\\Stats' => 'src/Stats.php',
]);

App::plugin("adamkiss/kirby-sqlite-queue", [

	'options' => [
		// plugin options
		'database' => kirby()->root('site') . '/db/queue.sqlite',
		'tables' => [
			'jobs' => 'jobs',
			'logs' => 'logs',
			'info' => 'info'
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
		'queue' => function(?string $q = null): Plugin|Queue|null {
			$plugin = Plugin::instance($this->kirby());

			if (is_null($q)) {
				return $plugin;
			}

			return $plugin->get($q);
		}
	],

	'hooks' => [
		'system.loadPlugins:after' => function() {}
	],

	'commands' => [
		'queue:stats' => [
			'description' => 'Queue: show stats',
			'args' => [],
			'command' => function(Kirby\CLI\CLI $cli) {
				# wip
			}
		],
		'queue:work' => [
			'description' => 'Queue: work',
			'args' => [
				'sleep' => [
					'description' => 'Sleep time',
					'required' => false,
					'default' => 5
				]
			],
			'command' => function(Kirby\CLI\CLI $cli) {
				// @codeCoverageIgnoreStart

				pcntl_async_signals(true);
				set_time_limit(0);
				pcntl_signal(SIGTERM, fn () => exit());
				pcntl_signal(SIGINT, fn () => exit());

				while(true) {
					while($job = queue()->next_job()) {
						$job->execute();
					}

					sleep($cli->climate()->arguments->get('sleep'));
				}

				// @codeCoverageIgnoreEnd
			}
		]
	]
]);

if (! function_exists('queue') && (defined('KIRBY_HELPER_QUEUE') !== true || constant('KIRBY_HELPER_QUEUE') === false)) {
	function queue(?string $queue = null): Plugin|Queue|null
	{
		return kirby()->site()->queue($queue);
	}
}

<?php

namespace Adamkiss\SqliteQueue;

use Kirby\Database\Database as KirbyDatabase;
use Kirby\Toolkit\Obj;
use Kirby\Toolkit\Str;

/**
 * Database is the contact point between the kirby3-database-queue plugin and the database.
 *
 * @author Adam Kiss
 * @copyright 2023 Adam Kiss
 * @license MIT
 */
class Database extends KirbyDatabase
{
	protected KirbyDatabase $db;

	public function __construct(
		protected Plugin $plugin
	) {
		parent::__construct([
			'type' => 'sqlite',
			'database' => $this->plugin()->option('database'),
		]);

		// Set WAL mode
		$this->execute('PRAGMA journal_mode = WAL;');

		$this->setup();
	}

	/**
	 * Get the plugin syntax consistency.
	 */
	public function plugin(): Plugin
	{
		return $this->plugin;
	}

	public function setup(): void
	{
		foreach (Str::split(<<<SQL
			CREATE TABLE IF NOT EXISTS 'jobs' (
				'id' INTEGER PRIMARY KEY AUTOINCREMENT,
				'status' INTEGER NOT NULL DEFAULT 0, /* 0 = new, 1 = in progress */
				'queue' TEXT NOT NULL DEFAULT 'default',
				'priority' INTEGER NOT NULL DEFAULT 0,
				'data' BLOB NOT NULL,
				'attempt' INTEGER NOT NULL DEFAULT 1,
				'created_at' DATE NOT NULL,
				'available_at' DATE DEFAULT NULL
			);
			CREATE UNIQUE INDEX IF NOT EXISTS "idx_id" ON 'jobs' ("id");
			CREATE INDEX IF NOT EXISTS "idx_status" ON 'jobs' ("status");
			CREATE INDEX IF NOT EXISTS "idx_available_at" ON 'jobs' ("available_at");

			CREATE TABLE IF NOT EXISTS 'logs' (
				'id' INTEGER PRIMARY KEY AUTOINCREMENT,
				'queue' TEXT NOT NULL DEFAULT 'default',
				'status' INTEGER NOT NULL, /* 0 = completed, 1 = failed */
				'data' BLOB NOT NULL,
				'result' BLOB,
				'attempt' INTEGER NOT NULL DEFAULT 1,
				'created_at' DATE NOT NULL,
				'executed_at' DATE NOT NULL,
				'completed_at' DATE NOT NULL
			);
			CREATE UNIQUE INDEX IF NOT EXISTS "idx_id" ON 'logs' ("id");
			CREATE INDEX IF NOT EXISTS "idx_status" ON 'logs' ("status");
		SQL, ';') as $query) {
			$this->execute($query);
		}
	}

	/**
	 * Count jobs in the queue - optionally filtered by queue name.
	 */
	public function count_jobs(?string $queue = null): int
	{
		$query = $this->table('jobs')
			->select('id')
			->where('status', 0)
			->orWhere('status', 1);

		if (!is_null($queue)) {
			$query->where(['queue' => $queue]);
		}

		return $query->count();
	}

	/**
	 * Get the next job in the queue
	 */
	public function next_job(): ?Job
	{
		return $this->table('jobs')
			->where(['status' => 0])
			->andWhere(
				fn ($w) => $w
					->where('available_at <= datetime("now", "localtime")')
					->orWhere('available_at IS NULL')
			)
			->order('available_at asc')
			->order('priority desc')

			->fetch(fn ($row) => Job::from_db($this->plugin(), $row))
			->limit(1)
			->all()

			->first();
	}

	/**
	 * Get stats about the jobs
	 */
	public function get_stats(?string $for = null): array
	{
		$jobs = $this->table('jobs')
			->select('count(id) as count, queue, status')
			->group('queue, status')
			->all();
		$logs = $this->table('logs')
			->select('count(id) as count, queue, status')
			->group('queue, status')
			->all();

		$queues = $this->plugin()->all()->clone()
			->map(fn ($q) => new Obj([
				'Queue name' => $q->name(),
				'Waiting' => 0,
				'In progress' => 0,
				'Completed' => 0,
				'Failed' => 0
			]));

		foreach ($jobs as $row) {
			$queues->get($row->queue())->{$row->status() === '0' ? 'Waiting' : 'In progress'} = (int)($row->count());
		}
		foreach ($logs as $row) {
			$queues->get($row->queue())->{$row->status() === '0' ? 'Completed' : 'Failed'} = (int)($row->count());
		}

		if (!is_null($for)) {
			return $queues->get($for)->toArray();
		}

		return $queues->toArray(fn ($q) => $q->toArray());
	}
}

<?php

declare(strict_types=1);

namespace Adamkiss\SqliteQueue;

use Kirby\Toolkit\Obj;
use Kirby\Toolkit\Date;

class Result extends Obj {
	protected Queue $queue;

	function __construct(
		protected Plugin $plugin,
		public Date $created_at,
		public Date $executed_at,
		public Date $completed_at,
		string|Queue $queue = 'default',
		public ?int $id = null,
		public int $status = 0,
		public mixed $data = null,
		public mixed $result = null,
		public int $attempt = 1,
	) {
		$this->queue = $queue instanceof Queue ? $queue : $this->plugin->get($queue);
	}

	/**
	 * Create a JobLog object from the database row
	 */
	public static function from_db(
		Plugin $plugin,
		array $row,
	) : Result {
		return new self(
			plugin: $plugin,
			id: intval($row['id']),
			data: unserialize($row['data']),
			result: unserialize($row['result']),
			status: intval($row['status']),
			created_at: new Date($row['created_at']),
			executed_at: new Date($row['executed_at']),
			completed_at: new Date($row['completed_at']),
		);
	}

	/**
	 * Save the result to the database
	 */
	public function save() : self {
		$this->plugin->db()->table('logs')
			->insert([
				'queue' => $this->queue->name(),
				'status' => $this->status,
				'data' => serialize($this->data),
				'result' => serialize($this->result),
				'attempt' => $this->attempt,
				'created_at' => $this->created_at,
				'executed_at' => $this->executed_at,
				'completed_at' => $this->completed_at
			]);

		$this->id = $this->plugin->db()->lastId();
		return $this;
	}

}

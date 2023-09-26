<?php

declare(strict_types = 1);

namespace Adamkiss\SqliteQueue;

use Kirby\Toolkit\Date;

class Job
{
	protected Queue $queue;

	public function __construct(
		protected Plugin $plugin,
		string|Queue $queue = 'default',
		public readonly array $data = [],
		public ?int $id = null,
		public readonly int $attempt = 1,
		protected int $status = 0,
		public readonly Date $created_at = new Date(),
		public readonly ?Date $available_at = null,
	) {
		$this->queue = $queue instanceof Queue ? $queue : $this->plugin->get($queue);
	}

	/**
	 * Create a Job object from a database row
	 */
	public static function from_db(
		Plugin $plugin,
		array $row,
	): Job {
		return new self(
			plugin: $plugin,
			queue: $plugin->get($row['queue']),
			id: (int)($row['id']),
			data: unserialize($row['data']),
			attempt: (int)($row['attempt']),
			status: (int)($row['status']),
			created_at: new Date($row['created_at']),
			available_at: is_null($row['available_at'])
				? null
				: new Date($row['available_at']),
		);
	}

	/**
	 * The Database object for code consistency
	 */
	private function db(): Database
	{
		return $this->plugin->db();
	}

	/**
	 * Get the Job Queue
	 */
	public function queue(): Queue
	{
		return $this->queue;
	}

	/**
	 * Save the job to the database
	 */
	public function save(): Job
	{
		$this->db()->table('jobs')
			->insert([
				'status' => $this->status,
				'queue' => $this->queue->name(),
				'priority' => $this->queue->priority(),
				'data' => serialize($this->data),
				'attempt' => $this->attempt,
				'created_at' => $this->created_at,
				'available_at' => $this->available_at,
			]);

		$this->id = $this->db()->lastId();
		return $this;
	}

	/**
	 * Set the job status to in progress
	 */
	public function lock(): bool
	{
		return $this->db()->table('jobs')
			->where(['id' => $this->id])
			->update(['status' => 1]);
	}

	/**
	 * Delete the job from the database
	 */
	public function delete(): void
	{
		$this->db()->table('jobs')->delete(['id' => $this->id]);
	}

	public function execute(): Result
	{
		$status = null;
		$result = null;

		$this->lock();
		$executed_at = new Date();

		try {
			$result = $this->queue->handler()($this->data);
			$status = 0;
		} catch (\Throwable $th) {
			$status = 1;
			$result = [
				'error' => $th->getMessage(),
				'code' => $th->getCode(),
				'file' => $th->getFile(),
				'line' => $th->getLine()
			];
		} finally {
			// If job failed and we're retrying, create a new job with the same data after backoff time
			if ($status === 1) {
				$this->queue->retry($this);
			}

			$result = new Result(
				plugin: $this->plugin,
				created_at: $this->created_at,
				executed_at: $executed_at,
				completed_at: new Date(),
				queue: $this->queue->name(),
				status: $status,
				data: $this->data,
				result: $result,
				attempt: $this->attempt,
			);
			$result->save();

			$this->delete();

			return $result;
		}
	}

	/**
	 * Execute the job immediately
	 */
	public function executeImmediately(): mixed
	{
		return $this->queue->handler()($this->data);
	}
}

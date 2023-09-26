<?php

namespace Adamkiss\SqliteQueue;

use Closure;
use DateInterval;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Date;

class Queue
{
	protected array $options;

	/**
	 * Constructor
	 */
	public function __construct(
		protected Plugin $plugin,
		public readonly string $name,
		callable|array $options,
		array $defaults = [],
	) {
		// if only handler is passed
		if ($options instanceof Closure) {
			$options = ['handler' => $options];
		} elseif (is_callable($options)) {
			$options = ['handler' => Closure::fromCallable($options)];
		}

		// merge options with defaults
		$this->options = A::merge($defaults, $options);

		// expand callable handler if it was passed as array
		if (
			(is_array($this->options['handler']) || is_string($this->options['handler']))
			&& is_callable($this->options['handler'])
		) {
			$this->options['handler'] = Closure::fromCallable($this->options['handler']);
		}
	}

	/**
	 * Get the plugin for this queue.
	 */
	public function plugin(): Plugin
	{
		return $this->plugin;
	}

	/**
	 * Get the name of this queue.
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * Get the priority of this queue.
	 */
	public function priority(): int
	{
		return $this->options['priority'];
	}

	/**
	 * Get the retry count for this queue
	 */
	public function retries(): int
	{
		return $this->options['retries'];
	}

	/**
	 * Get the backoff time for this queue in seconds
	 */
	public function backoff(): int
	{
		return $this->options['backoff'];
	}

	/**
	 * Get the handler for this queue
	 */
	public function handler(): Closure
	{
		return $this->options['handler'];
	}

	/**
	 * Get the priority of this queue.
	 */
	public function sync(): bool
	{
		return $this->options['sync'];
	}

	/**
	 * Get the number of jobs in the queue.
	 */
	public function count(): int
	{
		return $this->plugin()->db()->count_jobs($this->name());
	}

	/**
	 * Clear the queue.
	 */
	public function clear(): bool
	{
		return $this->plugin()->db()
			->table('jobs')
			->delete(['queue' => $this->name()]);
	}

	/**
	 * Add a job to the queue.
	 */
	public function add(
		array $data = [],
		string|Date|null $execute_at = null,
		int $attempt = 1
	): mixed {
		// If sync, execute immediately
		if ($this->sync()) {
			return (new Job(
				plugin: $this->plugin(),
				queue: $this,
				data: $data,
				available_at: $execute_at,
				attempt: $attempt,
			))->executeImmediately();
		}

		// convert string to Date
		if (is_string($execute_at)) {
			$execute_at = new Date($execute_at);
		}

		return (new Job(
			plugin: $this->plugin(),
			queue: $this,
			data: $data,
			available_at: $execute_at,
			attempt: $attempt,
		))->save();
	}

	/**
	 * Retry a job.
	 */
	public function retry(Job $job): ?Job
	{
		if ($job->attempt > $this->retries()) {
			return null;
		}

		return $this->add(
			$job->data,
			$this->backoff() > 0
				? (new Date())->add(new DateInterval("PT{$this->backoff()}S"))
				: null,
			$job->attempt + 1
		);
	}

	public function stats(): array
	{
		return $this->plugin()->stats($this->name());
	}
}

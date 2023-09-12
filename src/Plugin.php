<?php

namespace Adamkiss\SqliteQueue;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Facade;
use Kirby\Toolkit\Collection;

class Plugin extends Facade {
	private App $kirby;

	protected static ?Plugin $instance = null;

	protected ?Database $db = null;
	protected ?Collection $queues;

	/**
	 * Private constructor to prevent direct instantiation.
	 * Initializes the plugin and registers all queues.
	 */
	public function __construct(?App $kirby = null){
		$this->kirby = $kirby ?? kirby();

		$this->queues = new Collection();

		// Get defaults
		$defaults = A::get(
			$this->option(default: []),
			['sync', 'retries', 'backoff', 'priority']
		);

		// Register queues
		foreach ($this->option('queues', []) as $name => $options) {
			$this->queues->append($name, new Queue($this, $name, $options, $defaults));
		}
	}

	/**
	 * Get the singleton instance of the plugin.
	 */
	static function instance(?App $kirby = null)
	{
        if (
            self::$instance !== null &&
            ($kirby === null || self::$instance->kirby === $kirby)
        ) {
            return self::$instance;
        }

        return self::$instance = new self($kirby);
	}

	/**
	 * Get the option from Kirby setup.
	 */
	public function option(?string $key = null, mixed $default = null): mixed
	{
		if (is_null($key)) {
			return $this->kirby->option("adamkiss.kirby-sqlite-queue", $default);
		}

		return $this->kirby->option("adamkiss.kirby-sqlite-queue.{$key}", $default);
	}

	/**
	 * Get the plugin's database.
	 */
	public function db(): Database
	{
		if (is_null($this->db)) {
			$this->db = new Database($this);
		}

		return $this->db;
	}

	/**
	 * Check for existence of a queue.
	 */
	public function has(string $name): bool
	{
		return $this->queues->has($name);
	}

	/**
	 * Get all queues.
	 */
	public function all(): Collection
	{
		return $this->queues;
	}

	/**
	 * Get a queue by name.
	 */
	public function get(string $name = 'default'): ?Queue
	{
		if (! $this->has($name)) {
			throw new InvalidArgumentException("Queue '$name' does not exist.");
			return null;
		}

		return $this->queues->get($name);
	}

	/**
	 * Add a job to a named queue, or the default queue if no name is provided AND only one queue is available.
	 */
	public function add(string|array $name, ...$data): mixed
	{
		if (! is_string($name)) {
			$queue = $this->all()->first();
			$data = A::merge([$name], $data);
		} else {
			$queue = $this->get($name);
		}

		return $queue->add(...$data);
	}

	/**
	 * Get and process the next job
	 */
	public function next_job(): ?Job
	{
		return $this->db()->next_job();
	}

	/**
	 * Count the jobs across all queues
	 */
	public function count_jobs(): int
	{
		return $this->db()->count_jobs();
	}

	/**
	 * Get stats about the queues (optionally, for a specific queue)
	 */
	function stats(?string $for = null) : array {
		return $this->db()->get_stats($for);
	}
}

<?php namespace Tatter\Workflows\Traits;

use Tatter\Workflows\Models\ActionModel;

trait ActionsTrait
{
	/**
	 * @var \Tatter\Workflows\Config\Workflows
	 */
	public $config;

	/**
	 * @var \Tatter\Workflows\Entities\Job
	 */
	public $job;

	/**
	 * @var \Tatter\Workflows\Models\JobModel
	 */
	public $jobs;

	/**
	 * @var \CodeIgniter\HTTP\RequestInterface
	 */
	public $request;

    /**
     * Sets common resources for Actions (frees up __construct for individual classes).
     *
     * @return $this
     */
	public function initialize(): self
	{
		$this->request = service('request');
		$this->config  = config('Workflows');
		$this->jobs    = model($this->config->jobModel);

		return $this;
	}

    /**
	 * Magic wrapper for getting values from the definition
	 *
	 * @param string $name
	 *
	 * @return string
	 */
    public function __get(string $name): string
    {
		return $this->definition[$name];
    }

    /**
	 * Creates the database record for this class based on its definition
	 *
	 * @return int|bool  true for existing entry, false for failure, int for inserted ID
	 */
	public function register()
	{
		$actions = model(ActionModel::class);

		// Check for an existing entry
		if ($action = $actions->where('uid', $this->uid)->first())
		{
			return true;
		}

		return $actions->insert($this->definition);
	}

    /**
	 * Deletes this action from the database (soft)
	 *
	 * @return bool  Result from the model
	 */
	public function remove(): bool
	{
		return model(ActionModel::class)->where('uid', $this->uid)->delete();
	}
}

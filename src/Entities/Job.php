<?php namespace Tatter\Workflows\Entities;

use CodeIgniter\Entity;
use Tatter\Workflows\Entities\Action;
use Tatter\Workflows\Entities\Stage;
use Tatter\Workflows\Entities\Workflow;
use Tatter\Workflows\Exceptions\WorkflowsException;
use Tatter\Workflows\Models\JobModel;
use Tatter\Workflows\Models\StageModel;
use Tatter\Workflows\Models\ActionModel;
use Tatter\Workflows\Models\WorkflowModel;

class Job extends Entity
{
	protected $dates = ['created_at', 'updated_at', 'deleted_at'];
	protected $casts = [
		'workflow_id' => 'int',
		'stage_id'    => '?int',
	];

    /**
     * Cached entity for the current Stage. Can be null for completed Jobs.
     *
     * @var Stage|null
     */
    protected $stage;

    /**
     * Indicates whether the Stage has been loaded.
     *
     * @var bool
     */
    protected $stageFlag = false;

    /**
     * Cached entity for the Workflow.
     *
     * @var Workflow
     */
    protected $workflow;

	//--------------------------------------------------------------------

    /**
     * Gets the current Stage
     *
     * @return Stage|null
     */
	public function getStage(): ?Stage
	{
		if (! $this->stageFlag)
		{
			$this->stage     = model(StageModel::class)->find($this->attributes['stage_id']);
			$this->stageFlag = true;
		}

		return $this->stage;
	}

    /**
     * Gets the Workflow
     *
     * @return Workflow
     */
	public function getWorkflow(): Workflow
	{
		if ($this->workflow === null)
		{
			$this->workflow = model(WorkflowModel::class)->find($this->attributes['workflow_id']);
		}

		return $this->workflow;
	}

    /**
     * Gets all Stages from the Workflow
     *
     * @return array<Stage>
     */
	public function getStages(): array
	{
		return $this->getWorkflow()->stages;
	}

	//--------------------------------------------------------------------

    /**
     * Returns the next Stage
     *
     * @return Stage|null
     */
	public function next(): ?Stage
	{
		return $this->_next($this->getStages());
	}

    /**
     * Returns the previous Stage
     *
     * @return Stage|null
     */
	public function previous(): ?Stage
	{
		// Look through all the Stages backwards
		$stages = $this->getStages();
		array_reverse($stages);

		return $this->_next($stages);
	}

    /**
     * Returns the next Stage from an array of Stages
     *
     * @param array<Stage> $stages
     *
     * @return Stage|null
     */
	protected function _next($stages): ?Stage
	{
		// look through the stages
		$stage = current($stages);
		do
		{
			// Check if this is the current stage
			if ($stage->id == $this->attributes['stage_id'])
			{
				// Look for the next Stage
				if (! $stage = next($stages))
				{
					return null;
				}

				return $stage;
			}
		} while ($stage = next($stages));

		return null;
	}

	//--------------------------------------------------------------------

    /**
     * Moves through the Workflow, skipping non-required Stages but running their Action functions.
     *
     * @param int $actionId  ID of the target Action
     *
     * @return array  Array of boolean results from each Action's up/down method
     * @throws WorkflowsException
     */
	public function travel(int $actionId): array
	{
		// Make sure the target Stage exists
		if (! $target = model(StageModel::class)
			->where('action_id', $actionId)
			->where('workflow_id', $this->attributes['workflow_id'])
			->first())
		{
			throw WorkflowsException::forStageNotFound();
		}

		// Get the Workflow, Stages, and current Stage
		$workflow = $this->getWorkflow();
		$stages   = $this->getStages();
		$current  = $this->getStage();

		// Determine direction of travel
		if ($current->id < $target->id)
		{
			// Make sure this won't skip any required stages
			$test = model(StageModel::class)
				->where('id >=', $current->id)
				->where('id <', $target->id)
				->where('workflow_id', $this->attributes['workflow_id'])
				->where('required', 1)
				->first();

			if (! empty($test))
			{
				throw WorkflowsException::forSkipRequiredStage($test->name);
			}

			$method = 'up';
		}
		else
		{
			$method = 'down';
			arsort($stages);
		}

		// Travel the Workflow running the appropriate method
		$results = [];
		foreach ($stages as $stage)
		{
			// Check if we need to run this action
			if (
				($method == 'up'   && $stage->id >= $current->id) ||
				($method == 'down' && $stage->id <= $current->id)
			)
			{
				$results[$stage->id] = $stage->action->$method();
			}

			// If the target was reached then we're done
			if ($stage->id === $target->id)
			{
				break;
			}
		}

		// Update the Job
		model(JobModel::class)->update($this->attributes['id'], ['stage_id' => $target->id]);

		return $results;
	}
}

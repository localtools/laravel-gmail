<?php

namespace Localtools\LaravelGmail\Traits;

use Google\Service\Gmail;

trait HasLabels
{
	/**
	 * List the labels in the user's mailbox.
	 *
	 * @param $userEmail
	 *
	 * @return \Google\Service\Gmail\ListLabelsResponse
	 */
	public function labelsList($userEmail)
	{
		$service = new Gmail($this);

		return $service->users_labels->listUsersLabels($userEmail);
	}

	/**
	 * Create new label by name.
	 *
	 * @param $userEmail
	 * @param $label
	 *
	 * @return \Google\Service\Gmail\Label
	 */
	public function createLabel($userEmail, $label)
	{
		$service = new Gmail($this);

		return $service->users_labels->create($userEmail, $label);
	}

	/**
	 * first or create label in the user's mailbox.
	 *
	 * @param $userEmail
	 * @param $nLabel
	 * @return \Google\Service\Gmail\Label
	 */
	public function firstOrCreateLabel($userEmail, $newLabel)
	{
		$labels = $this->labelsList($userEmail);

		foreach ($labels->getLabels() as $existLabel) {
			if ($existLabel->getName() == $newLabel->getName()) {
				return $existLabel;
			}
		}

		$service = new Gmail($this);

		return $service->users_labels->create($userEmail, $newLabel);
	}
}

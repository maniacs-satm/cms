<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\elementactions;

use Craft;
use craft\app\enums\AttributeType;
use craft\app\events\Event;
use craft\app\models\BaseElementModel;
use craft\app\models\ElementCriteria as ElementCriteriaModel;

/**
 * Set Status Element Action
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SetStatus extends BaseElementAction
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc ElementActionInterface::getTriggerHtml()
	 *
	 * @return string|null
	 */
	public function getTriggerHtml()
	{
		return Craft::$app->templates->render('_components/elementactions/SetStatus/trigger');
	}

	/**
	 * @inheritDoc ElementActionInterface::performAction()
	 *
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return bool
	 */
	public function performAction(ElementCriteriaModel $criteria)
	{
		$status = $this->getParams()->status;

		// Figure out which element IDs we need to update
		if ($status == BaseElementModel::ENABLED)
		{
			$sqlNewStatus = '1';
		}
		else
		{
			$sqlNewStatus = '0';
		}

		$elementIds = $criteria->ids();

		// Update their statuses
		Craft::$app->db->createCommand()->update(
			'elements',
			['enabled' => $sqlNewStatus],
			['in', 'id', $elementIds]
		);

		if ($status == BaseElementModel::ENABLED)
		{
			// Enable their locale as well
			Craft::$app->db->createCommand()->update(
				'elements_i18n',
				['enabled' => $sqlNewStatus],
				['and', ['in', 'elementId', $elementIds], 'locale = :locale'],
				[':locale' => $criteria->locale]
			);
		}

		// Clear their template caches
		Craft::$app->templateCache->deleteCacheById($elementIds);

		// Fire an 'onSetStatus' event
		$this->onSetStatus(new Event($this, [
			'criteria'   => $criteria,
			'elementIds' => $elementIds,
			'status'     => $status,
		]));

		$this->setMessage(Craft::t('Statuses updated.'));

		return true;
	}

	// Events
	// -------------------------------------------------------------------------

	/**
	 * Fires an 'onSetStatus' event.
	 *
	 * @param Event $event
	 *
	 * @return null
	 */
	public function onSetStatus(Event $event)
	{
		$this->raiseEvent('onSetStatus', $event);
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseElementAction::defineParams()
	 *
	 * @return array
	 */
	protected function defineParams()
	{
		return [
			'status' => [AttributeType::Enum, 'values' => [BaseElementModel::ENABLED, BaseElementModel::DISABLED], 'required' => true]
		];
	}
}
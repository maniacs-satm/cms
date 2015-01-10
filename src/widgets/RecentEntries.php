<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\widgets;

use Craft;
use craft\app\enums\AttributeType;
use craft\app\enums\ElementType;
use craft\app\enums\SectionType;
use craft\app\helpers\JsonHelper;

/**
 * Class RecentEntries widget.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class RecentEntries extends BaseWidget
{
	// Properties
	// =========================================================================

	/**
	 * @var bool
	 */
	public $multipleInstances = true;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc ComponentTypeInterface::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Recent Entries');
	}

	/**
	 * @inheritDoc SavableComponentTypeInterface::getSettingsHtml()
	 *
	 * @return string
	 */
	public function getSettingsHtml()
	{
		return Craft::$app->templates->render('_components/widgets/RecentEntries/settings', [
			'settings' => $this->getSettings()
		]);
	}

	/**
	 * @inheritDoc WidgetInterface::getTitle()
	 *
	 * @return string
	 */
	public function getTitle()
	{
		if (Craft::$app->getEdition() >= Craft::Client)
		{
			$sectionId = $this->getSettings()->section;

			if (is_numeric($sectionId))
			{
				$section = Craft::$app->sections->getSectionById($sectionId);

				if ($section)
				{
					$title = Craft::t('Recent {section} Entries', [
						'section' => Craft::t($section->name)
					]);
				}
			}
		}

		if (!isset($title))
		{
			$title = Craft::t('Recent Entries');
		}

		// See if they are pulling entries from a different locale
		$targetLocale = $this->_getTargetLocale();

		if ($targetLocale && $targetLocale != Craft::$app->language)
		{
			$locale = Craft::$app->i18n->getLocaleById($targetLocale);

			$title = Craft::t('{title} ({locale})', [
				'title'  => $title,
				'locale' => $locale->getName()
			]);
		}

		return $title;
	}

	/**
	 * @inheritDoc WidgetInterface::getBodyHtml()
	 *
	 * @return string|false
	 */
	public function getBodyHtml()
	{
		$params = [];

		if (Craft::$app->getEdition() >= Craft::Client)
		{
			$sectionId = $this->getSettings()->section;

			if (is_numeric($sectionId))
			{
				$params['sectionId'] = (int)$sectionId;
			}
		}

		$js = 'new Craft.RecentEntriesWidget('.$this->model->id.', '.JsonHelper::encode($params).');';

		Craft::$app->templates->includeJsResource('js/RecentEntriesWidget.js');
		Craft::$app->templates->includeJs($js);
		Craft::$app->templates->includeTranslations('by {author}');

		$entries = $this->_getEntries();

		return Craft::$app->templates->render('_components/widgets/RecentEntries/body', [
			'entries' => $entries
		]);
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return [
			'section' => [AttributeType::Mixed, 'default' => '*'],
			'locale'  => [AttributeType::Locale, 'default' => Craft::$app->language],
			'limit'   => [AttributeType::Number, 'default' => 10],
		];
	}

	// Private Methods
	// =========================================================================

	/**
	 * Returns the recent entries, based on the widget settings and user permissions.
	 *
	 * @return array
	 */
	private function _getEntries()
	{
		$targetLocale = $this->_getTargetLocale();

		if (!$targetLocale)
		{
			// Hopeless
			return [];
		}

		// Normalize the target section ID value.
		$editableSectionIds = $this->_getEditableSectionIds();
		$targetSectionId = $this->getSettings()->section;

		if (!$targetSectionId || $targetSectionId == '*' || !in_array($targetSectionId, $editableSectionIds))
		{
			$targetSectionId = array_merge($editableSectionIds);
		}

		if (!$targetSectionId)
		{
			return [];
		}

		$criteria = Craft::$app->elements->getCriteria(ElementType::Entry);
		$criteria->status = null;
		$criteria->localeEnabled = null;
		$criteria->locale = $targetLocale;
		$criteria->sectionId = $targetSectionId;
		$criteria->editable = true;
		$criteria->limit = $this->getSettings()->limit;
		$criteria->order = 'elements.dateCreated desc';

		return $criteria->find();
	}

	/**
	 * Returns the Channel and Structure section IDs that the user is allowed to edit.
	 *
	 * @return array
	 */
	private function _getEditableSectionIds()
	{
		$sectionIds = [];

		foreach (Craft::$app->sections->getEditableSections() as $section)
		{
			if ($section->type != SectionType::Single)
			{
				$sectionIds[] = $section->id;
			}
		}

		return $sectionIds;
	}

	/**
	 * Returns the target locale for the widget.
	 *
	 * @return string|false
	 */
	private function _getTargetLocale()
	{
		// Make sure that the user is actually allowed to edit entries in the current locale. Otherwise grab entries in
		// their first editable locale.

		// Figure out which locales the user is actually allowed to edit
		$editableLocaleIds = Craft::$app->i18n->getEditableLocaleIds();

		// If they aren't allowed to edit *any* locales, return false
		if (!$editableLocaleIds)
		{
			return false;
		}

		// Figure out which locale was selected in the settings
		$targetLocale = $this->getSettings()->locale;

		// Only use that locale if it still exists and they're allowed to edit it.
		// Otherwise go with the first locale that they are allowed to edit.
		if (!in_array($targetLocale, $editableLocaleIds))
		{
			$targetLocale = $editableLocaleIds[0];
		}

		return $targetLocale;
	}
}
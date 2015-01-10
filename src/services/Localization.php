<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\services;

use Craft;
use craft\app\events\Event;
use craft\app\helpers\IOHelper;
use craft\app\i18n\Locale;
use craft\app\i18n\LocaleData;
use yii\base\Component;

/**
 * Class Localization service.
 *
 * An instance of the Localization service is globally accessible in Craft via [[Application::i18n `Craft::$app->i18n`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Localization extends Component
{
	// Properties
	// =========================================================================

	/**
	 * @var
	 */
	private $_appLocales;

	/**
	 * @var
	 */
	private $_siteLocales;

	/**
	 * @var
	 */
	private $_localeData;

	// Public Methods
	// =========================================================================

	/**
	 * Returns an array of all known locales. The list of known locales is based on whatever files exist in
	 * craft/app/framework/i18n/data/.
	 *
	 * @return Locale[] An array of [[Locale]] objects.
	 */
	public function getAllLocales()
	{
		$locales = [];
		$localeIds = LocaleData::getLocaleIds();

		foreach ($localeIds as $localeId)
		{
			$locales[] = new Locale($localeId);
		}

		return $locales;
	}

	/**
	 * Returns an array of locales that Craft is translated into. The list of locales is based on whatever files exist
	 * in craft/app/translations/.
	 *
	 * @return Locale[] An array of [[Locale]] objects.
	 */
	public function getAppLocales()
	{
		if (!$this->_appLocales)
		{
			$this->_appLocales = [new Locale('en_us')];

			$path = Craft::$app->path->getCpTranslationsPath();
			$folders = IOHelper::getFolderContents($path, false, ".*\.php");

			if (is_array($folders) && count($folders) > 0)
			{
				foreach ($folders as $dir)
				{
					$localeId = IOHelper::getFileName($dir, false);
					if ($localeId != 'en_us')
					{
						$this->_appLocales[] = new Locale($localeId);
					}
				}
			}
		}

		return $this->_appLocales;
	}

	/**
	 * Returns an array of the locale IDs which Craft has been translated into. The list of locales is based on whatever
	 * files exist in craft/app/translations/.
	 *
	 * @return array An array of locale IDs.
	 */
	public function getAppLocaleIds()
	{
		$locales = $this->getAppLocales();
		$localeIds = [];

		foreach ($locales as $locale)
		{
			$localeIds[] = $locale->id;
		}

		return $localeIds;
	}

	/**
	 * Returns an array of the site locales. The list of locales is based on whatever was defined in Settings > Locales
	 * in the control panel.
	 *
	 * @return Locale[] An array of [[Locale]] objects.
	 */
	public function getSiteLocales()
	{
		if (!isset($this->_siteLocales))
		{
			$query = Craft::$app->db->createCommand()
				->select('locale')
				->from('locales')
				->order('sortOrder');

			if (Craft::$app->getEdition() != Craft::Pro)
			{
				$query->limit(1);
			}

			$localeIds = $query->queryColumn();

			foreach ($localeIds as $localeId)
			{
				$this->_siteLocales[] = new Locale($localeId);
			}

			if (empty($this->_siteLocales))
			{
				$this->_siteLocales = [new Locale('en_us')];
			}
		}

		return $this->_siteLocales;
	}

	/**
	 * Returns the site's primary locale. The primary locale is whatever is listed first in Settings > Locales in the
	 * control panel.
	 *
	 * @return Locale A [[Locale]] object representing the primary locale.
	 */
	public function getPrimarySiteLocale()
	{
		$locales = $this->getSiteLocales();
		return $locales[0];
	}

	/**
	 * Returns the site's primary locale ID. The primary locale is whatever is listed first in Settings > Locales in the
	 * control panel.
	 *
	 * @return string The primary locale ID.
	 */
	public function getPrimarySiteLocaleId()
	{
		return $this->getPrimarySiteLocale()->getId();
	}

	/**
	 * Returns an array of the site locale IDs. The list of locales is based on whatever was defined in Settings > Locales
	 * in the control panel.
	 *
	 * @return array An array of locale IDs.
	 */
	public function getSiteLocaleIds()
	{
		$locales = $this->getSiteLocales();
		$localeIds = [];

		foreach ($locales as $locale)
		{
			$localeIds[] = $locale->id;
		}

		return $localeIds;
	}

	/**
	 * Returns a list of locales that are editable by the current user.
	 *
	 * @return array
	 */
	public function getEditableLocales()
	{
		if (Craft::$app->isLocalized())
		{
			$locales = $this->getSiteLocales();
			$editableLocales = [];

			foreach ($locales as $locale)
			{
				if (Craft::$app->getUser()->checkPermission('editLocale:'.$locale->getId()))
				{
					$editableLocales[] = $locale;
				}
			}

			return $editableLocales;
		}
		else
		{
			return $this->getSiteLocales();
		}
	}

	/**
	 * Returns an array of the editable locale IDs.
	 *
	 * @return array
	 */
	public function getEditableLocaleIds()
	{
		$locales = $this->getEditableLocales();
		$localeIds = [];

		foreach ($locales as $locale)
		{
			$localeIds[] = $locale->id;
		}

		return $localeIds;
	}

	/**
	 * Returns a locale by its ID.
	 *
	 * @param string $localeId
	 *
	 * @return Locale
	 */
	public function getLocaleById($localeId)
	{
		return new Locale($localeId);
	}

	/**
	 * Adds a new site locale.
	 *
	 * @param string $localeId
	 *
	 * @return bool
	 */
	public function addSiteLocale($localeId)
	{
		$maxSortOrder = Craft::$app->db->createCommand()->select('max(sortOrder)')->from('locales')->queryScalar();
		$affectedRows = Craft::$app->db->createCommand()->insert('locales', ['locale' => $localeId, 'sortOrder' => $maxSortOrder+1]);
		$success = (bool) $affectedRows;

		if ($success)
		{
			$this->_siteLocales[] = new Locale($localeId);

			// Add this locale to each of the category groups
			$categoryLocales = Craft::$app->db->createCommand()
				->select('groupId, urlFormat, nestedUrlFormat')
				->from('categorygroups_i18n')
				->where('locale = :locale', [':locale' => $this->getPrimarySiteLocaleId()])
				->queryAll();

			if ($categoryLocales)
			{
				$newCategoryLocales = [];

				foreach ($categoryLocales as $categoryLocale)
				{
					$newCategoryLocales[] = [$categoryLocale['groupId'], $localeId, $categoryLocale['urlFormat'], $categoryLocale['nestedUrlFormat']];
				}

				Craft::$app->db->createCommand()->insertAll('categorygroups_i18n', ['groupId', 'locale', 'urlFormat', 'nestedUrlFormat'], $newCategoryLocales);
			}

			// Re-save all of the localizable elements
			if (!Craft::$app->tasks->areTasksPending('ResaveAllElements'))
			{
				Craft::$app->tasks->createTask('ResaveAllElements', null, [
					'localizableOnly' => true,
				]);
			}
		}

		return $success;
	}

	/**
	 * Reorders the site's locales.
	 *
	 * @param array $localeIds
	 *
	 * @return bool
	 */
	public function reorderSiteLocales($localeIds)
	{
		$oldPrimaryLocaleId = $this->getPrimarySiteLocaleId();

		foreach ($localeIds as $sortOrder => $localeId)
		{
			Craft::$app->db->createCommand()->update('locales', ['sortOrder' => $sortOrder+1], ['locale' => $localeId]);
		}

		$this->_siteLocales = null;
		$newPrimaryLocaleId = $this->getPrimarySiteLocaleId();

		// Did the primary site locale just change?
		if ($oldPrimaryLocaleId != $newPrimaryLocaleId)
		{
			Craft::$app->config->maxPowerCaptain();

			// Update all of the non-localized elements
			$nonLocalizedElementTypes = [];

			foreach (Craft::$app->elements->getAllElementTypes() as $elementType)
			{
				if (!$elementType->isLocalized())
				{
					$nonLocalizedElementTypes[] = $elementType->getClassHandle();
				}
			}

			if ($nonLocalizedElementTypes)
			{
				$elementIds = Craft::$app->db->createCommand()
					->select('id')
					->from('elements')
					->where(['in', 'type', $nonLocalizedElementTypes])
					->queryColumn();

				if ($elementIds)
				{
					// To be sure we don't hit any unique constraint MySQL errors, first make sure there are no rows for
					// these elements that don't currently use the old primary locale
					$deleteConditions = ['and', ['in', 'elementId', $elementIds], 'locale != :locale'];
					$deleteParams     = [':locale' => $oldPrimaryLocaleId];

					Craft::$app->db->createCommand()->delete('elements_i18n', $deleteConditions, $deleteParams);
					Craft::$app->db->createCommand()->delete('content', $deleteConditions, $deleteParams);

					// Now convert the locales
					$updateColumns    = ['locale' => $newPrimaryLocaleId];
					$updateConditions = ['in', 'elementId', $elementIds];

					Craft::$app->db->createCommand()->update('elements_i18n', $updateColumns, $updateConditions);
					Craft::$app->db->createCommand()->update('content', $updateColumns, $updateConditions);
				}
			}
		}

		return true;
	}

	/**
	 * Deletes a site locale.
	 *
	 * @param string      $localeId          The locale to be deleted.
	 * @param string|null $transferContentTo The locale that should take over the deleted locale’s content.
	 *
	 * @throws \CDbException
	 * @throws \Exception
	 * @return bool Whether the locale was successfully deleted.
	 */
	public function deleteSiteLocale($localeId, $transferContentTo)
	{
		$transaction = Craft::$app->db->getCurrentTransaction() === null ? Craft::$app->db->beginTransaction() : null;

		try
		{
			// Fire an 'onBeforeDeleteLocale' event
			$event = new Event($this, [
				'localeId'          => $localeId,
				'transferContentTo' => $transferContentTo
			]);

			$this->onBeforeDeleteLocale($event);

			// Is the event is giving us the go-ahead?
			if ($event->performAction)
			{
				// Get the section IDs that are enabled for this locale
				$sectionIds = Craft::$app->db->createCommand()
					->select('sectionId')
					->from('sections_i18n')
					->where(['locale' => $localeId])
					->queryColumn();

				// Figure out which ones are *only* enabled for this locale
				$soloSectionIds = [];

				foreach ($sectionIds as $sectionId)
				{
					$sectionLocales = Craft::$app->sections->getSectionLocales($sectionId);

					if (count($sectionLocales) == 1 && $sectionLocales[0]->locale == $localeId)
					{
						$soloSectionIds[] = $sectionId;
					}
				}

				// Did we find any?
				if ($soloSectionIds)
				{
					// Should we enable those for a different locale?
					if ($transferContentTo)
					{
						Craft::$app->db->createCommand()->update(
							'sections_i18n',
							['locale' => $transferContentTo],
							['in', 'sectionId', $soloSectionIds]
						);

						// Get all of the entry IDs in those sections
						$entryIds = Craft::$app->db->createCommand()
							->select('id')
							->from('entries')
							->where(['in', 'sectionId', $soloSectionIds])
							->queryColumn();

						if ($entryIds)
						{
							// Delete their template caches
							Craft::$app->templateCache->deleteCachesByElementId($entryIds);

							// Update the entry tables
							Craft::$app->db->createCommand()->update(
								'content',
								['locale' => $transferContentTo],
								['in', 'elementId', $entryIds]
							);

							Craft::$app->db->createCommand()->update(
								'elements_i18n',
								['locale' => $transferContentTo],
								['in', 'elementId', $entryIds]
							);

							Craft::$app->db->createCommand()->update(
								'entrydrafts',
								['locale' => $transferContentTo],
								['in', 'entryId', $entryIds]
							);

							Craft::$app->db->createCommand()->update(
								'entryversions',
								['locale' => $transferContentTo],
								['in', 'entryId', $entryIds]
							);

							Craft::$app->db->createCommand()->update(
								'relations',
								['sourceLocale' => $transferContentTo],
								['and', ['in', 'sourceId', $entryIds], 'sourceLocale is not null']
							);

							// All the Matrix tables
							$blockIds = Craft::$app->db->createCommand()
								->select('id')
								->from('matrixblocks')
								->where(['in', 'ownerId', $entryIds])
								->queryColumn();

							if ($blockIds)
							{
								Craft::$app->db->createCommand()->update(
									'matrixblocks',
									['ownerLocale' => $transferContentTo],
									['and', ['in', 'id', $blockIds], 'ownerLocale is not null']
								);

								Craft::$app->db->createCommand()->delete(
									'elements_i18n',
									['and', ['in', 'elementId', $blockIds], 'locale = :transferContentTo'],
									[':transferContentTo' => $transferContentTo]
								);

								Craft::$app->db->createCommand()->update(
									'elements_i18n',
									['locale' => $transferContentTo],
									['and', ['in', 'elementId', $blockIds], 'locale = :localeId'],
									[':localeId' => $localeId]
								);

								$matrixTablePrefix = Craft::$app->db->addTablePrefix('matrixcontent_');
								$matrixTablePrefixLength = strlen($matrixTablePrefix);
								$tablePrefixLength = strlen(Craft::$app->db->tablePrefix);

								foreach (Craft::$app->db->getSchema()->getTableNames() as $tableName)
								{
									if (strncmp($tableName, $matrixTablePrefix, $matrixTablePrefixLength) === 0)
									{
										$tableName = substr($tableName, $tablePrefixLength);

										Craft::$app->db->createCommand()->delete(
											$tableName,
											['and', ['in', 'elementId', $blockIds], 'locale = :transferContentTo'],
											[':transferContentTo' => $transferContentTo]
										);

										Craft::$app->db->createCommand()->update(
											$tableName,
											['locale' => $transferContentTo],
											['and', ['in', 'elementId', $blockIds], 'locale = :localeId'],
											[':localeId' => $localeId]
										);
									}
								}

								Craft::$app->db->createCommand()->update(
									'relations',
									['sourceLocale' => $transferContentTo],
									['and', ['in', 'sourceId', $blockIds], 'sourceLocale is not null']
								);
							}
						}
					}
					else
					{
						// Delete those sections
						foreach ($soloSectionIds as $sectionId)
						{
							Craft::$app->sections->deleteSectionById($sectionId);
						}
					}
				}

				// Delete the locale
				$affectedRows = Craft::$app->db->createCommand()->delete('locales', ['locale' => $localeId]);
				$success = (bool) $affectedRows;

				// If it didn't work, rollback the transaction in case something changed in onBeforeDeleteLocale
				if (!$success)
				{
					if ($transaction !== null)
					{
						$transaction->rollback();
					}

					return false;
				}
			}
			else
			{
				$success = false;
			}

			// Commit the transaction regardless of whether we deleted the locale,
			// in case something changed in onBeforeDeleteLocale
			if ($transaction !== null)
			{
				$transaction->commit();
			}
		}
		catch (\Exception $e)
		{
			if ($transaction !== null)
			{
				$transaction->rollback();
			}

			throw $e;
		}

		if ($success)
		{
			// Fire an 'onDeleteLocale' event
			$this->onDeleteLocale(new Event($this, [
				'localeId'          => $localeId,
				'transferContentTo' => $transferContentTo
			]));
		}

		return $success;
	}

	/**
	 * Returns the localization data for a given locale.
	 *
	 * @param string $localeId
	 *
	 * @return LocaleData|null
	 */
	public function getLocaleData($localeId = null)
	{
		if (!$localeId)
		{
			$localeId = Craft::$app->language;
		}

		if (!isset($this->_localeData) || !array_key_exists($localeId, $this->_localeData))
		{
			if (LocaleData::exists($localeId))
			{
				$this->_localeData[$localeId] = LocaleData::getInstance($localeId);
			}
			else
			{
				$this->_localeData[$localeId] = null;
			}
		}

		return $this->_localeData[$localeId];
	}

	// Events
	// -------------------------------------------------------------------------

	/**
	 * Fires an 'onBeforeDeleteLocale' event.
	 *
	 * @param Event $event
	 *
	 * @return null
	 */
	public function onBeforeDeleteLocale(Event $event)
	{
		$this->raiseEvent('onBeforeDeleteLocale', $event);
	}

	/**
	 * Fires an 'onDeleteLocale' event.
	 *
	 * @param Event $event
	 *
	 * @return null
	 */
	public function onDeleteLocale(Event $event)
	{
		$this->raiseEvent('onDeleteLocale', $event);
	}
}
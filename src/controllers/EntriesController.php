<?php
namespace Craft;

/**
 * Handles entry tasks
 */
class EntriesController extends BaseController
{
	protected $allowAnonymous = array('actionSaveEntry');

	/**
	 * Edit an entry.
	 *
	 * @param array $variables
	 * @throws HttpException
	 */
	public function actionEditEntry(array $variables = array())
	{
		$this->_prepEditEntryVariables($variables);

		if (craft()->hasPackage(CraftPackage::Users) && $variables['section']->type != SectionType::Single)
		{
			// Get all the possible authors
			$currentUser = craft()->userSession->getUser();
			$excludeAuthorIds = 'not '.$currentUser->id;

			if ($variables['entry']->authorId && $variables['entry']->authorId != $currentUser->id)
			{
				$excludeAuthorIds = array('and', $excludeAuthorIds, 'not '.$variables['entry']->authorId);
			}

			$authorOptionCriteria = craft()->elements->getCriteria(ElementType::User);
			$authorOptionCriteria->can = 'createEntries:'.$variables['section']->id;
			$authorOptionCriteria->id = $excludeAuthorIds;
			$authorOptions = $authorOptionCriteria->find();

			// List the current author first
			if ($variables['entry']->authorId && $variables['entry']->authorId != $currentUser->id)
			{
				$currentAuthor = craft()->users->getUserById($variables['entry']->authorId);

				if ($currentAuthor)
				{
					array_unshift($authorOptions, $currentAuthor);
				}
			}

			// Then the current user
			if (!$variables['entry']->authorId || $variables['entry']->authorId == $currentUser->id)
			{
				array_unshift($authorOptions, $currentUser);
			}

			$variables['authorOptions'] = array();

			foreach ($authorOptions as $authorOption)
			{
				$authorLabel = $authorOption->username;
				$authorFullName = $authorOption->getFullName();

				if ($authorFullName)
				{
					$authorLabel .= ' - '.$authorFullName;
				}

				$variables['authorOptions'][] = array('label' => $authorLabel, 'value' => $authorOption->id);
			}
		}

		if (craft()->hasPackage(CraftPackage::PublishPro) && $variables['section']->type == SectionType::Structure)
		{
			// Get all the possible parent options
			$parentOptionCriteria = craft()->elements->getCriteria(ElementType::Entry);
			$parentOptionCriteria->sectionId = $variables['section']->id;
			$parentOptionCriteria->status = null;
			$parentOptionCriteria->localeEnabled = null;
			$parentOptionCriteria->limit = null;

			if ($variables['section']->maxLevels)
			{
				$parentOptionCriteria->level = '< '.$variables['section']->maxLevels;
			}

			if ($variables['entry']->id)
			{
				$idParam = array('and', 'not '.$variables['entry']->id);

				$descendantCriteria = craft()->elements->getCriteria(ElementType::Entry);
				$descendantCriteria->descendantOf = $variables['entry'];
				$descendantCriteria->status = null;
				$descendantCriteria->localeEnabled = null;
				$descendantIds = $descendantCriteria->ids();

				foreach ($descendantIds as $id)
				{
					$idParam[] = 'not '.$id;
				}

				$parentOptionCriteria->id = $idParam;
			}

			$parentOptions = $parentOptionCriteria->find();

			$variables['parentOptions'] = array(array(
				'label' => '', 'value' => '0'
			));

			foreach ($parentOptions as $parentOption)
			{
				$label = '';

				for ($i = 1; $i < $parentOption->level; $i++)
				{
					$label .= '    ';
				}

				$label .= $parentOption->title;

				$variables['parentOptions'][] = array('label' => $label, 'value' => $parentOption->id);
			}

			// Get the initially selected parent
			$variables['parentId'] = craft()->request->getParam('parentId');

			if ($variables['parentId'] === null && $variables['entry']->id)
			{
				$parentIdCriteria = craft()->elements->getCriteria(ElementType::Entry);
				$parentIdCriteria->ancestorOf =$variables['entry'];
				$parentIdCriteria->ancestorDist = 1;
				$parentIdCriteria->status = null;
				$parentIdCriteria->localeEnabled = null;
				$parentIds = $parentIdCriteria->ids();

				if ($parentIds)
				{
					$variables['parentId'] = $parentIds[0];
				}
			}
		}

		// Get the enabled locales
		if (craft()->hasPackage(CraftPackage::Localize))
		{
			if ($variables['entry']->id)
			{
				$variables['enabledLocales'] = craft()->elements->getEnabledLocalesForElement($variables['entry']->id);
			}
			else
			{
				$variables['enabledLocales'] = array();

				foreach ($variables['section']->getLocales() as $locale)
				{
					if ($locale->enabledByDefault)
					{
						$variables['enabledLocales'][] = $locale->locale;
					}
				}
			}
		}

		// Page title w/ revision label
		if (craft()->hasPackage(CraftPackage::PublishPro))
		{
			switch ($variables['entry']->getClassHandle())
			{
				case 'EntryDraft':
				{
					$variables['revisionLabel'] = Craft::t('Draft {id}', array('id' => $variables['draftId']));
					break;
				}

				case 'EntryVersion':
				{
					$variables['revisionLabel'] = Craft::t('Version {id}', array('id' => $variables['versionId']));
					break;
				}

				default:
				{
					$variables['revisionLabel'] = Craft::t('Current');
				}
			}
		}

		if (!$variables['entry']->id)
		{
			$variables['title'] = Craft::t('Create a new entry');
		}
		else
		{
			$variables['title'] = $variables['entry']->title;

			if (craft()->hasPackage(CraftPackage::PublishPro) && $variables['entry']->getClassHandle() != 'Entry')
			{
				$variables['title'] .= ' <span class="hidden">('.$variables['revisionLabel'].')</span>';
			}
		}

		// Breadcrumbs
		$variables['crumbs'] = array(
			array('label' => Craft::t('Entries'), 'url' => UrlHelper::getUrl('entries'))
		);

		if ($variables['section']->type == SectionType::Single)
		{
			$variables['crumbs'][] = array('label' => 'Singles', 'url' => UrlHelper::getUrl('entries/singles'));
		}
		else
		{
			$variables['crumbs'][] = array('label' => $variables['section']->name, 'url' => UrlHelper::getUrl('entries/'.$variables['section']->handle));

			if ($variables['section']->type == SectionType::Structure)
			{
				foreach ($variables['entry']->getAncestors() as $ancestor)
				{
					$variables['crumbs'][] = array('label' => $ancestor->title, 'url' => $ancestor->getCpEditUrl());
				}
			}
		}

		// Multiple entry types?
		$entryTypes = $variables['section']->getEntryTypes();

		if (count($entryTypes) > 1)
		{
			$variables['showEntryTypes'] = true;

			foreach ($entryTypes as $entryType)
			{
				$variables['entryTypeOptions'][] = array('label' => Craft::t($entryType->name), 'value' => $entryType->id);
			}

			craft()->templates->includeJsResource('js/EntryTypeSwitcher.js');
			craft()->templates->includeJs('new Craft.EntryTypeSwitcher();');
		}
		else
		{
			$variables['showEntryTypes'] = false;
		}

		// Enable preview mode?
		$variables['showPreviewBtn'] = false;

		if (!craft()->request->isMobileBrowser(true) && $variables['section']->hasUrls)
		{
			// Make sure the section's template actually exists
			$templatesPath = craft()->path->getTemplatesPath();
			craft()->path->setTemplatesPath(craft()->path->getSiteTemplatesPath());

			try
			{
				$templateExists = (bool) craft()->templates->findTemplate($variables['section']->template);
			}
			catch (TemplateLoaderException $e)
			{
				$templateExists = false;
			}

			craft()->path->setTemplatesPath($templatesPath);

			if ($templateExists)
			{
				craft()->templates->includeJsResource('js/EntryPreviewMode.js');
				craft()->templates->includeJs('Craft.entryPreviewMode = new Craft.EntryPreviewMode('.JsonHelper::encode($variables['entry']->getUrl()).', "'.$variables['entry']->locale.'");');
				$variables['showPreviewBtn'] = true;
			}
		}

		// Set the "Continue Editing" URL
		$variables['continueEditingUrl'] = 'entries/'.$variables['section']->handle.'/{id}' .
			(isset($variables['draftId']) ? '/drafts/'.$variables['draftId'] : '') .
			(craft()->hasPackage(CraftPackage::Localize) && craft()->getLanguage() != $variables['localeId'] ? '/'.$variables['localeId'] : '');

		// Include translations
		craft()->templates->includeTranslations('Live Preview');

		// Render the template!
		craft()->templates->includeCssResource('css/entry.css');
		$this->renderTemplate('entries/_edit', $variables);
	}

	/**
	 *
	 */
	public function actionSwitchEntryType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$variables['sectionId'] = craft()->request->getRequiredPost('sectionId');
		$variables['entry'] = $this->_populateEntryModel();
		$variables['showEntryTypes'] = true;

		$this->_prepEditEntryVariables($variables);

		$paneHtml = craft()->templates->render('_includes/tabs', $variables) .
			craft()->templates->render('entries/_fields', $variables);

		$this->returnJson(array(
			'paneHtml' => $paneHtml,
			'headHtml' => craft()->templates->getHeadHtml(),
			'footHtml' => craft()->templates->getFootHtml(),
		));
	}

	/**
	 * Previews an entry.
	 */
	public function actionPreviewEntry()
	{
		$this->requirePostRequest();

		craft()->setLanguage(craft()->request->getPost('locale'));

		$entry = $this->_populateEntryModel();
		$section = $entry->getSection();
		$type = $entry->getType();

		if ($section && $type)
		{
			if (!$entry->postDate)
			{
				$entry->postDate = new DateTime();
			}

			craft()->templates->getTwig()->disableStrictVariables();

			$this->renderTemplate($section->template, array(
				'entry' => $entry
			));
		}

		craft()->end();
	}

	/**
	 * Saves an entry.
	 */
	public function actionSaveEntry()
	{
		$this->requirePostRequest();

		$entry = $this->_populateEntryModel();

		if (!$entry->id)
		{
			// If there is a logged in user, make sure they have create entry permissions.
			if (craft()->userSession->isLoggedIn())
			{
				// Make sure the user is allowed to create entries in this section
				craft()->userSession->requirePermission('createEntries:'.$entry->sectionId);
			}

			// Make sure the user is allowed to publish entries in this section, or that this is disabled
			if ($entry->enabled && !craft()->userSession->checkPermission('publishEntries:'.$entry->sectionId))
			{
				$entry->enabled = false;
			}
		}
		else
		{
			// Make sure the user is allowed to edit entries in this section
			craft()->userSession->requirePermission('editEntries:'.$entry->sectionId);

			if ($entry->enabled)
			{
				// Make sure the user is allowed to edit live entries in this section
				craft()->userSession->requirePermission('publishEntries:'.$entry->sectionId);
			}
		}

		if (craft()->entries->saveEntry($entry))
		{
			if (craft()->request->isAjaxRequest())
			{
				$return['success']   = true;
				$return['title']     = $entry->title;
				$return['cpEditUrl'] = $entry->getCpEditUrl();
				$return['author']    = $entry->getAuthor()->getAttributes();
				$return['postDate']  = ($entry->postDate ? $entry->postDate->localeDate() : null);

				$this->returnJson($return);
			}
			else
			{
				craft()->userSession->setNotice(Craft::t('Entry saved.'));

				// TODO: Remove for 2.0
				if (isset($_POST['redirect']) && mb_strpos($_POST['redirect'], '{entryId}') !== false)
				{
					Craft::log('The {entryId} token within the ‘redirect’ param on entries/saveEntry requests has been deprecated. Use {id} instead.', LogLevel::Warning);
					$_POST['redirect'] = str_replace('{entryId}', '{id}', $_POST['redirect']);
				}

				$this->redirectToPostedUrl($entry);
			}
		}
		else
		{
			if (craft()->request->isAjaxRequest())
			{
				$this->returnJson(array(
					'errors' => $entry->getErrors(),
				));
			}
			else
			{
				craft()->userSession->setError(Craft::t('Couldn’t save entry.'));

				// Send the entry back to the template
				craft()->urlManager->setRouteVariables(array(
					'entry' => $entry
				));
			}
		}
	}

	/**
	 * Deletes an entry.
	 */
	public function actionDeleteEntry()
	{
		$this->requirePostRequest();

		$entry = $this->_populateEntryModel();
		$section = $entry->getSection();
		craft()->userSession->requirePermission('deleteEntries:'.$section->id);

		$entryId = $entry->id;

		if (craft()->entries->deleteEntryById($entryId))
		{
			craft()->userSession->setNotice(Craft::t('Entry deleted.'));
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t delete entry.'));
		}

		$this->redirectToPostedUrl();
	}

	/**
	 * Preps entry edit variables.
	 *
	 * @access private
	 * @param array &$variables
	 * @throws HttpException
	 * @throws Exception
	 */
	private function _prepEditEntryVariables(&$variables)
	{
		if (!empty($variables['sectionHandle']))
		{
			$variables['section'] = craft()->sections->getSectionByHandle($variables['sectionHandle']);
		}
		else if (!empty($variables['sectionId']))
		{
			$variables['section'] = craft()->sections->getSectionById($variables['sectionId']);
		}

		if (empty($variables['section']))
		{
			throw new HttpException(404);
		}

		$variables['permissionSuffix'] = ':'.$variables['section']->id;

		// Make sure the user is allowed to edit entries in this section
		craft()->userSession->requirePermission('editEntries'.$variables['permissionSuffix']);

		if (craft()->hasPackage(CraftPackage::Localize))
		{
			// Only use the locales that the user has access to
			$sectionLocaleIds = array_keys($variables['section']->getLocales());
			$editableLocaleIds = craft()->i18n->getEditableLocaleIds();
			$variables['localeIds'] = array_merge(array_intersect($sectionLocaleIds, $editableLocaleIds));
		}
		else
		{
			$variables['localeIds'] = array(craft()->i18n->getPrimarySiteLocaleId());
		}

		if (!$variables['localeIds'])
		{
			throw new HttpException(404);
		}

		if (empty($variables['localeId']))
		{
			$variables['localeId'] = craft()->language;

			if (!in_array($variables['localeId'], $variables['localeIds']))
			{
				$variables['localeId'] = $variables['localeIds'][0];
			}
		}
		else
		{
			// Make sure they were requesting a valid locale
			if (!in_array($variables['localeId'], $variables['localeIds']))
			{
				throw new HttpException(404);
			}
		}

		// Now let's set up the actual entry
		if (empty($variables['entry']))
		{
			if (!empty($variables['entryId']))
			{
				if (!empty($variables['draftId']))
				{
					$variables['entry'] = craft()->entryRevisions->getDraftById($variables['draftId']);
				}
				else if (!empty($variables['versionId']))
				{
					$variables['entry'] = craft()->entryRevisions->getVersionById($variables['versionId']);
				}
				else
				{
					$variables['entry'] = craft()->entries->getEntryById($variables['entryId'], $variables['localeId']);
				}

				if (!$variables['entry'])
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['entry'] = new EntryModel();
				$variables['entry']->sectionId = $variables['section']->id;
				$variables['entry']->authorId = craft()->userSession->getUser()->id;
				$variables['entry']->enabled = true;
			}
		}

		// More permission enforcement
		if (!$variables['entry']->id)
		{
			craft()->userSession->requirePermission('createEntries'.$variables['permissionSuffix']);
		}
		else if ($variables['entry']->authorId != craft()->userSession->getUser()->id)
		{
			craft()->userSession->requirePermission('editPeerEntries'.$variables['permissionSuffix']);
		}

		if ($variables['entry']->id && $variables['entry']->getClassHandle() == 'EntryDraft')
		{
			if ($variables['entry']->creatorId != craft()->userSession->getUser()->id)
			{
				craft()->userSession->requirePermission('editPeerEntryDrafts'.$variables['permissionSuffix']);
			}
		}

		// Entry type

		// Override the entry type?
		$typeId = craft()->request->getParam('typeId');

		if ($typeId)
		{
			$variables['entry']->typeId = $typeId;
		}

		// Save the entry type locally
		$variables['entryType'] = $variables['entry']->getType();

		if (!$variables['entryType'])
		{
			throw new Exception(Craft::t('No entry types are available for this entry.'));
		}

		// Tabs
		$variables['tabs'] = array();

		foreach ($variables['entryType']->getFieldLayout()->getTabs() as $index => $tab)
		{
			// Do any of the fields on this tab have errors?
			$hasErrors = false;

			if ($variables['entry']->hasErrors())
			{
				foreach ($tab->getFields() as $field)
				{
					if ($variables['entry']->getErrors($field->getField()->handle))
					{
						$hasErrors = true;
						break;
					}
				}
			}

			$variables['tabs'][] = array(
				'label' => $tab->name,
				'url'   => '#tab'.($index+1),
				'class' => ($hasErrors ? 'error' : null)
			);
		}

		// Settings tab
		if ($variables['section']->type != SectionType::Single)
		{
			$hasErrors = ($variables['entry']->hasErrors() && (
				$variables['entry']->getErrors('slug') ||
				$variables['entry']->getErrors('postDate') ||
				$variables['entry']->getErrors('expiryDate')
			));
		}
	}

	/**
	 * Populates an EntryModel with post data.
	 *
	 * @access private
	 * @throws Exception
	 * @return EntryModel
	 */
	private function _populateEntryModel()
	{
		$entryId = craft()->request->getPost('entryId');
		$localeId = craft()->request->getPost('locale');

		if ($entryId)
		{
			$entry = craft()->entries->getEntryById($entryId, $localeId);

			if (!$entry)
			{
				throw new Exception(Craft::t('No entry exists with the ID “{id}”', array('id' => $entryId)));
			}
		}
		else
		{
			$entry = new EntryModel();

			if ($localeId)
			{
				$entry->locale = $localeId;
			}
		}

		// Set the entry attributes, defaulting to the existing values for whatever is missing from the post data
		$entry->sectionId     = craft()->request->getPost('sectionId', $entry->sectionId);
		$entry->typeId        = craft()->request->getPost('typeId',    $entry->typeId);

		// Check for the author in POST first.
		$authorId = craft()->request->getPost('author');
		if (!$authorId)
		{
			// If the entry has an existing authorId, use that.
			if ($entry->authorId)
			{
				$authorId = $entry->authorId;
			}
			else
			{
				// If the there is a currently logged in user, use their authorId.
				if (craft()->userSession->isLoggedIn())
				{
					$authorId = craft()->userSession->getUser()->id;
				}
				else
				{
					// Maybe anonymous entry creation is enabled for this section?
					if (!$entryId && $entry->sectionId)
					{
						$section = craft()->sections->getSectionById($entry->sectionId);
						if ($section->defaultAuthorId)
						{
							// We found a defaultAuthor
							$defaultAuthor = craft()->users->getUserById($section->defaultAuthorId);
							$authorId = $defaultAuthor->id;
						}
						else
						{
							throw new HttpException(403);
						}
					}
				}
			}
		}

		$entry->authorId      = $authorId;
		$entry->slug          = craft()->request->getPost('slug', $entry->slug);
		$entry->postDate      = (($postDate   = craft()->request->getPost('postDate'))   ? DateTime::createFromString($postDate,   craft()->timezone) : $entry->postDate);
		$entry->expiryDate    = (($expiryDate = craft()->request->getPost('expiryDate')) ? DateTime::createFromString($expiryDate, craft()->timezone) : null);
		$entry->enabled       = (bool) craft()->request->getPost('enabled', $entry->enabled);
		$entry->localeEnabled = (bool) craft()->request->getPost('localeEnabled', $entry->localeEnabled);

		$entry->getContent()->title = craft()->request->getPost('title', $entry->title);

		$entry->setContentFromPost('fields');

		$entry->parentId = craft()->request->getPost('parentId');

		return $entry;
	}
}

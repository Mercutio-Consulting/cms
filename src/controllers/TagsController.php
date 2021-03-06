<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\controllers;

use Craft;
use craft\elements\Tag;
use craft\helpers\Db;
use craft\helpers\Search;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\TagGroup;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The TagsController class is a controller that handles various tag and tag group related tasks such as displaying,
 * saving, deleting, searching and creating tags and tag groups in the control panel.
 *
 * Note that all actions in the controller require an authenticated Craft session via [[Controller::allowAnonymous]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class TagsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Called before displaying the tag settings index page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $tagGroups = Craft::$app->getTags()->getAllTagGroups();

        return $this->renderTemplate('settings/tags/index', [
            'tagGroups' => $tagGroups
        ]);
    }

    /**
     * Edit a tag group.
     *
     * @param int|null      $tagGroupId The tag group’s ID, if any.
     * @param TagGroup|null $tagGroup   The tag group being edited, if there were any validation errors.
     *
     * @return Response
     * @throws NotFoundHttpException if the requested tag group cannot be found
     */
    public function actionEditTagGroup(int $tagGroupId = null, TagGroup $tagGroup = null): Response
    {
        $this->requireAdmin();

        if ($tagGroupId !== null) {
            if ($tagGroup === null) {
                $tagGroup = Craft::$app->getTags()->getTagGroupById($tagGroupId);

                if (!$tagGroup) {
                    throw new NotFoundHttpException('Tag group not found');
                }
            }

            $title = $tagGroup->name;
        } else {
            if ($tagGroup === null) {
                $tagGroup = new TagGroup();
            }

            $title = Craft::t('app', 'Create a new tag group');
        }

        // Breadcrumbs
        $crumbs = [
            [
                'label' => Craft::t('app', 'Settings'),
                'url' => UrlHelper::url('settings')
            ],
            [
                'label' => Craft::t('app', 'Tags'),
                'url' => UrlHelper::url('settings/tags')
            ]
        ];

        // Tabs
        $tabs = [
            'settings' => [
                'label' => Craft::t('app', 'Settings'),
                'url' => '#taggroup-settings'
            ],
            'fieldLayout' => [
                'label' => Craft::t('app', 'Field Layout'),
                'url' => '#taggroup-fieldlayout'
            ]
        ];

        return $this->renderTemplate('settings/tags/_edit', [
            'tagGroupId' => $tagGroupId,
            'tagGroup' => $tagGroup,
            'title' => $title,
            'crumbs' => $crumbs,
            'tabs' => $tabs
        ]);
    }

    /**
     * Save a tag group.
     *
     * @return Response|null
     */
    public function actionSaveTagGroup()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $tagGroup = new TagGroup();

        // Set the simple stuff
        $tagGroup->id = Craft::$app->getRequest()->getBodyParam('tagGroupId');
        $tagGroup->name = Craft::$app->getRequest()->getBodyParam('name');
        $tagGroup->handle = Craft::$app->getRequest()->getBodyParam('handle');

        // Set the field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Tag::class;
        $tagGroup->setFieldLayout($fieldLayout);

        // Save it
        if (!Craft::$app->getTags()->saveTagGroup($tagGroup)) {
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save the tag group.'));

            // Send the tag group back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'tagGroup' => $tagGroup
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Tag group saved.'));

        return $this->redirectToPostedUrl($tagGroup);
    }

    /**
     * Deletes a tag group.
     *
     * @return Response
     */
    public function actionDeleteTagGroup(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireAdmin();

        $sectionId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Craft::$app->getTags()->deleteTagGroupById($sectionId);

        return $this->asJson(['success' => true]);
    }

    /**
     * Searches for tags.
     *
     * @return Response
     */
    public function actionSearchForTags(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $search = Craft::$app->getRequest()->getBodyParam('search');
        $tagGroupId = Craft::$app->getRequest()->getBodyParam('tagGroupId');
        $excludeIds = Craft::$app->getRequest()->getBodyParam('excludeIds', []);
        $allowSimilarTags = Craft::$app->getConfig()->get('allowSimilarTags');

        $tags = Tag::find()
            ->groupId($tagGroupId)
            ->title(Db::escapeParam($search).'*')
            ->where(['not', ['elements.id' => $excludeIds]])
            ->all();

        $return = [];
        $exactMatches = [];
        $tagTitleLengths = [];
        $exactMatch = false;

        if ($allowSimilarTags) {
            $search = Search::normalizeKeywords($search, [], false);
        } else {
            $search = Search::normalizeKeywords($search);
        }

        foreach ($tags as $tag) {
            $return[] = [
                'id' => $tag->id,
                'title' => $tag->title
            ];

            $tagTitleLengths[] = StringHelper::length($tag->title);

            if ($allowSimilarTags) {
                $title = Search::normalizeKeywords($tag->title, [], false);
            } else {
                $title = Search::normalizeKeywords($tag->title);
            }

            if ($title == $search) {
                $exactMatches[] = 1;
                $exactMatch = true;
            } else {
                $exactMatches[] = 0;
            }
        }

        array_multisort($exactMatches, SORT_DESC, $tagTitleLengths, $return);

        return $this->asJson([
            'tags' => $return,
            'exactMatch' => $exactMatch
        ]);
    }

    /**
     * Creates a new tag.
     *
     * @return Response
     */
    public function actionCreateTag(): Response
    {
        $this->requireLogin();
        $this->requireAcceptsJson();

        $tag = new Tag();
        $tag->groupId = Craft::$app->getRequest()->getRequiredBodyParam('groupId');
        $tag->title = Craft::$app->getRequest()->getRequiredBodyParam('title');
        $tag->validateCustomFields = false;

        if (Craft::$app->getElements()->saveElement($tag)) {
            return $this->asJson([
                'success' => true,
                'id' => $tag->id
            ]);
        } else {
            return $this->asJson([
                'success' => false
            ]);
        }
    }
}

<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\elements;

use Craft;
use craft\app\base\Element;
use craft\app\base\FieldLayoutTrait;
use craft\app\elements\db\GlobalSetQuery;
use craft\app\helpers\UrlHelper;

/**
 * The GlobalSet class is responsible for implementing and defining globals as a native element type in
 * Craft.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class GlobalSet extends Element
{
	// Traits
	// =========================================================================

	use FieldLayoutTrait;

	// Properties
	// =========================================================================

	/**
	 * @var string Name
	 */
	public $name;

	/**
	 * @var string Handle
	 */
	public $handle;

	/**
	 * @var integer Field layout ID
	 */
	public $fieldLayoutId;

	/**
	 * @var string The element type that global sets' field layouts should be associated with.
	 */
	private $_fieldLayoutElementClass = 'craft\app\elements\GlobalSet';

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc ElementInterface::hasContent()
	 *
	 * @return bool
	 */
	public static function hasContent()
	{
		return true;
	}

	/**
	 * @inheritDoc ElementInterface::isLocalized()
	 *
	 * @return bool
	 */
	public static function isLocalized()
	{
		return true;
	}

	/**
	 * @inheritdoc
	 *
	 * @return GlobalSetQuery The newly created [[GlobalSetQuery]] instance.
	 */
	public static function find()
	{
		return new GlobalSetQuery(get_called_class());
	}

	/**
	 * @inheritDoc ElementInterface::populateElementModel()
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public static function populateElementModel($row)
	{
		return GlobalSet::populateModel($row);
	}

	// Instance Methods
	// -------------------------------------------------------------------------

	/**
	 * Use the global set's name as its string representation.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->name;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		$rules = parent::rules();

		$rules[] = [['handle'], 'craft\\app\\validators\\Handle', 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']];
		$rules[] = [['fieldLayoutId'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true];
		$rules[] = [['name', 'handle'], 'string', 'max' => 255];

		return $rules;
	}

	/**
	 * @inheritDoc ElementInterface::getCpEditUrl()
	 *
	 * @return string|false
	 */
	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('globals/'.$this->handle);
	}
}
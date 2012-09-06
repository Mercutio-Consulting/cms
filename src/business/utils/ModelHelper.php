<?php
namespace Blocks;

/**
 *
 */
class ModelHelper
{
	/*
	 * Gets the attribute type.
	 *
	 * @static
	 * @param mixed $config
	 * @return string
	 */
	public static function getAttributeType($config)
	{
		return is_string($config) ? $config : (isset($config['type']) ? $config['type'] : (isset($config[0]) ? $config[0] : AttributeType::Varchar));
	}

	/**
	 * Populates any default values that are defined for a model.
	 *
	 * @static
	 * @param BaseModel|BaseRecord $model
	 */
	public static function populateAttributeDefaults($model)
	{
		foreach ($model->defineAttributes() as $name => $config)
		{
			if (is_array($config) && isset($config['default']))
				$model->setAttribute($name, $config['default']);
		}
	}

	/**
	 * Returns the rules array used by CModel.
	 *
	 * @static
	 * @param BaseModel|BaseRecord $model
	 * @return array
	 */
	public static function getRules($model)
	{
		$rules = array();

		$uniques = array();
		$required = array();
		$emails = array();
		$urls = array();
		$strictLengths = array();
		$minLengths = array();
		$maxLengths = array();

		$integerTypes = array(AttributeType::Number, AttributeType::TinyInt, AttributeType::SmallInt, AttributeType::MediumInt, AttributeType::Int, AttributeType::BigInt);
		$numberTypes = $integerTypes;
		$numberTypes[] = AttributeType::Decimal;

		$attributes = $model->defineAttributes();

		foreach ($attributes as $name => $config)
		{
			$type = static::getAttributeType($config);

			// Catch handles, email addresses, languages and URLs before running normalizeAttributeConfig, since 'type' will get changed to VARCHAR
			if ($type == AttributeType::Handle)
			{
				$reservedWords = isset($config['reservedWords']) ? ArrayHelper::stringToArray($config['reservedWords']) : array();
				$rules[] = array($name, 'Blocks\HandleValidator', 'reservedWords' => $reservedWords);
			}

			if ($type === AttributeType::UnixTimeStamp)
				$rules[] = array($name, 'Blocks\DateTimeValidator');

			if ($type == AttributeType::Language)
				$rules[] = array($name, 'Blocks\LanguageValidator');

			if ($type == AttributeType::Email)
				$emails[] = $name;

			if ($type == AttributeType::Url)
				$urls[] = $name;

			// Remember if it's a license key
			$isLicenseKey = ($type == AttributeType::LicenseKey);

			$config = DbHelper::normalizeAttributeConfig($config);

			// Uniques
			if (!empty($config['unique']))
				$uniques[] = $name;

			// Only enforce 'required' validation if there's no default value
			if (isset($config['required']) && $config['required'] === true && !isset($config['default']))
				$required[] = $name;

			// Numbers
			if (in_array($config['type'], $numberTypes) && $type !== AttributeType::UnixTimeStamp)
			{
				$rule = array($name, 'Blocks\LocaleNumberValidator');

				if (isset($config['min']) && is_numeric($config['min']))
					$rule['min'] = $config['min'];

				if (isset($config['max']) && is_numeric($config['max']))
					$rule['max'] = $config['max'];

				if (($config['type'] == AttributeType::Number && empty($config['decimals'])) || in_array($config['type'], $integerTypes))
					$rule['integerOnly'] = true;

				$rules[] = $rule;
			}

			// Enum attribute values
			if ($config['type'] == AttributeType::Enum)
			{
				$values = ArrayHelper::stringToArray($config['values']);
				$rules[] = array($name, 'in', 'range' => $values);
			}

			// License keys' length=36 is redundant in the context of validation, since matchPattern already enforces 36 chars
			if ($isLicenseKey)
				unset($config['length']);

			// Strict, min, and max lengths
			if (isset($config['length']) && is_numeric($config['length']))
				$strictLengths[(string)$config['length']][] = $name;
			else
			{
				// Only worry about min- and max-lengths if a strict length isn't set
				if (isset($config['minLength']) && is_numeric($config['minLength']))
					$minLengths[(string)$config['minLength']][] = $name;

				if (isset($config['maxLength']) && is_numeric($config['maxLength']))
					$maxLengths[(string)$config['maxLength']][] = $name;
			}

			// Regex pattern matching
			if (!empty($config['matchPattern']))
				$rules[] = array($name, 'match', 'pattern' => $config['matchPattern']);
		}

		// Catch any unique indexes, if this is a BaseRecord instance
		if ($model instanceof BaseRecord)
		{
			foreach ($model->defineIndexes() as $config)
			{
				if (!empty($config['unique']))
				{
					$columns = ArrayHelper::stringToArray($config['columns']);

					if (count($columns) == 1)
					{
						$uniques[] = $columns[0];
					}
					else
					{
						$initialColumn = array_shift($columns);
						$rules[] = array($initialColumn, 'Blocks\CompositeUniqueValidator', 'with' => implode(',', $columns));
					}
				}
			}
		}

		if ($uniques)
			$rules[] = array(implode(',', $uniques), 'unique');

		if ($required)
			$rules[] = array(implode(',', $required), 'required');

		if ($emails)
			$rules[] = array(implode(',', $emails), 'email');

		if ($urls)
			$rules[] = array(implode(',', $urls), 'url', 'defaultScheme' => 'http');

		if ($strictLengths)
		{
			foreach ($strictLengths as $strictLength => $attributeNames)
			{
				$rules[] = array(implode(',', $attributeNames), 'length', 'is' => (int)$strictLength);
			}
		}

		if ($minLengths)
		{
			foreach ($minLengths as $minLength => $attributeNames)
			{
				$rules[] = array(implode(',', $attributeNames), 'length', 'min' => (int)$minLength);
			}
		}

		if ($maxLengths)
		{
			foreach ($maxLengths as $maxLength => $attributeNames)
			{
				$rules[] = array(implode(',', $attributeNames), 'length', 'max' => (int)$maxLength);
			}
		}

		$rules[] = array(implode(',', array_keys($attributes)), 'safe', 'on' => 'search');

		return $rules;
	}
}

<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Interfaces;


/**
 * Interface InputInterface
 * @package Key\Interfaces
 */
interface InputInterface
{
    const TYPE_STRING = 'string';
    const TYPE_INT = 'int';
    const TYPE_FLOAT = 'float';
    const TYPE_ARRAY = 'array';
    //const TYPE_PAGINATION = 'pagination';
    const TYPE_FILE = 'file';
    const TYPE_DATETIME = 'datetime';
    const TYPE_MIXED = 'mixed';
    const TYPE_AUTO = 'auto';

    /**
     * Set container.
     *
     * @param \Key\Container $app
     * @return $this
     */
    public function setApp($app);

    /**
     * Determine if the parameter is required
     * @return bool
     */
    public function isRequired();

    /**
     * Get the description of the input
     * @return string
     */
    public function getDescription();

    /**
     * Get the type of the input
     * @return string
     */
    public function getType();

    /**
     * Get the enum of the input
     * @return Collection
     * @deprecated @see $this->getMap()
     */
    public function getEnum();

    /**
     * Get the map of the input
     *
     * @return mixed
     */
    public function getMap();

    /**
     * Get the default value of the input
     *
     * @return mixed
     */
    public function getDefaultValue();

    /**
     * Get the fixed value of the input
     * @return mixed
     */
    public function getFixedValue();

    /**
     * Get the format of the input
     *
     * @return mixed
     */
    public function getFormat();

    /**
     * Determine the type is valid and match the current input
     * @param string $type The type of the input
     * @return bool
     * @exception \RuntimeException
     */
    public function isType($type);

    /**
     * Determine if the input type is 'string'
     * @return bool
     */
    public function isStringType();

    /**
     * Determine if the input type is 'int'
     * @return bool
     */
    public function isIntType();

    /**
     * Determine if the input type is 'float'
     * @return bool
     */
    public function isFloatType();

    /**
     * Determine if the input type is 'array'
     * @return bool
     */
    public function isArrayType();

    /**
     * Determine if the input type is 'pagination'
     * @return bool
     */
    //public function isPaginationType();

    /**
     * Determine if the input type is 'file'
     * @return bool
     */
    public function isFileType();

    /**
     * Determine if the input type is 'datetime'
     * @return bool
     */
    public function isDatetimeType();

    /**
     * Validate the value
     *
     * @return int Validation result code
     */
    public function validate();

    /**
     * Get valid value; if invalid, it returns null.
     *
     * @return mixed
     */
    public function getValidValue();

    /**
     * Get invalid code phrase.
     *
     * @return string
     */
    public function getPhrase();

    /**
     * Convert the value to match the input type.
     *
     * @return mixed
     */
    public function convert();
}
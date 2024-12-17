<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Inputs;


/**
 * Class EmailInput
 * @package Key\Inputs
 */
class EmailInput extends StringInput
{
    const EMAIL_REGEXP = "/^[A-Za-z0-9_\\-\\+\\.]+@[a-zA-Z0-9_-]+(\\.[a-zA-Z0-9_-]+)+$/";

    /**
     * Validate the value for the column field.
     *
     * @return bool
     */
    public function validate()
    {
        $this->validatedCode = static::VALID_CODE_SUCCESS;
        if (strlen($this->value) == 0 && $this->isRequired()) {
            $this->validatedCode = static::INVALID_CODE_REQUIRED;
            return $this->validatedCode;
        }

        // Pattern verify
        if (strlen($this->value) > 0 && !preg_match(static::EMAIL_REGEXP, $this->value)) {
            $this->validatedCode = static::INVALID_CODE_FORMAT;
            return $this->validatedCode;
        }

        return $this->validatedCode;
    }
}

<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2023 yidianzhishi.com
 * @version 1.0.0
 * @link https://www.yidianzhishi.com
 */
namespace Key\Inputs;


/**
 * 动态类型
 * Class DynamicInput
 * @package Key\Inputs
 */
class DynamicInput extends Input
{
    public function __construct($name, $value, $inputConfig = null, $validatedData = null, $parent = null)
    {
        parent::__construct($name, $value, $inputConfig, $validatedData, $parent);
        if (isset($inputConfig['generator'])) {
            $generator = $inputConfig['generator'];
            if (method_exists($this, $generator)) {
                $genertedFields = call_user_func(array($this, $generator));
                $this->config = [
                    'type' => 'array',
                    'detail' => $genertedFields,
                ];
            }
            else {
                if ($parent && method_exists($parent, $generator)) {
                    $genertedFields = call_user_func(array($parent, $generator));
                    $this->config = [
                        'type' => 'array',
                        'detail' => $genertedFields,
                    ];
                }
                else {
                    error_log(sprintf('method %s not found for %s', get_class($this), $generator));
                }
            }
        }
    }
}
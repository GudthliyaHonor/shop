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


use Key\Exception\AppException;
use RuntimeException;
use InvalidArgumentException;
use Key\Collection;
use Key\Constants;
use Key\Interfaces\InputInterface;

/**
 * AutoGenerate value Input.
 *
 * @package Key\Inputs
 */
class AutoInput extends Input
{

    protected function getPrefix()
    {
        return $this->get('prefix') ?: '';
    }

    protected function getGenerator()
    {
        return $this->get('generator');
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     */
    public function validate()
    {
        if (($validatedCode = parent::validate()) === Input::VALID_CODE_SUCCESS) {
            $generator = $this->getGenerator();
            switch($generator) {
                case 'int':
                    $this->value = $this->value ?: mtrand(1, 99999999);
                    break;
                case 'float':
                    $this->value = $this->value ?: mtrand(1, 999999999) / mtrand(1, 100);
                    break;
                case 'uuid':
                    $this->value = $this->value ?: create_uuid();
                    break;
                case 'snowflow':
                    $this->value = $this->value ?: \Key\Util\Snowflow::generate();
                    break;
                case 'snowflow-string':
                    $this->value = '' . ($this->value ?: \Key\Util\Snowflow::generate());
                    break;
                case 'sequence':
                    if (class_exists('\App\Common\Sequence')) {
                        $params = $this->get('params');
                        $aid = $params['aid'] ?? 0;

                        $sequenceName = 'auto_input_';
                        if (isset($this->config['sequence_name'])) {
                            $nameConf = $this->config['sequence_name'];
                            if (is_array($nameConf)) {
                                $prefix = $nameConf['prefix'] ?? 'auto_input_';
                                $suffix = $nameConf['suffix'] ?? null;
                                if ($suffix) {
                                    $sequenceName = $prefix . $suffix;
                                }
                                else {
                                    $suffixArgs = $nameConf['suffix_args'];
                                    foreach ($suffixArgs as $arg) {
                                        $sequenceName .= $this->validatedData[$arg];
                                    }
                                }
                            }
                            else {
                                if (is_string($nameConf)) {
                                    $sequenceName = $nameConf;
                                }
                                else {
                                    throw new AppException('Invalid sequence_name type');
                                }
                            }
                        }
                        else {
                            throw new AppException('Missing sequence_name attribute');
                        }
                        if ($this->app) {
                            \App\Common\Sequence::$app = $this->app;
                            $this->value = $this->value ?: \App\Common\Sequence::getSeparateId($sequenceName, $aid, 1, $this->config['sequence_from'] ?? 1000);
                        }
                    }
                    break;
                default:
                    $this->value = $this->value ?: str_replace('.', '', uniqid($this->getPrefix(), true));
            }
        }
        elseif (is_string($this->value) && strlen($this->value) > 0) {
            return $validatedCode;
        }

        $this->validatedCode = static::VALID_CODE_SUCCESS;
        return static::VALID_CODE_SUCCESS;
    }

}
<?php
/**
 * Base render.
 *
 * User: lgh
 * Date: 2018/6/6
 * Time: 10:21
 */

namespace Key\DataTransfer\Renders;


use Pimple\Container;

abstract class BaseRender
{
    protected $row;
    protected $container;
    protected $def = [];

    /**
     * BaseRender constructor.
     * @param Container $container
     * @param array $def Column definition
     */
    public function __construct(Container $container, $def = [])
    {
        $this->container = $container;
        $this->def = $def;
    }

    /**
     * Output.
     *
     * @param array $row Row data
     * @param array $params Extra data
     * @return string
     */
    abstract public function render($row, $params = []);
}
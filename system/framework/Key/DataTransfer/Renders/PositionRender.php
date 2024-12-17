<?php
/**
 * Position render.
 * User: lgh
 * Date: 2018/6/6
 * Time: 10:21
 */

namespace Key\DataTransfer\Renders;


class PositionRender extends BaseRender
{

    /**
     * Output.
     *
     * @param array $row Row data
     * @param array $params Extra data
     * @return string
     */
    public function render($row, $params = [])
    {
        return ArrayGet($row, 'position_name', '');
    }
}
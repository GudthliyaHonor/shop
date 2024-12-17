<?php
/**
 * String render.
 *
 * @author lgh
 * @datetime 2018/9/7 11:12
 * @copyright 2018 Yidianzhishi
 * @version 1.0.0
 */

namespace Key\DataTransfer\Renders;


class StringRender extends BaseRender
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
        $key = $this->def['key'];
        if ($key) {
            $value = ArrayGet($row, $key);
            return $value  ? "\t" . $value : '';
        }
        return '';
    }
}
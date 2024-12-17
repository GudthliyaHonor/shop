<?php
/**
 * Gender column render.
 *
 * User: lgh
 * Date: 2018/6/6
 * Time: 11:02
 */

namespace Key\DataTransfer\Renders;


class GenderRender extends BaseRender
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
        $gender = ArrayGet($row, 'gender', '');
        $map = ArrayGet($this->def, 'map');
        if ($map) {
            $key = array_search($gender, $map);
            if ($key) {
                return $key;
            }
        }
        return '';
    }

}
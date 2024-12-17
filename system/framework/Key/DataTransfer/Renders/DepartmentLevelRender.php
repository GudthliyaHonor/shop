<?php
/**
 * Department Level Render.
 *
 * @author lgh
 * @datetime 2018/8/15 11:32
 * @copyright 2018 Yidianzhishi
 * @version 1.0.0
 */

namespace Key\DataTransfer\Renders;


use App\Models\Department;

class DepartmentLevelRender extends BaseRender
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
        $departments = isset($params['departments']) ? $params['departments'] : [];
        $department_id = ArrayGet($row, 'department_id', 0);
        //$index = ArrayGet($this->def, 'index', 0);
        $index = ArrayGet($params, 'index', 1); // From 1
        if ($departments) {
            if (isset($departments[$department_id]) && $department = $departments[$department_id]) {
                $path = trim(ArrayGet($department, 'path', ''), '/');
                $fragments = explode('/', $path);
                if (count($fragments) >= 1) {
                    $fragments = array_map('intval', $fragments);
                    if (isset($fragments[$index])) {
                        $id = $fragments[$index];
                        if (isset($departments[$id])) {
                            return ArrayGet($departments[$id], 'name', '');
                        }
                    }
                }
            }
        }
        
        return '';
    }

}
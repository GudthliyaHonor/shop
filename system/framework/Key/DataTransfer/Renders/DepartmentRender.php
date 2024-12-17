<?php
/**
 * Department column render.
 *
 * User: lgh
 * Date: 2018/6/6
 * Time: 10:32
 */

namespace Key\DataTransfer\Renders;


use App\Common\Constants;
use App\Models\Account;
use App\Models\Department;

class DepartmentRender extends BaseRender
{

    const RENDER_MODE_NORMAL = 'normal'; // Only render the current node
    const RENDER_MODE_FULL = 'full'; // Render all the levels of the department

    /**
     * Get account name.
     *
     * @return string
     */
    protected function getAccountName()
    {
        $accountModel = new Account($this->container);
        $session = $this->container['session'];
        $aid = (int) $session->get(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID);
        $account = $accountModel->getById($aid);
        return ArrayGet($account, 'name', 'Root');
    }

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
        $accountName = isset($params['accountName']) ? $params['accountName'] : '';
        $department_id = ArrayGet($row, 'department_id', 0);
        if ($department_id) {
            if ($departments) {
                if (isset($departments[$department_id]) && $department = $departments[$department_id]) {
                    $path = trim(ArrayGet($department, 'path', ''), '/');
                    $fragments = explode('/', $path);
                    if (count($fragments) >= 1) {
                        $fragments = array_map('intval', $fragments);
                        $names = [];
                        
                        array_shift($fragments);
                        $names[] = $accountName;

                        foreach ($fragments as $id) {
                            if (isset($departments[$id])) {
                                $names[] = ArrayGet($departments[$id], 'name');
                            } else {
                                $names[] = $id;
                            }
                        }
                        return implode('/', $names);
                    }
                }
            } else {
                $accountName = $this->getAccountName();
                $departmentModel = new Department($this->container);
                $department = $departmentModel->getById($department_id);
                $path = ArrayGet($department, 'path', '');
                $pieces = array_filter(explode('/', $path));
                $pieces = array_map('intval', $pieces);
                if ($pieces) {
                    $pieces = array_values($pieces);
                    array_shift($pieces);
                    if ($pieces) {
                        $names = [];
                        foreach ($pieces as $id) {
                            $dept = $departmentModel->getById($id);
                            $names[] = ArrayGet($dept, 'name');
                        }
                        array_unshift($names, $accountName);
                        return implode('/', $names);
                    }
                }
            }
        }
        return '';
    }
}
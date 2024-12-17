<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Util;


use PHPExcel_Reader_IReadFilter;

/**
 * Class PHPExcelReadFilter
 * @package Key\Util
 * @deprecated
 */
class PHPExcelReadFilter implements PHPExcel_Reader_IReadFilter
{

    protected $startRow = 1;
    protected $endRow;

    /**
     * PHPExcelReadFilter constructor.
     * @param int $startRow
     * @param null $endRow
     */
    public function __construct($startRow = 1, $endRow = null)
    {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
    }

    /**
     * Filter the data.
     *
     * @param string $column
     * @param int $row
     * @param string $worksheetName
     * @return bool
     */
    public function readCell($column, $row, $worksheetName = '')
    {
        // if endRow is not set, read all data.
        if (!$this->endRow) {
            return true;
        }

        // Read data from startRow to endRow.
        if ($row >= $this->startRow && $row <= $this->endRow) {
            return true;
        }

        return false;
    }

}
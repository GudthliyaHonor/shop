<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Common;

require_once LIBRARY_PATH . DS . 'PHPExcel' . DS . 'Classes' .DS . 'PHPExcel.php';

/**
 * Provides export base function.
 *
 * @package App\Common
 */
abstract class Export
{

    protected $filename;
    protected $header = array();
    protected $rows = array();
    protected $sheet_title = 'Sheet1';
    protected $excel_type = 'Excel2007';
    protected $file_extension = 'xlsx';
    protected $export_exist_data = false;

    protected $properties = array();

    //protected $output_dir = '/tmp/';

    public function __construct($header = array(), $rows = array())
    {
        $this->header = $header;
        $this->rows = $rows;
    }

    /**
     * @return string
     */
    protected function getFilename()
    {
        if (empty($this->filename)) {
            $this->filename = randomChars(8, 'char');//Tools::random(8, 'char');
        }

        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * @param string $sheet_title
     */
    public function setSheetTitle($sheet_title)
    {
        $this->sheet_title = $sheet_title;
    }

    /**
     * @param string $excel_type
     */
    public function setExcelType($excel_type)
    {
        switch ($excel_type) {
            case 'Excel5':
                $this->file_extension = 'xls';
                break;
            case 'OpenDocument':
            case 'PDF':
                throw new \InvalidArgumentException('Excel type not supported: '.$excel_type);
                break;
            case 'Excel2007':
            default:
                $this->file_extension = 'xlsx';
        }

        $this->excel_type = $excel_type;
    }

    public function setProperties($properties) {
        if (is_array($properties)) {
            $this->properties = $properties;
        }
    }

    /**
     * @return array
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Update Excel data or format.
     *
     * @param \PHPExcel $excel
     */
    function updateExcel(&$excel)
    {
        //
    }

    /**
     * Start to export
     */
    public function run()
    {
        $excel = $this->createExcel();

        $this->updateExcel($excel);

        $filename = $this->getFilename() . '.' . $this->file_extension;
        //$full_path = $this->output_dir . $filename;

        // clean
        ob_end_clean();

        header('Content-Type: application/vnd.ms-excel');
        header('Cache-Control: max-age=0');
        header("Content-Transfer-Encoding:binary");
        header('Content-Disposition:attachment;filename="'.$filename.'"');

        $writer = \PHPExcel_IOFactory::createWriter($excel, $this->excel_type);
        // Fixme: it should check file size
        $writer->save('php://output');
        exit;
    }

    protected function createExcel()
    {
        $excel = new \PHPExcel();

        if ($this->properties) {
            $excel->setProperties($this->properties);
        }

        $activeSheet = $excel->setActiveSheetIndex(0);
        $activeSheet->setTitle($this->sheet_title);

        foreach($this->getHeader() as $index => $value) {
            $this->handleHeaderItem($index, $value, $activeSheet);
        }

        if ($this->export_exist_data) {
            // fixme, if there is a large data to export?
            $rows = $this->getRows();
            foreach ($rows as $idx1 => $row) {
                $this->renderRow($row, $idx1, $activeSheet);
            }
        }

        return $excel;
    }

    /**
     * Handle header item.
     *
     * @param int $index Column index
     * @param string $value Column value
     * @param \PHPExcel_Worksheet $sheet
     */
    protected function handleHeaderItem($index, $value, $sheet)
    {
        $sheet->setCellValueByColumnAndRow($index, 1, $value);
    }

    /**
     * Render the row.
     *
     * @param array $row Row data
     * @param int  $rowIndex Current row number
     * @param \PHPExcel_Worksheet $activeSheet
     */
    protected function renderRow($row, $rowIndex, $activeSheet)
    {
        foreach ($row as $col => $item) {
            $activeSheet->setCellValueByColumnAndRow($col, $rowIndex + 2, $this->dataConvert($item, $col));
        }
    }

    /**
     * Data conversion
     *
     * @param mixed $val
     * @param int $index
     * @return mixed
     */
    protected function dataConvert($val, $index) {
        return $val;
    }

    /**
     * Set if exports exist data.
     *
     * @param bool $dataExport
     * @return $this
     */
    public function setExportExistData($dataExport = false)
    {
        $this->export_exist_data = !!$dataExport;
        return $this;
    }

}
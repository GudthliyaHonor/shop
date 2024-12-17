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

require LIBRARY_PATH . DS . 'PHPExcel' . DS . 'Classes' .DS . 'PHPExcel.php';

/**
 * Class PHPExcelHelper
 * @package Key\Util
 * @deprecated
 */
class PHPExcelHelper
{
    /**
     * Load PHPExcel library.
     * @deprecated
     */
    public static function loadPHPExcelLibrary()
    {
        //require_once LIBRARY_PATH . DS . 'PHPExcel' . DS . 'Classes' .DS . 'PHPExcel.php';
    }

    /**
     * Read excel file data as an array.
     *
     * @param string $excelFile Excel file path
     * @param int $startRow Start row number to read
     * @param null|int $endRow End row number to read, when it is null, all data will be read
     * @param string $excelType Excel type, for example: 'Excel2007', 'CSV'
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public static function readFromExcel($excelFile, $startRow = 1, $endRow = null, $excelType = 'Excel2007')
    {
        /** @var \PHPExcel_Reader_Abstract $excelReader */
        $excelReader = \PHPExcel_IOFactory::createReader($excelType);
        $excelReader->setReadDataOnly(true);

        if ($startRow > $endRow) {
            $endRow = $startRow;
        }

        if ($startRow && $endRow) {
            $pref = new PHPExcelReadFilter($startRow, $endRow);
            $excelReader->setReadFilter($pref);
        }

        $phpExcel = $excelReader->load($excelFile);
        $activeSheet = $phpExcel->getActiveSheet();

        $highestRow = $activeSheet->getHighestRow();
        if ($endRow === null || $endRow < $startRow || $endRow > $highestRow) {
            $endRow = $highestRow;
        }

        $highestColumn = $activeSheet->getHighestColumn();
        $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn);

        $data = array();
        for($row = $startRow; $row <= $endRow; $row++) {
            for ($col = 0; $col < $highestColumnIndex; $col++) {
                $data[$row][] = (string) $activeSheet->getCellByColumnAndRow($col, $row)->getValue();
            }
        }

        return $data;
    }

    /**
     * Export the excel file to client.
     *
     * @param \PHPExcel $phpExcelObject
     * @param string $filename Export file name
     * @param string $writerType Export writer type, for example: 'Excel2007' => '*.xlxs', 'CVS' => '*.csv'
     * @throws \PHPExcel_Reader_Exception
     */
    public static function exportExcel($phpExcelObject, $filename = 'export.xlsx', $writerType = 'Excel2007')
    {
        if (!($phpExcelObject instanceof \PHPExcel)) {
            throw new \InvalidArgumentException('$phpExcelObject must be PHPExcel instance.');
        }

        // clean
        ob_end_clean();

        header('Content-Type: application/vnd.ms-excel');
        header('Cache-Control: max-age=0');
        header("Content-Transfer-Encoding:binary");
        header('Content-Disposition:attachment;filename="'.$filename.'"');

        $writer = \PHPExcel_IOFactory::createWriter($phpExcelObject, $writerType);
        $writer->save('php://output');
        exit;
    }

    /**
     * Get PHPExcel object.
     *
     * @return \PHPExcel
     */
    public static function getPHPExcel()
    {
        return new \PHPExcel();
    }

    /**
     * Create the excel object.
     *
     * @param array $header Data header
     * @param array $rows Data rows
     * @param string $title Sheet title
     * @param null|array $properties Excel file properties
     * @return \PHPExcel
     */
    public static function createExcel($header, $rows, $title = 'Sheet 1', $properties = null)
    {
        $excel = new \PHPExcel();

        if ($properties) {
            $excel->setProperties($properties);
        }

        $excel->setActiveSheetIndex(0);
        $activeSheet = $excel->getActiveSheet();

        $activeSheet->setTitle($title);

        foreach($header as $index => $value) {
            $activeSheet->setCellValueByColumnAndRow($index, 1, $value);
        }

        foreach ($rows as $idx1 => $row) {
            foreach ($row as $idx2 => $item) {
                $activeSheet->setCellValueByColumnAndRow($idx2, $idx1 + 2, $item);
            }
        }

        return $excel;
    }

    /**
     * Get some information of the excel file.
     *
     * @param string $file File path
     * @param array $info Excel file info
     * @param \PHPExcel $excel
     * @return \PHPExcel_Reader_IReader
     */
    public static function getInfo($file, &$info, &$excel)
    {
        $pathinfo = pathinfo($file);
        if (isset($pathinfo['extension'])) {
            switch (strtolower($pathinfo['extension'])) {
                case 'xls':
                    $excelType = 'Excel5';
                    break;
                case 'xlsx':
                default:
                    $excelType = 'Excel2007';
                    break;
            }
        } else {
            $excelType = 'Excel2007';
        }

        $excelReader = \PHPExcel_IOFactory::createReader($excelType);
        $excelReader->setReadDataOnly(true);

        $excel = $excelReader->load($file);
        $sheet = $excel->setActiveSheetIndex(0);

        $highestColumn = \PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());

        $header = array();
        for($i = 0; $i < $highestColumn; $i++) {
            $header[] = (string) $sheet->getCellByColumnAndRow($i, 1)->getValue();
        }

        $info = array(
            'highestRow' => $sheet->getHighestRow(),
            'highestColumn' => $highestColumn,
            'header' => $header
        );

        return $excelReader;
    }

    /**
     * Get some information of the excel file.
     *
     * @param string $file File path
     * @param array $info Excel file info
     * @param \PHPExcel $excel
     * @return \PHPExcel_Reader_IReader
     */
    public static function getAstResultInfo($file, &$info, &$excel)
    {
        $pathinfo = pathinfo($file);
        if (isset($pathinfo['extension'])) {
            switch (strtolower($pathinfo['extension'])) {
                case 'xls':
                    $excelType = 'Excel5';
                    break;
                case 'xlsx':
                default:
                    $excelType = 'Excel2007';
                    break;
            }
        } else {
            $excelType = 'Excel2007';
        }

        $excelReader = \PHPExcel_IOFactory::createReader($excelType);
        $excelReader->setReadDataOnly(true);

        $excel = $excelReader->load($file);
        $sheet = $excel->setActiveSheetIndex(0);

        $highestColumn = \PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());



        $header = array();
        for($i = 0; $i < $highestColumn; $i++) {
            $header['title']= (string) $sheet->getCellByColumnAndRow(0, 1)->getValue();
            $header['header'][] = (string) $sheet->getCellByColumnAndRow($i, 2)->getValue();
        }

        $info = array(
            'highestRow' => $sheet->getHighestRow(),
            'highestColumn' => $highestColumn,
            'header' => $header
        );
        return $excelReader;
    }
}
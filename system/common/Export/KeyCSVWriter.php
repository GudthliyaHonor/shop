<?php

namespace App\Common\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class KeyCSVWriter implements Writer
{

    const WRITER_TYPE_DEFAULT = 'csv';

    private $file;

    /** @var resource $fp */
    private $fp;

    public function __construct()
    {
        $this->file = uniqid('/tmp/expcsv-') . '.csv';
        $this->fp = fopen($this->file, 'w');   
    }

    /**
     * Write the header of sheet.
     *
     * @param String $sheetName
     * @param array $header
     * @param array $colOptions
     * @return void
     */
    public function writeSheetHeader($sheetName, array $header, $colOptions = null)
    {
        $keys = array_keys($header);
        fputcsv($this->fp, $keys);
    }

    /**
     * Write the row.
     *
     * @param String $sheetName
     * @param array $row String array data, for example: ['aaa', 'bbb']
     * @param array $rowOptions
     * @return void
     */
    public function writeSheetRow($sheetName, array $row, $rowOptions = null)
    {
        fputcsv($this->fp, $row);
    }

    /**
     * Write the rows.
     *
     * @param array[] $rows Array of string array
     * @return void
     */
    public function writeSheetRows($sheetName, $rows, $rowOptions = null)
    {
        foreach ($rows as $row) {
            fputcsv($this->fp, $row);
        }
    }

    /**
     * Write the data to file.
     *
     * @param string $file
     * @return void
     */
    public function writeToFile($file)
    {
        fclose($this->fp);
        return copy($this->file, $file);
    }

    /**
     * Set company info for the XLS file.
     *
     * @param string $company
     * @return void
     */
    public function setCompany($company)
    {

    }

    /**
     * Set temp dir for the writer.
     *
     * @param string $tempdir
     * @return void
     */
    public function setTempDir($tempdir = '')
    {
        // DO nothing
    }

}
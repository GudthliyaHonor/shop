<?php

namespace App\Common\Export;


interface Writer
{
    /**
     * Write the header of sheet.
     *
     * @param String $sheetName
     * @param array $header
     * @param array $colOptions
     * @return void
     */
    public function writeSheetHeader($sheetName, array $header, $colOptions = null);

    /**
     * Write the row.
     *
     * @param String $sheetName
     * @param array $row
     * @param array $rowOptions
     * @return void
     */
    public function writeSheetRow($sheetName, array $row, $rowOptions = null);

    /**
     * Write the rows.
     *
     * @param array[] $rows
     * @return void
     */
    public function writeSheetRows($sheetName, $rows, $rowOptions = null);

    /**
     * Write the data to file.
     *
     * @param string $file
     * @return void
     */
    public function writeToFile($file);

    /**
     * Set company info for the XLS file.
     *
     * @param string $company
     * @return void
     */
    public function setCompany($company);

    /**
     * Set temp dir for the writer.
     *
     * @param string $tempdir
     * @return void
     */
    public function setTempDir($tempdir = '');

}
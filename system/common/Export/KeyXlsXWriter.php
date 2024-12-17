<?php

namespace App\Common\Export;


use XLSXWriter;

class KeyXlsXWriter implements Writer
{
    private $xlsWriter;

    public function __construct()
    {
        $this->xlsWriter = new XLSXWriter();
    }

    /**
     * Write the header of sheet.
     *
     * @param String $sheetName
     * @param array $header Header types, for example: ['header1' => '@', 'header2' => '@']
     * @param array $colOptions
     * @return void
     */
    public function writeSheetHeader($sheetName, array $header, $colOptions = null)
    {
        $this->xlsWriter->writeSheetHeader($sheetName, $header, $colOptions);
    }

    /**
     * Write the row.
     *
     * @param String $sheetName
     * @param array $row
     * @param array $rowOptions
     * @return void
     */
    public function writeSheetRow($sheetName, array $row, $rowOptions = null)
    {
        $this->xlsWriter->writeSheetRow($sheetName, $row, $rowOptions);
    }

    /**
     * Write the rows.
     *
     * @param array[] $rows
     * @return void
     */
    public function writeSheetRows($sheetName, $rows, $rowOptions = null)
    {
        if ($rows && is_array($rows)) {
            foreach ($rows as $row) {
                $this->writeSheetRow($sheetName, $row, $rowOptions);
            }
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
        $this->xlsWriter->writeToFile($file);
    }

    /**
     * Set company info for the XLS file.
     *
     * @param string $company
     * @return $this
     */
    public function setCompany($company)
    {
        $this->xlsWriter->setCompany($company);
        return $this;
    }

    /**
     * Set temp dir for the writer.
     *
     * @param string $tempdir
     * @return $this
     */
    public function setTempDir($tempdir = '')
    {
        $this->xlsWriter->setTempDir($tempdir);
        return $this;
    }

}
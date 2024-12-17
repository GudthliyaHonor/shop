<?php

namespace App\Common\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class KeySpreadWriter implements Writer
{

    const WRITER_TYPE_DEFAULT = 'Xlsx';

    /** @var \PhpOffice\PhpSpreadsheet\Writer\IWriter $xlsWriter */
    private $xlsWriter;

    private $spreadsheet;

    private $rowIndex = 1;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet();
        $this->xlsWriter = IOFactory::createWriter($this->spreadsheet, self::WRITER_TYPE_DEFAULT);
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
        if (!$this->spreadsheet->sheetNameExists($sheetName)) {
            $this->spreadsheet->getActiveSheet()->setTitle($sheetName);
        }
        $sheet = $this->spreadsheet->setActiveSheetIndexByName($sheetName);
        $i = 1;
        $keys = array_keys($header);
        foreach ($keys as $val) {
            $sheet->getCell([$i++, 1])->setValueExplicit($val, DataType::TYPE_STRING2);
        }
        $this->rowIndex++;
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
        $sheet = $this->spreadsheet->setActiveSheetIndexByName($sheetName);
        $sheet->fromArray([$row], null, 'A' . ($this->rowIndex++));
    }

    /**
     * Write the rows.
     *
     * @param array[] $rows Array of string array
     * @return void
     */
    public function writeSheetRows($sheetName, $rows, $rowOptions = null)
    {
        $sheet = $this->spreadsheet->setActiveSheetIndexByName($sheetName);
        $sheet->fromArray($rows, null, 'A' . $this->rowIndex);
        $this->rowIndex += count($rows);
    }

    /**
     * Write the data to file.
     *
     * @param string $file
     * @return void
     */
    public function writeToFile($file)
    {
        $this->xlsWriter->save($file);
    }

    /**
     * Set company info for the XLS file.
     *
     * @param string $company
     * @return void
     */
    public function setCompany($company)
    {
        $this->spreadsheet->getProperties()->setCompany($company);
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
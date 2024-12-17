<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2018 yidianzhishi.cn
 * @version 1.0.0
 * @link http://www.yidianzhishi.cn
 */

namespace Key\DataTransfer;


//use Box\Spout\Common\Type;
//use Box\Spout\Writer\Common\Sheet;
//use Box\Spout\Writer\WriterFactory;
//use Box\Spout\Writer\WriterInterface;
//use Box\Spout\Writer\Style\StyleBuilder;

class Export
{
    const DEFAULT_TIME_LIMIT = 300;

    /** @var \Key\Foundation\Application */
    protected $app;

    /** @var array */
    protected $headers;

    /** @var array */
    protected $data = [];

    /** @var \Box\Spout\Writer\WriterInterface */
    protected $writer;

    protected $sheet;

    protected $outputFile = 'php://output';

    protected $outputFilename = 'export.xlsx';

    /**
     * Get the export instance.
     * 
     * @param \Key\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Set the `set_time_limit` config of php.
     * 
     * @param int $limit
     * @return $this
     */
    public function setTimeLimit($limit = self::DEFAULT_TIME_LIMIT)
    {
        set_time_limit($limit > 0 ? $limit : self::DEFAULT_TIME_LIMIT);
        return $this;
    }

    /**
     * Set the extra arguments.
     * 
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setArgument($name, $value)
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * Set the header configures.
     * 
     * @param array $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the file to the local.
     * 
     * @param string $file File path
     * @return $this
     */
    public function setOutputFile($file)
    {
        $this->outputFile = $file;
        return $this;
    }

    /**
     * Set the filename to web browser client.
     * 
     * @param string $filename
     * @return $this
     */
    public function setOutputFilename($filename)
    {
        $this->outputFilename = $filename;
        return $this;
    }

    /**
     * Set the rows to sheet (Append mode).
     * 
     * @return $this
     */
    public function setRows($rows)
    {
        if ($rows) {
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $this->addRow($row);
                }
            }
        }
        return $this;
    }

    /**
     * Add a row to the sheet.
     * 
     * return $this
     */
    public function addRow($row, $style = null, $transform = true)
    {
        if ($row) {
            $row = $transform ? $this-> transform($row) : $row;
            if ($style) {
                $this->getWriter()->addRowWithStyle($row, $style);
            } else {
                $this->getWriter()->addRow($row);
            }
            
        }
        return $this;
    }

    protected function transform($row)
    {
        $newRow = [];
        if ($row) {
            foreach ($this->headers as $key => $conf) {
                $val = ArrayGet($row, $key);
                $type = ArrayGet($conf, 'type', 'string');
                switch ($type) {
                    case 'int':
                        $val = (int) $val;
                        $map = ArrayGet($conf, 'map');
                        if (is_null($map)) {
                            $newRow[] = $val;
                        } else {
                            if (isset($map[$val])) {
                                $newRow[] = $map[$val];
                            } else {
                                $newRow[] = $val;
                            }
                        }
                        break;
                    case 'date':
                        $format = ArrayGet($conf, 'format', 'Y-m-d');
                        if ($val instanceof \MongoDB\BSON\UTCDateTime) {
                            $val = date($format, $val->toDateTime()->getTimestamp());
                        } elseif (is_int($val)) {
                            $val = date($format, $val);
                        } else {
                            $val = (string) $val;
                        }
                        $newRow[] = $val;
                        break;
                    case 'string':
                        if ($prefix = ArrayGet($conf, 'prefix')) {
                            $val = $prefix . (string) $val;
                        }
                    default:
                        $newRow[] = (string) $val;
                }
            }
        }
        return $newRow;
    }

    /**
     * Send the file to web browser client.
     */
    public function send()
    {
        header('Content-Description: File Transfer');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition:attachment;filename="' . $this->outputFilename .'"');
        header('Cache-Control: max-age=0');
        exit();
    }

    /**
     * Get the writer.
     * 
     * @return \Box\Spout\Writer\WriterInterface
     */
    public function getWriter()
    {
        if (!$this->writer) {
            $this->writer = WriterFactory::create(Type::XLSX);
            $this->writer->openToFile($this->outputFile);
        }
        return $this->writer;
    }

    /**
     * Close the writer.
     * 
     * @return $this
     */
    public function closeWriter()
    {
        if ($this->writer) {
            $this->writer->close();
        }
        return $this;
    }

    /**
     * Get the sheet.
     * 
     * @return \Box\Spout\Writer\Common\Sheet
     */
    protected function getSheet()
    {
        $writer = $this->getWriter();
        return $writer->getCurrentSheet();
    }

    /**
     * Render the header to the sheet.
     * 
     * @return $this
     */
    public function renderHeader($wrapText = true)
    {
        $headerNames = [];
        foreach ($this->headers as $header) {
            $headerName = is_string($header) 
                ? $header : (is_array($header) && isset($header['title']) ? $header['title'] : '');
            $headerNames[] = $headerName;
        }
     /*   $style = (new StyleBuilder())
            ->setShouldWrapText($wrapText)
            ->build();*/
        $this->addRow($headerNames, null, false);
        return $this;
    }
}

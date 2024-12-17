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

use Box\Spout\Common\Type;
use Key\Foundation\Application;
use Box\Spout\Reader\ReaderFactory;

class Import
{
    protected $readerType = Type::XLSX;
    protected $file;

    /** @var \Box\Spout\Reader\ReaderInterface */
    protected $reader;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function setReaderType($type = Type::XLSX)
    {
        $this->readerType = $type;
    }

    public function loadFile($file)
    {
        $this->file = $file;
        $this->reader = ReaderFactory::create($this->readerType);
        $this->reader->setShouldFormatDates(true);
        $this->reader->open($file);
        return $this;
    }

    /**
     * Get current sheet.
     *
     * @return \Box\Spout\Reader\SheetInterface
     */
    public function getCurrentSheet()
    {
        $iterator = $this->reader->getSheetIterator();
        $iterator->rewind();
        return $iterator->current();
    }
}
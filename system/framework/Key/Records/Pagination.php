<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Records;


use Key\Abstracts\BaseRecord;
use Key\Constants;

/**
 * Class Pagination
 *
 * @package Key\Data
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Pagination extends BaseRecord
{

    protected $fields = array(
        'page' => array(
            'type' => 'int',
            'min' => 0
        ),

        'itemsPerPage' => array(
            'type' => 'int',
            'min' => 0,
            'default' => 10
        )
    );

    /**
     * Current page
     *
     * @var int
     */
    protected $page = 0;

    /**
     * Items per page
     *
     * @var int
     */
    protected $itemsPerPage = Constants::DEFAULT_ITEMS_PER_PAGE;

    /**
     * Page offset
     *
     * @var int
     */
    protected $offset;

    /**
     * @inheritDoc
     */
    public function __construct($name = 'pg', $value = array(), $inputConfig = null)
    {
        parent::__construct($name, $value, $inputConfig);

        if ($inputConfig) {
            if (isset($inputConfig['default']['page'])) {
                $this->page = (int) $inputConfig['default']['page'];
            }
            if (isset($inputConfig['default']['itemsPerPage'])) {
                $this->itemsPerPage = (int) $inputConfig['default']['itemsPerPage'];
            }
        }

        if (is_array($this->value)) {
            if (isset($this->value['page'])) {
                $this->page = $this->value['page'];
            }
            if (isset($this->value['itemsPerPage'])) {
                $this->itemsPerPage = $this->value['itemsPerPage'];
            }
        }

    }

    /**
     * Get current page number.
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Set current page number.
     *
     * @param int $page
     * @return Pagination
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }

    /**
     * Get the number of the items per page.
     *
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->itemsPerPage;
    }

    /**
     * Set the number of the items per page.
     *
     * @param int $itemsPerPage
     * @return Pagination
     */
    public function setItemsPerPage($itemsPerPage)
    {
        $this->itemsPerPage = $itemsPerPage;
        return $this;
    }

    /**
     * Get the page offset.
     *
     * @return int
     */
    public function getOffset()
    {
        $this->offset = ($this->getPage() > 0 ? $this->getPage() - 1 : 0) * $this->itemsPerPage;

        return $this->offset;
    }

    /**
     * Set the page offset.
     *
     * @param int $offset
     * @return Pagination
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Get valid value for the input.
     *
     * @return mixed|null
     */
    public function getValidValue()
    {
        if ($value = parent::getValidValue()) {
            if ($tmp = $value->toArray()) {
                if (isset($tmp['page'])) {
                    $this->setPage($tmp['page']);
                }
                if (isset($tmp['itemsPerPage'])) {
                    $this->setItemsPerPage($tmp['itemsPerPage']);
                }
            }
        }

        return $this;
    }


    public function toArray($recursive = false)
    {
        return [
            'page' => $this->getPage(),
            'itemsPerPage' => $this->getItemsPerPage(),
            'offset' => $this->getOffset(),
        ];
    }

}

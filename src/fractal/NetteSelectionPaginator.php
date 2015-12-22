<?php

namespace Tomaj\NetteApi\Fractal;

use League\Fractal\Pagination\PaginatorInterface;
use Nette\Database\Table\Selection;

class NetteSelectionPaginator implements PaginatorInterface
{
    private $items;

    private $perPage;

    private $page;

    private $apiLinkGenerator;

    private $totalCount = null;

    public function __construct(Selection $items, $perPage, $page, \Closure $apiLinkGenerator = null)
    {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->page = $page;
        $this->apiLinkGenerator = $apiLinkGenerator;
    }

    public function getCurrentPage()
    {
        return $this->page;
    }

    public function getLastPage()
    {
        return ceil($this->getCount() / $this->perPage);
    }

    public function getTotal()
    {
        return $this->getCount();
    }

    public function getCount()
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->items->count('*');
        }
        return $this->totalCount;
    }

    public function getPerPage()
    {
        return $this->perPage;
    }

    public function getUrl($page)
    {
        if (!$this->apiLinkGenerator) {
            return null;
        }
        return $this->apiLinkGenerator->__invoke($page);
    }
}

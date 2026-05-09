<?php
namespace Combipower\TessAI\Api\Data;

interface PaginationMetaInterface
{
    const TOTAL = 'total';
    const PAGE = 'page';
    const PER_PAGE = 'per_page';

    /**
     * @return int|null
     */
    public function getTotal();

    /**
     * @return int|null
     */
    public function getPage();

    /**
     * @return int|null
     */
    public function getPerPage();

    /**
     * @param int $total
     * @return \Combipower\TessAI\Api\Data\PaginationMetaInterface
     */
    public function setTotal($total);

    /**
     * @param int $page
     * @return \Combipower\TessAI\Api\Data\PaginationMetaInterface
     */
    public function setPage($page);

    /**
     * @param int $perPage
     * @return \Combipower\TessAI\Api\Data\PaginationMetaInterface
     */
    public function setPerPage($perPage);
}

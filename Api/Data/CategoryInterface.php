<?php
namespace Combipower\TessAI\Api\Data;

interface CategoryInterface
{
    const ID = 'id';
    const NAME = 'name';
    const PARENT_ID = 'parent_id';
    const DEPTH = 'depth';

    /**
     * @return string|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getName();

    /**
     * @return string|null
     */
    public function getParentId();

    /**
     * @return int|null
     */
    public function getDepth();

    /**
     * @param string $id
     * @return \Combipower\TessAI\Api\Data\CategoryInterface
     */
    public function setId($id);

    /**
     * @param string $name
     * @return \Combipower\TessAI\Api\Data\CategoryInterface
     */
    public function setName($name);

    /**
     * @param string|null $parentId
     * @return \Combipower\TessAI\Api\Data\CategoryInterface
     */
    public function setParentId($parentId);

    /**
     * @param int $depth
     * @return \Combipower\TessAI\Api\Data\CategoryInterface
     */
    public function setDepth($depth);
}

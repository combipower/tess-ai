<?php
namespace Combipower\TessAI\Api\Data;

interface OptionInterface
{
    const ID = 'id';
    const NAME = 'name';

    /**
     * @return string|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getName();

    /**
     * @param string $id
     * @return \Combipower\TessAI\Api\Data\OptionInterface
     */
    public function setId($id);

    /**
     * @param string $name
     * @return \Combipower\TessAI\Api\Data\OptionInterface
     */
    public function setName($name);
}

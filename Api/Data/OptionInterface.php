<?php
namespace Tess\PricingTool\Api\Data;

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
     * @return \Tess\PricingTool\Api\Data\OptionInterface
     */
    public function setId($id);

    /**
     * @param string $name
     * @return \Tess\PricingTool\Api\Data\OptionInterface
     */
    public function setName($name);
}

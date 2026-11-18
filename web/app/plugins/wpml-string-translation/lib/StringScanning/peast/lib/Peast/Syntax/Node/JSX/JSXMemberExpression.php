<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace WPML\ST\Vendor\Peast\Syntax\Node\JSX;

use WPML\ST\Vendor\Peast\Syntax\Node\Node;
use WPML\ST\Vendor\Peast\Syntax\Node\Expression;

class JSXMemberExpression extends Node implements Expression
{
    protected $propertiesMap = array(
        "object" => true,
        "property" => true
    );
    
    protected $object;
    
    protected $property;
    
    public function getObject()
    {
        return $this->object;
    }
    
    public function setObject($object)
    {
        $this->assertType($object, array("JSX\\JSXMemberExpression", "JSX\\JSXIdentifier"));
        $this->object = $object;
        return $this;
    }
    
    public function getProperty()
    {
        return $this->property;
    }
    
    public function setProperty(JSXIdentifier $property)
    {
        $this->property = $property;
        return $this;
    }
}
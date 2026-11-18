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

abstract class JSXBoundaryElement extends Node
{
    protected $propertiesMap = array(
        "name" => true
    );
    
    protected $name;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->assertType(
            $name,
            array("JSX\\JSXIdentifier", "JSX\\JSXMemberExpression", "JSX\\JSXNamespacedName")
        );
        $this->name = $name;
        return $this;
    }
}
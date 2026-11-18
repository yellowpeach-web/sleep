<?php
/**
 * This file is part of the Peast package
 *
 * (c) Marco Marchiò <marco.mm89@gmail.com>
 *
 * For the full copyright and license information refer to the LICENSE file
 * distributed with this source code
 */
namespace WPML\ST\Vendor\Peast\Selector\Node\Part;

use WPML\ST\Vendor\Peast\Syntax\Node\Node;
use WPML\ST\Vendor\Peast\Syntax\Node\Pattern;
use WPML\ST\Vendor\Peast\Syntax\Node\Statement;
use WPML\ST\Vendor\Peast\Syntax\Node\Expression;
use WPML\ST\Vendor\Peast\Syntax\Node\Declaration;
use WPML\ST\Vendor\Peast\Syntax\Utils;

class PseudoSimple extends Pseudo
{
    protected $priority = 3;

    public function check(Node $node, $parent = null)
    {
        switch ($this->name) {
            case "pattern":
                return $node instanceof Pattern;
            case "statement":
                return $node instanceof Statement;
            case "expression":
                return $node instanceof Expression;
            case "declaration":
                return $node instanceof Declaration;
            case "last-child":
            case "first-child":
                $first = $this->name === "first-child";
                $props = Utils::getExpandedNodeProperties($parent);
                return count($props) > 0 && (
                    $first ? $props[0] === $node : array_pop($props) === $node
                );
        }
    }
}
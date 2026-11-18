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

use WPML\ST\Vendor\Peast\Selector\Matches;
use WPML\ST\Vendor\Peast\Selector\Node\Selector;
use WPML\ST\Vendor\Peast\Syntax\Node\Node;

class PseudoSelector extends Pseudo
{
    protected $priority = 1;

    protected $selector;

    public function setSelector(Selector $selector)
    {
        $this->selector = $selector;
        return $this;
    }

    public function check(Node $node, $parent = null)
    {
        $match = new Matches();
        $match->addMatch($node, $parent);
        $res = $this->selector->exec($match)->count();
        return $this->name === "not" ? $res === 0 : $res !== 0;
    }
}
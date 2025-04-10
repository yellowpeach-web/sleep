<?php

namespace Timber\Extensions;

class FaqPostType extends \Timber\Post
{

    public function example(): string|null
    {
        return 'Example: returned from example timber extension';
    }
}

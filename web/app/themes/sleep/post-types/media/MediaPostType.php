<?php

namespace Timber\Extensions;

class MediaPostType extends \Timber\Post
{

    public function example(): string|null
    {
        return 'Example: returned from example timber extension';
    }
}

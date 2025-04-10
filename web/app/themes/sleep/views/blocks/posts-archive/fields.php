<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('posts-archive');
$block
    ->addMessage('*Info', 'This block will display all posts. For use on archive page only')
    ->setLocation('block', '==', 'acf/posts-archive')
    ->setFields();

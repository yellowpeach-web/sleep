<?php

namespace YPTheme\AcfBuilder;

use StoutLogic\AcfBuilder\FieldsBuilder;

class ThemeFieldBuilder extends FieldsBuilder
{
    use AcfFieldClones;

    public function setFields()
    {
        $this->addLocalFieldGroup();
    }

    public function addLocalFieldGroup()
    {
        if (!class_exists('ACF')) {
            return;
        }
        acf_add_local_field_group($this->build());
    }
}

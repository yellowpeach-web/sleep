<?php

namespace YPTheme\AcfBuilder;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 *   AcfFieldClones Trait
 *   Used for defining reusable ACF fields.
 */
trait AcfFieldClones
{
    /**
     * Retrieve the cloned field
     *
     * @param string $methodName Name of the field method
     * @return FieldsBuilder
     */
    public function getClone(string $methodName)
    {
        return $this->$methodName();
    }

    public function buttons()
    {
        return $this
            ->addRepeater('buttons')
            ->addLink('button')
            ->endRepeater();
    }

    public function heading()
    {
        return $this
            ->addText('heading', [
                'wrapper' => ['width' => '75%'],
            ])
            ->addSelect('tag', [
                'label' => 'Heading Type',
                'instructions' => 'Select the required heading type. Note: only use a H1 tag once on a page.',
                'default_value' => ['h2' => 'H2'],
                'wrapper' => ['width' => '25%'],
            ])
            ->addChoices(
                ['h1' => 'H1'],
                ['h2' => 'H2'],
                ['h3' => 'H3'],
                ['h4' => 'H4'],
                ['h5' => 'H5'],
                ['h6' => 'H6'],
                ['p' => 'P']
            )
            ->setDefaultValue('h2');
    }

    public function content()
    {
        return $this->addWysiwyg('content');
    }
}

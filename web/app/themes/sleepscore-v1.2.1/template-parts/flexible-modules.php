<?php
    $fields = get_field_objects();
    $flex_field = 'flexible_modules';
    $fid = $fields[$flex_field]['ID'];
    if (isset($fid) && $fid != ''):
        $sections = array();
        $mydata = get_post_field('post_content', $fid);
        $mydata = unserialize($mydata);
        $newdata = $mydata['layouts'];
        foreach ($newdata as $l):
            array_push($sections, $l['name']);
        endforeach;
        if (have_rows($flex_field)) :
            while (have_rows($flex_field)) : the_row();
                $rowlayout = get_row_layout();
                if (in_array($rowlayout, $sections)) :
                    get_template_part("template-parts/sections/" . $rowlayout);
                endif;
            endwhile;
        endif;
    endif;
?>
<?php

/*
 * Labels of select options given as plain values (Field::options(['start', 'center'])).
 * A key under the field name ("valign" => [...]) wins over the generic one.
 */
return [
    'start' => 'Start',
    'center' => 'Centre',
    'end' => 'End',

    'none' => 'None',
    'xs' => 'XS',
    'sm' => 'S',
    'md' => 'M',
    'lg' => 'L',
    'xl' => 'XL',
    '2xl' => '2XL',
    '3xl' => '3XL',
    '4xl' => '4XL',
    '5xl' => '5XL',

    'thin' => 'Thin',
    'thick' => 'Thick',
    'theme' => 'Theme',
    'full' => 'Full width',
    'boxed' => 'Boxed',
    'readable' => 'Readable',

    'info' => 'Information',
    'success' => 'Success',
    'warning' => 'Warning',

    'auto' => 'Original',
    'square' => 'Square',
    'landscape' => 'Landscape',
    'portrait' => 'Portrait',
    'wide' => 'Wide',

    'solid' => 'Solid',
    'outline' => 'Outline',
    'link' => 'Link',

    'valign' => ['start' => 'Top', 'center' => 'Middle', 'end' => 'Bottom'],
    'radius' => ['none' => 'Square', 'full' => 'Circle'],
    'rounded' => ['none' => 'Square', 'md' => 'Theme', 'lg' => 'Large', 'full' => 'Circle'],
    'container' => ['full' => 'Full width'],
    'width' => ['full' => 'Whole column'],
    // Explicit labels used by core blocks (sizes whose meaning depends on the block).
    'labels' => ['normal' => 'Regular', 'large' => 'Large', 'highlighted' => 'Highlighted', 'lead' => 'Lead'],
];

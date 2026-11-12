<?php

/*
 * Labels of select options given as plain values (Field::options(['start', 'center'])).
 * A key under the field name ("valign" => [...]) wins over the generic one.
 */
return [
    'start' => 'Début',
    'center' => 'Centre',
    'end' => 'Fin',

    'none' => 'Aucun',
    'xs' => 'XS',
    'sm' => 'S',
    'md' => 'M',
    'lg' => 'L',
    'xl' => 'XL',
    '2xl' => '2XL',
    '3xl' => '3XL',
    '4xl' => '4XL',
    '5xl' => '5XL',

    'thin' => 'Fine',
    'thick' => 'Épaisse',
    'theme' => 'Thème',
    'full' => 'Pleine largeur',
    'boxed' => 'Centrée',
    'readable' => 'Lisible',

    'info' => 'Information',
    'success' => 'Succès',
    'warning' => 'Attention',

    'auto' => 'Original',
    'square' => 'Carré',
    'landscape' => 'Paysage',
    'portrait' => 'Portrait',
    'wide' => 'Panoramique',

    'solid' => 'Plein',
    'outline' => 'Contour',
    'link' => 'Lien',

    'valign' => ['start' => 'Haut', 'center' => 'Milieu', 'end' => 'Bas'],
    'radius' => ['none' => 'Droit', 'full' => 'Cercle'],
    'rounded' => ['none' => 'Droit', 'md' => 'Thème', 'lg' => 'Large', 'full' => 'Cercle'],
    'border' => ['none' => 'Aucune'],
    'shadow' => ['none' => 'Aucune'],
    'container' => ['full' => 'Pleine largeur'],
    'width' => ['full' => 'Toute la colonne'],
    // Explicit labels used by core blocks (sizes whose meaning depends on the block).
    'labels' => ['normal' => 'Normale', 'large' => 'Grande', 'highlighted' => 'Mise en avant', 'lead' => 'Chapô'],
];

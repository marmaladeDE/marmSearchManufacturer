<?php
/**
 * marmalade GmbH
 * OXID module to search also in the manufacturer table
 *
 * PHP version 5
 *
 * @author   Joscha Krug <support@marmalade.de>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.html
 * @version  2.0
 * @link     https://github.com/marmaladeDE/marmSearchManufacturer
 */

/**
 * Metadata version
 */
$sMetadataVersion = '1.1';

$aModule = array(
    'id'          => 'marm/searchmanufacturer',
    'title'       => 'marmalade :: Search Manufacturer',
    'description' => array(
        'de'    => 'Findet Produkte auch über den Herstellernamen',
        'en'    => 'Find your products also via the manufacturers name.',
    ),
    'email'         => 'support@marmalade.de',
    'url'           => 'http://www.marmalade.de',
    'thumbnail'     => 'marmalade.jpg',
    'version'       => '2.0',
    'author'        => 'marmalade GmbH :: Joscha Krug',
    'extend' => array(
        'oxsearch'              => 'marm/searchmanufacturer/marm_searchmanufacturers_oxsearch'
    )
);
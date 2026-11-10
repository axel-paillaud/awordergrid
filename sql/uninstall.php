<?php
/**
 * @author    Axelweb <contact@axelweb.fr>
 * @copyright 2026 Axelweb
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of Axelweb
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

// Example table deletion (commented by default)
// Uncomment and adapt according to your needs
/*
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'awordergrid_example`;';
*/

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;

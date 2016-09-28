<?php
/*-------------------------------------------------------+
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| https://www.php-fusion.co.uk/
+--------------------------------------------------------*
| Filename: Page/WidgetInterface.php
| Author: Frederick MC Chan (Chan)
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/
namespace PHPFusion\Page;
if (!defined("IN_FUSION")) {
    die("Access Denied");
}

interface WidgetInterface {

    /**
     * Widget display driver which returns a html string
     * You should also extend your display callback class with the PageModel that
     * will enable more data access
     *
     * @param $columnData - the data input for the driver for your consideration
     *
     * @return HTML string
     */
    public function display_widget($columnData);

}
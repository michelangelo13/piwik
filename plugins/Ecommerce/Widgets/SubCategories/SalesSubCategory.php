<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Ecommerce\Widgets\SubCategories;

use Piwik\Widget\SubCategory;

class SalesSubCategory extends SubCategory
{
    protected $category = 'Goals_Ecommerce';
    protected $name = 'Ecommerce_Sales';
    protected $order = 15;

}

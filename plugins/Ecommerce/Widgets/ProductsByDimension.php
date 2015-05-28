<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Ecommerce\Widgets;

use Piwik\Widget\WidgetContainerConfig;

class ProductsByDimension extends WidgetContainerConfig
{
    protected $layout = 'ByDimension';
    protected $id = 'Products';
    protected $category = 'Goals_Ecommerce';
    protected $subCategory = 'Goals_Products';
}

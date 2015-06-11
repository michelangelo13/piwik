<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Actions\Widgets\SubCategories;

use Piwik\Widget\SubCategory;

class DownloadsSubCategory extends SubCategory
{
    protected $category = 'General_Actions';
    protected $name = 'General_Downloads';
    protected $order = 35;

}

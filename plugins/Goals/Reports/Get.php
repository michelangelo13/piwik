<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Goals\Reports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\Goals\API;
use Piwik\Plugins\Goals\Goals;
use Piwik\Plugins\Goals\Pages;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Site;
use Piwik\Widget\WidgetsList;

class Get extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('Goals_Goals');
        $this->processedMetrics = array('conversion_rate');
        $this->documentation = ''; // TODO
        $this->order = 1;
        $this->orderGoal = 50;
        $this->metrics = array('nb_conversions', 'nb_visits_converted', 'revenue');
        $this->parameters = null;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite  = Common::getRequestVar('idSite', null, 'int');
        $goals   = API::getInstance()->getGoals($idSite);
        $reports = Goals::getReportsWithGoalMetrics();

        $site = new Site($idSite);
        $ecommerceEnabled = $site->isEcommerceEnabled();

        $page = new Pages($factory, $reports);

        $allPages   = array();
        $allPages[] = $page->createGoalsOverviewPage($goals);

        if ($ecommerceEnabled) {
            $allPages[] = $page->createEcommerceOverviewPage();
        }

        foreach ($goals as $goal) {
            $allPages[] = $page->createGoalDetailPage($goal);
        }

        $order = 2;
        foreach ($allPages as $widgets) {
            $widgetsList->addWidgets($widgets);
            $this->makePageWidgetizable($widgets, $order++, $widgetsList, $factory);
        }

        // we do not want to create a widgetizable widget for this page
        if ($ecommerceEnabled) {
            $widgetsList->addWidgets($page->createEcommerceSalesPage());
        }
    }

    private function makePageWidgetizable($widgets, $order, WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        /** @var \Piwik\Widget\WidgetConfig[] $widgets */
        $firstWidget = reset($widgets);

        $id = $firstWidget->getCategory() . $firstWidget->getSubCategory();

        $config = $factory->createContainerWidget($id);
        $config->setName($firstWidget->getSubCategory());
        $config->setCategory($firstWidget->getCategory());
        $config->setSubCategory('');
        $config->setIsWidgetizable();
        $config->setOrder($order);
        $widgetsList->addContainer($config);
        foreach ($widgets as $widget) {
            $config->addWidget($widget);
        }
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            $idGoal = Common::getRequestVar('idGoal', 0, 'int');

            if (empty($idGoal)) {

                $view->config->addSparklineMetricsToDisplay(array('nb_conversions'));
                $view->config->addSparklineMetricsToDisplay(array('conversion_rate'));
                $view->config->addSparklineMetricsToDisplay(array('revenue'));

                $view->config->addTranslations(array(
                    'nb_conversions' => Piwik::translate('Goals_Conversions'),
                    'conversion_rate' => Piwik::translate('Goals_OverallConversionRate'),
                    'revenue' => Piwik::translate('Goals_OverallRevenue'),
                ));

            } else {
                $allowMultiple = Common::getRequestVar('allow_multiple', 0, 'int');

                $view->config->addSparklineMetricsToDisplay(array('nb_conversions'));

                if ($allowMultiple) {
                    $view->config->addSparklineMetricsToDisplay(array('nb_visits_converted'));
                }

                $view->config->addSparklineMetricsToDisplay(array('conversion_rate'));
                $view->config->addTranslations(array(
                    'nb_conversions' => Piwik::translate('Goals_Conversions'),
                    'nb_visits_converted' => Piwik::translate('General_NVisits'),
                    'conversion_rate' => Piwik::translate('Goals_ConversionRate'),
                ));
            }
        }
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        if (!$this->isEnabled()) {
            return;
        }

        parent::configureReportMetadata($availableReports, $infos);

        $this->addReportMetadataForEachGoal($availableReports, $infos, function ($goal) {
            return Piwik::translate('Goals_GoalX', $goal['name']);
        });
    }
}

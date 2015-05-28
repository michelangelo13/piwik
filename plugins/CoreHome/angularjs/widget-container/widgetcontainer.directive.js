/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Example:
 * <div piwik-widget-container container="widget"></div>
 */
(function () {
    angular.module('piwikApp').directive('piwikWidgetContainer', piwikWidgetContainer);

    piwikWidgetContainer.$inject = ['piwik', '$location'];

    function piwikWidgetContainer(piwik, $location){
        return {
            restrict: 'A',
            scope: {
                container: '='
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget-container/widgetcontainer.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, ngModel) {

                    function getFullWidgetUrl(widget) {
                        var url = widget.widget_url;

                        // available in global scope
                        var search = $location.search();
                        url += '&idSite=' + search.idSite + '&period=' + search.period + '&date=' + search.date;
                        url += '&random=' + parseInt(Math.random() * 10000);

                        return url;
                    }

                    angular.forEach(scope.container.widgets, function (widget) {
                        widget.html_url = getFullWidgetUrl(widget);
                    });
                };
            }
        };
    }
})();
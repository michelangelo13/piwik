/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Example:
 * <div piwik-widget-by-dimension-container container="widget"></div>
 */
(function () {
    angular.module('piwikApp').directive('piwikWidgetByDimensionContainer', piwikWidgetContainer);

    piwikWidgetContainer.$inject = ['piwik', '$location'];

    function piwikWidgetContainer(piwik, $location){
        return {
            restrict: 'A',
            scope: {
                container: '='
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget-bydimension-container/widget-bydimension-container.directive.html?cb=' + piwik.cacheBuster,
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

                    var widgetsByCategory = {};
                    var firstCategoryName = null;

                    angular.forEach(scope.container.widgets, function (widget) {
                        var category = widget.subcategory.name;
                        if (!widgetsByCategory[category]) {
                            widgetsByCategory[category] = [];
                        }
                        if (!firstCategoryName) {
                            firstCategoryName = category;
                        }

                        widgetsByCategory[category].push(widget);
                    });

                    scope.firstCategoryName = firstCategoryName;
                    scope.widgetsByCategory = widgetsByCategory;


                    scope.$on("$includeContentError", function(event) {
                        scope.loading = false;
                    });
                    scope.$on("$includeContentLoaded", function(event) {
                        scope.loading = false;
                    });
                    scope.$on("$includeContentRequested", function(event) {
                        scope.loading = true;
                    });

                    scope.selectWidget = function (widget) {
                        widget.html_url = getFullWidgetUrl(widget);
                        scope.selectedWidget = widget;
                    }

                    if (scope.container.widgets && scope.container.widgets.length) {
                        scope.selectWidget(scope.container.widgets[0]);
                    }
                };
            }
        };
    }
})();
/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Example:
 * <div piwik-widget widget="widget"></div>
 */
(function () {
    angular.module('piwikApp').directive('piwikWidget', piwikWidget);

    piwikWidget.$inject = ['piwik', '$location'];

    function piwikWidget(piwik, $location){
        return {
            restrict: 'A',
            scope: {
                widget: '='
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget/widget.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, ngModel) {

                    function getFullWidgetUrl(widget) {
                        var url = widget.widget_url;

                        // available in global scope
                        var search = $location.search();
                        url += '&idSite=' + search.idSite + '&period=' + search.period;
                        url += '&date=' + search.date + '&random=' + parseInt(Math.random() * 10000);

                        return url;
                    }

                    scope.$on("$includeContentError", function(event) {
                        scope.loadingFailed = true;
                        scope.loading = false;
                    });
                    scope.$on("$includeContentLoaded", function(event) {
                        scope.loadingFailed = false;
                        scope.loading = false;
                    });
                    scope.$on("$includeContentRequested", function(event) {
                        scope.loadingFailed = false;
                        scope.loading = true;
                    });

                    if (!scope.widget.isContainer) {
                        // we want to render only if it is not a container
                        scope.widget.html_url = getFullWidgetUrl(scope.widget);
                    }
                };
            }
        };
    }
})();
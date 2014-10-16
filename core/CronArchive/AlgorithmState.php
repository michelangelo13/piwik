<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\CronArchive;

use Piwik\ArchiveProcessor\Rules;
use Piwik\Concurrency\Semaphore;
use Piwik\CronArchive;
use Piwik\Date;
use Piwik\MetricsFormatter;
use Piwik\Option;
use Piwik\Site;

/**
 * TODO
 */
class AlgorithmState
{
    const ACTIVE_REQUESTS_SEMAPHORE_NAME = 'CronArchive.ActiveRequests';
    const FAILED_REQUESTS_SEMAPHORE_NAME = 'CronArchive.FailedRequests';
    const PROCESSED_WEBSITES_SEMAPHORE = 'CronArchive.ProcessedWebsites';

    // force-timeout-for-periods default (1 hour)
    const SECONDS_DELAY_BETWEEN_PERIOD_ARCHIVES = 3600;

    /**
     * TODO
     */
    private $siteInfosCache = array();

    /**
     * TODO
     *
     * @var CronArchive
     */
    private $container;

    /**
     * TODO
     */
    public function __construct(CronArchive $container)
    {
        $this->container = $container;
    }

    /**
     * TODO
     */
    public function getLastTimestampWebsiteProcessedDay($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            if ($container->archiveAndRespectTTL) {
                Option::clearCachedOption($container->lastRunKey($idSite, "day"));
                return Option::get($container->lastRunKey($idSite, "day"));
            } else {
                return false;
            }
        });
    }

    /**
     * TODO
     */
    public function getLastTimestampWebsiteProcessedPeriods($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            if ($container->archiveAndRespectTTL) {
                Option::clearCachedOption($container->lastRunKey($idSite, "periods")); // TODO: ::get() should include an arg to clear cached option
                return Option::get($container->lastRunKey($idSite, "periods"));
            } else {
                return false;
            }
        });
    }

    /**
     * TODO
     */
    public function getSecondsSinceLastExecution($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            // For period other than days, we only re-process the reports at most
            // 1) every $processPeriodsMaximumEverySeconds
            $result = $container->startTime - $self->getLastTimestampWebsiteProcessedPeriods($idSite);

            // if timeout is more than 10 min, we account for a 5 min processing time, and allow trigger 1 min earlier
            if ($self->getProcessPeriodsMaximumEverySeconds() > 10 * 60) {
                $result += 5 * 60;
            }

            return $result;
        });
    }

    /**
     * TODO
     */
    public function getDayHasEndedMustReprocesses($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return in_array($idSite, $container->websiteDayHasFinishedSinceLastRun);
        });
    }

    /**
     * TODO
     */
    public function getIsOldReportInvalidedForWebsite($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return in_array($idSite, $container->idSitesInvalidatedOldReports);
        });
    }

    /**
     * TODO
     */
    public function getIsWebsiteArchivingForced($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return in_array($idSite, $container->shouldArchiveSpecifiedSites);
        });
    }

    /**
     * TODO
     */
    public function getShouldArchivePeriods($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            $lastTimeProcessedPeriods = $self->getLastTimestampWebsiteProcessedPeriods($idSite);
            if (empty($lastTimeProcessedPeriods)) {
                // 2) OR always if script never executed for this website before
                return true;
            }

            // (*) If the website is archived because it is a new day in its timezone
            // We make sure all periods are archived, even if there is 0 visit today
            if ($self->getDayHasEndedMustReprocesses($idSite)) {
                return true;
            }

            // (*) If there was some old reports invalidated for this website
            // we make sure all these old reports are triggered at least once
            if ($self->getIsOldReportInvalidedForWebsite($idSite)) {
                return true;
            }

            if ($self->getIsWebsiteArchivingForced($idSite)) {
                return true;
            }

            return $self->getSecondsSinceLastExecution($idSite) > $self->getProcessPeriodsMaximumEverySeconds();
        });
    }

    /**
     * TODO
     */
    public function getElapsedTimeSinceLastArchiving($idSite, $pretty = false)
    {
        $result = $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return $container->startTime - $self->getLastTimestampWebsiteProcessedDay($idSite);
        });

        if ($pretty) {
            $result = MetricsFormatter::getPrettyTimeFromSeconds($result, true, $isHtml = false);
        }

        return $result;
    }

    /**
     * TODO
     *
     * valid if last archive age is less than TTL
     */
    public function getIsExistingArchveValid($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return $self->getElapsedTimeSinceLastArchiving($idSite) < $self->getTodayArchiveTimeToLive();
        });
    }

    /**
     * TODO
     */
    public function getHasBeenProcessedSinceMidnight($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            $lastTimestampWebsiteProcessedDay = $self->getLastTimestampWebsiteProcessedDay($idSite);

            if (false === $lastTimestampWebsiteProcessedDay) {
                return true;
            }

            $timezone = Site::getTimezoneFor($idSite);

            $dateInTimezone     = Date::factory('now', $timezone);
            $midnightInTimezone = $dateInTimezone->setTime('00:00:00');

            $lastProcessedDateInTimezone = Date::factory((int) $lastTimestampWebsiteProcessedDay, $timezone);

            return $lastProcessedDateInTimezone->getTimestamp() >= $midnightInTimezone->getTimestamp();
        });
    }

    /**
     * TODO
     */
    public function getShouldSkipDayArchive($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            $isExistingArchiveValid = $self->getIsExistingArchveValid($idSite);

            // Skip this day archive if last archive was older than TTL
            $skipDayArchive = $isExistingArchiveValid;

            // Invalidate old website forces the archiving for this site
            $skipDayArchive = $skipDayArchive && !$self->getIsOldReportInvalidedForWebsite($idSite);

            // Also reprocess when day has ended since last run
            if ($self->getDayHasEndedMustReprocesses($idSite)
                // it might have reprocessed for that day by another cron
                && !$self->getHasBeenProcessedSinceMidnight($idSite)
                && !$isExistingArchiveValid
            ) {
                $skipDayArchive = false;
            }

            if ($self->getIsWebsiteArchivingForced($idSite)) {
                $skipDayArchive = false;
            }

            return $skipDayArchive;
        });
    }

    /**
     * TODO
     */
    public function getActiveRequestsSemaphore($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return new Semaphore(AlgorithmState::ACTIVE_REQUESTS_SEMAPHORE_NAME . '.' . $idSite);
        });
    }

    /**
     * TODO
     */
    public function getFailedRequestsSemaphore($idSite)
    {
        return $this->getOrSetInCache($idSite, __FUNCTION__, function (AlgorithmState $self, CronArchive $container) use ($idSite) {
            return new Semaphore(AlgorithmState::FAILED_REQUESTS_SEMAPHORE_NAME . '.' . $idSite);
        });
    }

    /**
     * TODO
     */
    public function getProcessedWebsitesSemaphore()
    {
        return $this->getOrSetInCache('none', __FUNCTION__, function (AlgorithmState $self, CronArchive $container) {
            return new Semaphore(AlgorithmState::PROCESSED_WEBSITES_SEMAPHORE);
        });
    }

    /**
     * TODO
     */
    public function getTodayArchiveTimeToLive()
    {
        return $this->getOrSetInCache('none', __FUNCTION__, function (AlgorithmState $self, CronArchive $container) {
            return Rules::getTodayArchiveTimeToLive();
        });
    }

    /**
     * Returns the delay in seconds, that should be enforced, between calling archiving for Periods Archives.
     * It can be set by --force-timeout-for-periods=X
     *
     * @return int
     *
     * TODO: revise
     */
    public function getProcessPeriodsMaximumEverySeconds()
    {
        return $this->getOrSetInCache('none', __FUNCTION__, function (AlgorithmState $self, CronArchive $container) {
            if (empty($container->forceTimeoutPeriod)) {
                return self::SECONDS_DELAY_BETWEEN_PERIOD_ARCHIVES;
            }

            // Ensure the cache for periods is at least as high as cache for today
            if ($container->forceTimeoutPeriod > $self->getTodayArchiveTimeToLive()) {
                return $container->forceTimeoutPeriod;
            }

            // TODO: should remove this log statement somehow
            $container->algorithmLogger->log("WARNING: Automatically increasing --force-timeout-for-periods from {$container->forceTimeoutPeriod} to "
                . $self->getTodayArchiveTimeToLive()
                . " to match the cache timeout for Today's report specified in Piwik UI > Settings > General Settings");

            return $self->getTodayArchiveTimeToLive();
        });
    }

    /**
     * @param $idSite
     * @param $infoKey
     * @param $calculateCallback
     * @return mixed
     */
    private function getOrSetInCache($idSite, $infoKey, $calculateCallback)
    {
        if (!isset($this->siteInfosCache[$idSite][$infoKey])) {
            $value = $calculateCallback($this, $this->container);

            $this->siteInfosCache[$idSite][$infoKey] = $value;
        }

        return $this->siteInfosCache[$idSite][$infoKey];
    }
}
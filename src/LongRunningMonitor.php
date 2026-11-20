<?php

namespace Grafite\MissionControlLaravel;

use Throwable;
use Carbon\CarbonInterval;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;

class LongRunningMonitor
{
    /**
     * Whether the job watcher has been registered.
     */
    protected bool $watchingJobs = false;

    /**
     * Whether the task watcher has been registered.
     */
    protected bool $watchingTasks = false;

    /**
     * Start times (in milliseconds) of the jobs currently running, keyed by object id.
     */
    protected array $jobStarts = [];

    /**
     * Start times (in milliseconds) of the tasks currently running, keyed by object id.
     */
    protected array $taskStarts = [];

    /**
     * Notify Mission Control when a queued job attempt runs longer than the threshold.
     *
     * Uses Queue::whenJobRunsLongerThan() on Laravel 13.40+, otherwise times the
     * job through the queue events.
     */
    public function watchJobs(float $thresholdSeconds): void
    {
        if ($this->watchingJobs) {
            return;
        }

        $this->watchingJobs = true;

        $queue = app('queue');

        if (method_exists($queue, 'whenJobRunsLongerThan')) {
            $queue->whenJobRunsLongerThan(
                CarbonInterval::seconds($thresholdSeconds),
                fn ($job, $duration) => $this->reportJob($job, $duration / 1000, $thresholdSeconds)
            );

            return;
        }

        $events = app('events');

        $events->listen(JobProcessing::class, function (JobProcessing $event) {
            $this->jobStarts[spl_object_id($event->job)] = $this->now();
        });

        $events->listen(JobAttempted::class, function (JobAttempted $event) use ($thresholdSeconds) {
            $key = spl_object_id($event->job);

            if (! isset($this->jobStarts[$key])) {
                return;
            }

            $seconds = ($this->now() - $this->jobStarts[$key]) / 1000;

            unset($this->jobStarts[$key]);

            if ($seconds > $thresholdSeconds) {
                $this->reportJob($event->job, $seconds, $thresholdSeconds, $event->connectionName);
            }
        });
    }

    /**
     * Notify Mission Control when a scheduled task runs longer than the threshold.
     *
     * Uses Schedule::whenTaskRunsLongerThan() on Laravel 13.40+, otherwise times the
     * task through the scheduler events.
     */
    public function watchTasks(Schedule $schedule, float $thresholdSeconds): void
    {
        if ($this->watchingTasks) {
            return;
        }

        $this->watchingTasks = true;

        if (method_exists($schedule, 'whenTaskRunsLongerThan')) {
            $schedule->whenTaskRunsLongerThan(
                CarbonInterval::seconds($thresholdSeconds),
                fn (Event $task, $runtime) => $this->reportTask($task, (float) $runtime, $thresholdSeconds)
            );

            return;
        }

        $events = app('events');

        $events->listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            $this->taskStarts[spl_object_id($event->task)] = $this->now();
        });

        $events->listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) use ($thresholdSeconds) {
            unset($this->taskStarts[spl_object_id($event->task)]);

            if ($event->runtime > $thresholdSeconds) {
                $this->reportTask($event->task, $event->runtime, $thresholdSeconds);
            }
        });

        $events->listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) use ($thresholdSeconds) {
            $key = spl_object_id($event->task);

            if (! isset($this->taskStarts[$key])) {
                return;
            }

            $seconds = ($this->now() - $this->taskStarts[$key]) / 1000;

            unset($this->taskStarts[$key]);

            if ($seconds > $thresholdSeconds) {
                $this->reportTask($event->task, $seconds, $thresholdSeconds);
            }
        });
    }

    /**
     * Send a long running job notification to Mission Control.
     */
    public function reportJob($job, float $seconds, float $thresholdSeconds, ?string $connection = null): bool
    {
        $name = $this->jobName($job);
        $connection = $connection ?? (method_exists($job, 'getConnectionName') ? $job->getConnectionName() : null);
        $queue = method_exists($job, 'getQueue') ? $job->getQueue() : null;
        $attempt = method_exists($job, 'attempts') ? $job->attempts() : null;

        $location = collect([$connection, $queue])->filter()->implode(':');

        $message = $name
            .($location ? " on {$location}" : '')
            .' took '.$this->format($seconds)
            .' (threshold '.$this->format($thresholdSeconds).')'
            .($attempt ? ", attempt {$attempt}" : '')
            .'.';

        return $this->notify('job-'.$name, "Long running job: {$name}", $message);
    }

    /**
     * Send a long running scheduled task notification to Mission Control.
     */
    public function reportTask(Event $task, float $seconds, float $thresholdSeconds): bool
    {
        $name = $task->getSummaryForDisplay();

        $message = "Scheduled task [{$name}] ({$task->getExpression()}) took "
            .$this->format($seconds)
            .' (threshold '.$this->format($thresholdSeconds).').';

        return $this->notify('task-'.$task->mutexName(), "Long running scheduled task: {$name}", $message);
    }

    /**
     * Send the notification, unless one was sent for the same key within the cooldown.
     */
    protected function notify(string $key, string $title, string $message): bool
    {
        try {
            $cooldown = (int) config('mission-control.long_running.cooldown', 15);

            if ($cooldown > 0 && ! cache()->add('mission-control-long-running-'.md5($key), true, now()->addMinutes($cooldown))) {
                return false;
            }

            return (bool) app(Notify::class)->send($title, 'warning', $message);
        } catch (Throwable $th) {
            // Monitoring must never break a queue worker or the scheduler.
            return false;
        }
    }

    /**
     * Resolve a readable name for the job.
     */
    protected function jobName($job): string
    {
        if (method_exists($job, 'resolveName')) {
            return $job->resolveName();
        }

        if (method_exists($job, 'getName')) {
            return $job->getName();
        }

        return get_class($job);
    }

    /**
     * Format a duration in seconds for display.
     */
    protected function format(float $seconds): string
    {
        return rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.').'s';
    }

    /**
     * The current time in milliseconds, which respects Carbon's test time.
     */
    protected function now(): float
    {
        return now()->getPreciseTimestamp(3);
    }
}

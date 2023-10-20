<?php

namespace Symbiote\SqsJobQueue\Job;


use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\SqsJobQueue\Extension\SqsQueuedJobExtension;
use Symbiote\SqsJobQueue\Task\SqsIntervalTask;
/**
 * A job that executes _scheduled_ jobs in an environment
 * where simple queues exist that don't allow long term scheduling of jobs,
 * such as SQS
 *
 * @author marcus
 */
class SqsScheduleRunnerJob implements SqsIntervalTask {

    const SCHEDULE_TIME = 45;

    /**
     *
     * @var QueuedJobService
     */
    public $queuedJobService;

    /**
     *
     * @var SqsService
     */
    public $sqsService;


    public function processScheduledJobs($message = '', $handler = '') {
        $this->queuedJobService->checkJobHealth(array(
                QueuedJob::QUEUED,
                QueuedJob::LARGE,
                SqsQueuedJobExtension::TYPE_SCHEDULED
        ));

        $processedWaiting = false;
        // run waiting jobs that haven't been re-added to the queue
        $jobs = QueuedJobDescriptor::get()->filter(array(
            'JobStatus' => array(QueuedJob::STATUS_WAIT),
            'Implementation:not' => SqsScheduleRunnerJob::class,
        ));

        foreach ($jobs as $job) {
            $processedWaiting = true;
            // bombs away!
            $this->queuedJobService->runJob($job->ID);
            unset($job);
        }

        if (!$processedWaiting) {
            $jobs = QueuedJobDescriptor::get()->filter(array(
                'JobStatus' => array(QueuedJob::STATUS_NEW, QueuedJob::STATUS_WAIT),
                'JobType'       =>  SqsQueuedJobExtension::TYPE_SCHEDULED,
                'Implementation:not' => SqsScheduleRunnerJob::class,
                'StartAfter:LessThan' => DBDatetime::now()->getValue()
            ));

            foreach ($jobs as $job) {
                // bombs away!
                $this->queuedJobService->runJob($job->ID);
                unset($job);
            }
        }
    }

    public function getInterval()
    {
        return self::SCHEDULE_TIME;
    }

    public function getTaskName()
    {
        return SqsScheduleRunnerJob::class;
    }

}

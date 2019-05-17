<?php

namespace Symbiote\SqsJobQueue\Service;


use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;



/**
 * A shell around QueuedJobService that performs some additional sanity checks before
 * executing things. 
 *
 * @author marcus
 */
class QueuedJobRunner
{

    /**
     *
     * @var QueuedJobService
     */
    public $queuedJobService;

    public function runJob($jobId)
    {
        $descriptor = QueuedJobDescriptor::get()->byID($jobId);
        if ($descriptor && $descriptor->ID) {
            // if it's actually running, don't start!!
            if ($descriptor->JobStatus == QueuedJob::STATUS_RUN) {
                return;
            }
            return $this->queuedJobService->runJob($jobId);
        }
    }

}

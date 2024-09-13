<?php

namespace Symbiote\SqsJobQueue\Task;

use SilverStripe\Dev\BuildTask;
use Symbiote\SqsJobQueue\Service\SqsService;

/**
 * @author marcus
 */
class CreateScheduledRunnerTask extends BuildTask
{
    public function run($request)
    {
        if (!$request->getVar('create')) {
            echo "Please supply the 'create' var";
            return;
        }

        $sqs = singleton(SqsService::class);

        $sqs->sendSqsMessage('Scheduled Job Runner', 'processScheduledJobs', 5);
    }
}

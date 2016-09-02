<?php

/**
 * @author marcus
 */
class CreateScheduledRunnerTask extends BuildTask {

    public function run($request) {
        if (!$request->getVar('create')) {
            echo "Please supply the 'create' var";
            return;
        }
        
        $sqs = singleton('SqsService');

        $sqs->sendSqsMessage('Scheduled Job Runner', 'processScheduledJobs', 5);
    }

}

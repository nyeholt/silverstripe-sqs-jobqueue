<?php

/**
 * @author marcus
 */
class SqsAdmin extends ModelAdmin {
    private static $url_segment = 'sqsadmin';
    private static $managed_models = array('SqsQueueState');
    private static $menu_title = 'SQS';
    
    public function getEditForm($id = null, $fields = null) {
        $form = parent::getEditForm($id, $fields);
        
        if ($this->modelClass == 'SqsQueueState') {
            // remove the 'add' buttons from the list, and ensure there's at least one in 
            // existence
            $grid = $form->Fields()->dataFieldByName('SqsQueueState');
            if ($grid) {
                $grid->getConfig()->removeComponentsByType('GridFieldAddNewButton');
                $grid->getConfig()->removeComponentsByType('GridFieldDeleteAction');
            }
            
            $sqs = SqsQueueState::get()->filter('Title', 'SqsScheduleRunnerJob')->first();
            if (!$sqs) {
                $sqs = SqsQueueState::create();
                $sqs->update(array(
                    'Title' => 'SqsScheduleRunnerJob'
                ));
                $sqs->write();
            }
            
            $form->Fields()->push(ReadonlyField::create('SqsWorker', 'SQS Worker Last Run', $sqs->WorkerRun));
            $form->Fields()->push(ReadonlyField::create('ScheduledChecker', 'SQS Scheduled Last Run', $sqs->LastScheduledStart));
            $form->Fields()->push(ReadonlyField::create('TriggerDate', 'SQS jobs last added', $sqs->LastAddedScheduleJob));
            
            $lastQueueRun = strtotime($sqs->WorkerRun);
            $lastScheduleRun = strtotime($sqs->LastScheduledStart);
            $lastAddedSchedule = strtotime($sqs->LastAddedScheduleJob); 
            
            $addTrigger = true;
//            if ($lastQueueRun - $lastScheduleRun > 600 && time() - $lastAddedSchedule > 60) {
//                $addTrigger = true;
//                $form->sessionMessage('Last scheduled message received more than 10 minutes prior to last SQS view, try triggering jobs below', 'bad');
//            }

            if ($addTrigger) {
                $form->Actions()->push(FormAction::create('triggerSqsJob', 'Check default SQS jobs'));
            }

        }
        return $form;
    }
    
    public function triggerSqsJob($data, Form $form) {
        singleton('SqsService')->checkScheduledTasks();
        return $this->redirectBack();
    }
}

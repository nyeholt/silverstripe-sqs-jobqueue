<?php

namespace Symbiote\SqsJobQueue\Control;


use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Form;
use SilverStripe\Admin\ModelAdmin;

use Symbiote\SqsJobQueue\Model\SqsQueueState;
use Symbiote\SqsJobQueue\Job\SqsScheduleRunnerJob;
use Symbiote\SqsJobQueue\Service\SqsService;



/**
 * @author marcus
 */
class SqsAdmin extends ModelAdmin {
    private static $url_segment = 'sqsadmin';
    private static $managed_models = array(SqsQueueState::class);
    private static $menu_title = 'SQS';

    public function getEditForm($id = null, $fields = null) {
        $form = parent::getEditForm($id, $fields);

        if ($this->modelClass == SqsQueueState::class) {
            // remove the 'add' buttons from the list, and ensure there's at least one in
            // existence
            $grid = $form->Fields()->dataFieldByName(SqsQueueState::class);
            if ($grid) {
                $grid->getConfig()->removeComponentsByType(GridFieldAddNewButton::class);
                $grid->getConfig()->removeComponentsByType(GridFieldDeleteAction::class);
            }

            $sqs = SqsQueueState::get()->filter('Title', SqsScheduleRunnerJob::class)->first();
            if (!$sqs) {
                $sqs = SqsQueueState::create();
                $sqs->update(array(
                    'Title' => SqsScheduleRunnerJob::class
                ));
                $sqs->write();
            }

            $form->Fields()->push(ReadonlyField::create('SqsWorker', 'SQS Worker Last Run', $sqs->WorkerRun));
            $form->Fields()->push(ReadonlyField::create('ScheduledChecker', 'SQS Scheduled Last Run', $sqs->LastScheduledStart));
            $form->Fields()->push(ReadonlyField::create('TriggerDate', 'SQS jobs last added', $sqs->LastAddedScheduleJob));

            // Commenting out variable definitions as they are no longer used
            // $lastQueueRun = strtotime($sqs->WorkerRun ?? '');
            // $lastScheduleRun = strtotime($sqs->LastScheduledStart ?? '');
            // $lastAddedSchedule = strtotime($sqs->LastAddedScheduleJob ?? '');

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
        singleton(SqsService::class)->checkScheduledTasks();
        return $this->redirectBack();
    }
}

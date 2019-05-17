<?php

namespace Symbiote\SqsJobQueue\Extension;


use SilverStripe\Forms\Form;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Core\Extension;



/**
 * 
 *
 * @author marcus
 */
class SqsQueuedJobExtension extends Extension
{
    const TYPE_SCHEDULED = 'Scheduled';

    public function updateEditForm(Form $form)
    {
        $gridfield = $form->Fields()->dataFieldByName(QueuedJobDescriptor::class);

        if ($gridfield) {
            /* @var $gridfield GridField */
            $component = $gridfield->getConfig()->getComponentByType(GridFieldDetailForm::class);
            if ($component) {
                $component->setItemEditFormCallback(function (Form $form, $requestItem) {
                    $fields = $form->Fields();
                    $jobType = $fields->dataFieldByName('JobType');
                    if ($jobType) {
                        $options = $jobType->getSource();
                        $options[self::TYPE_SCHEDULED] = self::TYPE_SCHEDULED;
                        $jobType->setSource($options);
                    }
                });
            }
        }
    }
}
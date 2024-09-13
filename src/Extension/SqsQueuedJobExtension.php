<?php

namespace Symbiote\SqsJobQueue\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * @author marcus
 */
class SqsQueuedJobExtension extends Extension
{
    public const TYPE_SCHEDULED = 'Scheduled';

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

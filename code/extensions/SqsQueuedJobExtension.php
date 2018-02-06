<?php

/**
 * 
 *
 * @author marcus
 */
class SqsQueuedJobExtension extends Extension
{
    const TYPE_SCHEDULED = 'Scheduled';

    public function updateEditForm(\Form $form)
    {
        $gridfield = $form->Fields()->dataFieldByName('QueuedJobDescriptor');

        if ($gridfield) {
            /* @var $gridfield GridField */
            $component = $gridfield->getConfig()->getComponentByType('GridFieldDetailForm');
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
<?php

namespace Symbiote\SqsJobQueue\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * @author marcus
 */
class SqsQueueState extends DataObject
{
    private static $table_name = 'SqsQueueState';

    private static $db = [
        'Title' => 'Varchar(128)',
        'WorkerRun' => DBDatetime::class,
        'LastScheduledStart' => DBDatetime::class,
        'LastAddedScheduleJob' => DBDatetime::class,
    ];

    private static $summary_fields = [
        'Title',
        'LastEdited',
        'WorkerRun',
        'LastScheduledStart',
        'LastAddedScheduleJob'
    ];
}

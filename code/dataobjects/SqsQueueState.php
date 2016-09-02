<?php

/**
 * @author marcus
 */
class SqsQueueState extends DataObject {
    private static $db = array(
        'Title'     => 'Varchar(128)',
        'WorkerRun' => 'SS_Datetime',
        'LastScheduledStart' => 'SS_Datetime',
        'LastAddedScheduleJob'   => 'SS_Datetime',
    );
    
    private static $summary_fields = array(
        'Title', 'LastEdited', 'WorkerRun', 'LastScheduledStart', 'LastAddedScheduleJob'
    );
}

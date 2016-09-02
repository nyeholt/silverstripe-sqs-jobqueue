<?php

/**
 * @author marcus
 */
interface SqsIntervalTask
{
    /**
     * Returns the interval of execution in seconds
     */
    public function getInterval();
    
    /**
     * Gets the name of the task
     */
    public function getTaskName();
}

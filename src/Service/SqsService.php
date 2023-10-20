<?php

namespace Symbiote\SqsJobQueue\Service;


use SilverStripe\ORM\FieldType\DBDatetime;
use Exception;


use Symbiote\SqsJobQueue\Model\SqsQueueState;
use Symbiote\SqsJobQueue\Task\SqsIntervalTask;



/**
 * @author marcus
 */
class SqsService {

    const MAX_DEPTH = 10;

    /**
     *
     * @var Aws\Sqs\SqsClient
     */
    public $client;
    public $queueName = 'jobqueue';
    public $queueUrl;


    /**
     * A map of handlers for messages that get sent. maps the message name to the
     * object that handles that message (as a method)
     *
     * @var array
     */
    public $handlers = array();

    /**
     * The list of tasks that the system will track as being regularly self triggered
     *
     * @var array
     */
    public $defaultTasks = array();

    public function __call($name, $arguments) {
        $message = array('args' => $arguments);
        return $this->sendSqsMessage($message, $name);
    }

    public function sendSqsMessage($message, $handler, $delay = 0) {
        if (!is_array($message)) {
            $message = array('message' => $message);
        }

        if (!isset($message['handler'])) {
            $message['handler'] = $handler;
        }

        $properties = array(
            'QueueUrl' => $this->getQueueUrl(),
            'MessageBody' => json_encode($message)
        );

        if ($delay > 0) {
            $properties['DelaySeconds'] = $delay;
        }

        $this->client->sendMessage($properties);
    }

    protected function getQueueUrl() {
        if (!$this->queueUrl) {
            $result = $this->client->getQueueUrl(array('QueueName' => $this->queueName));
            $this->queueUrl = $result->get('QueueUrl');
        }
        return $this->queueUrl;
    }

    public function readQueue($number = 0) {
        if ($number++ >= self::MAX_DEPTH) {
            return [];
        }

        $result = $this->client->receiveMessage(array(
            'QueueUrl' => $this->getQueueUrl(),
        ));

        $messageBody = null;
        $jobs = array();
        if ($result && $messages = $result->get('Messages')) {
            foreach ($messages as $message) {
                $workException = null;
                // Do something with the message
                $messageBody = $message['Body'];
                $data = json_decode($message['Body'], true);

                if ($data && isset($data['handler'])) {
                    if (isset($data['message'])) {
                        $this->updateTaskState($data['message']);
                    }

                    $name = $data['handler'];
                    $handler = isset($this->handlers[$name]) ? $this->handlers[$name] : $this;
                    $method = method_exists($handler, $name) ? $name : 'handleCall';
                    if (!method_exists($handler, $method)) {
                        $handler = $this;
                        $method = 'handleCall';
                    }
                    $args = isset($data['args']) ? $data['args'] : $data;
                    $jobs[$message['ReceiptHandle']] = array(
                        'name' => get_class($handler),
                        'method'    => $message['Body'],
                    );

                    try {
                        // check whether this handler should be restarted; we add immediately
                        // so that any task specific failure doesn't stop the _next_ run
                        if ($this->canRestartTask($handler)) {
                            $this->sendSqsMessage($handler->getTaskName(), $method, $handler->getInterval());
                        }

                        call_user_func_array(array($handler, $method), $args);
                    } catch (Exception $ex) {
                        $workException = $ex;
                    }
                }

                $this->client->deleteMessage(array(
                    'QueueUrl' => $this->getQueueUrl(),
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ));

                if ($workException) {
                    throw $workException;
                }
            }

            // if we had a message body, let's look for it again
            if ($messageBody) {
                $moreJobs = $this->readQueue($number);
                if ($moreJobs && count($moreJobs)) {
                    $jobs = array_merge($jobs, $moreJobs);
                }
            }
            return $jobs;
        }
    }

    /**
     * Update the run-record state of a given task, if it tracks running time
     *
     * @param string $name
     */
    protected function updateTaskState($name) {
        $state = SqsQueueState::get()->filter('Title', $name)->first();
        if ($state && $state->ID) {
            $state->WorkerRun = date('Y-m-d H:i:s');
            $state->write();
            $state->destroy();
            unset($state);
        }
    }

    /**
     * Check a task to see if it should be re-added to the execution queue
     *
     * Returns a boolean indicating whether the task should be re-added to SQS
     *
     * @return boolean
     */
    protected function canRestartTask($task) {
        if ($task instanceof SqsIntervalTask) {
            $state = SqsQueueState::get()->filter('Title', $task->getTaskName())->first();

            // NOTE(Marcus) 2018-08-18
            //
            // Fix this with corrected date format; on a plane right now,
            // can't look up the various options
            $now = date('Y-m-d H:i:s'); //  DBDatetime::now()->Format('Y-m-d H:i:s');
            if ($state && $state->ID) {
                // check if "now" is _less_ than the delay time, meaning we shouldn't be running, possibly because
                // multiple versions of this task got added to the queue
                if ((strtotime($now) - $task->getInterval()) < strtotime($state->LastScheduledStart)) {
                    return false;
                }
            } else {
                $state = SqsQueueState::create(array(
                    'Title'     => $task->getTaskName(),
                ));
            }

            $state->LastScheduledStart = $now;
            $state->write();
            return true;
        }

        return false;
    }

    /**
     * Checks through all the scheduled tasks that are expected to exist
     */
    public function checkScheduledTasks() {
        if (count($this->defaultTasks)) {
            $now = DBDatetime::now()->Format(DBDatetime::ISO_DATETIME);

            foreach ($this->defaultTasks as $task => $method) {
                $state = SqsQueueState::get()->filter('Title', $task)->first();

                $new = false;
                if (!$state) {
                    $state = SqsQueueState::create(array(
                        'Title' => $task
                    ));
                    $new = true;
                    $state->write();
                }

                // let's see if the dates are okay.
                $lastQueueRun = strtotime($state->WorkerRun ?? '');
                $lastScheduleRun = strtotime($state->LastScheduledStart ?? '');
                $lastAdded = strtotime($state->LastAddedScheduleJob ?? '');

                $a = $state->WorkerRun;
                $b = $state->LastScheduledStart;

                // if the last time it was added is more than 10 minutes ago, AND
                // the last run is more than 10 minutes since it was last started OR it's new OR it was last run more than 15 minutes ago
                if ((time() - $lastAdded > 600) && ((($lastQueueRun - $lastScheduleRun) > 600) || $new || (time() - $lastQueueRun > 900))) {
                    $state->LastAddedScheduleJob = $now;
                    $state->write();
                    $this->sendSqsMessage($task, $method);
                }
            }
        }
    }

    public function handleCall($args) {
        if (!count($args)) {
            return;
        }
//        $workerImpl = ClassInfo::implementorsOf('GearmanHandler');
//        $path = array_shift($args);
//        $method = array_shift($args);
//
//        foreach ($workerImpl as $type) {
//            $obj = Injector::inst()->get($type);
//            if ($obj->getName() == $method) {
//                call_user_func_array(array($obj, $method), $args);
//            }
//        }
    }

}

# SilverStripe SQS job queue

A module for sending and consuming SQS tasks. Can be configured to work as the trigger for queuejobs. 

When used as the queuedjobs handler, there's a few slight changes to how queuedjobs are run - aside from not needing Cron jobs anymore. 

* On calling QueuedJobService->queueJob, a message is sent to SQS
* A consumer picks up that message, then processes that job ID
* All "type 1" aka immediate jobs are subsequently processed in that execution thread
* If the added job is meant to be run in the future, the handler sets the job type to 'Scheduled'. 
* A separate SQS task runs that looks for any jobs of type "Scheduled" and executes those
* That task re-queues itself for to run again in 30 seconds time
* The "Scheduled" task runner will also look for any job currently sitting in the "wait" status; this is how paused jobs get picked up for further execution without needing to trigger another SQS message

## Configuration for use as the queuejobs handler

```
---
Name: jobrunner
After: queuedjobs
---
SilverStripe\Core\Injector\Injector:
  QueueHandler:
    class: Symbiote\SqsJobQueue\Service\SqsQueueHandler
    properties:
      sqsService: %$SqsService
  SqsClient:
    class: Aws\Sqs\SqsClient
    constructor:
      connection_details: 
        region: ap-southeast-2
        version: latest
        credentials: 
          key: YourKey
          secret: YourSecret
```


That expects a queue to exist with the name 'jobqueue' - if the queue name is different, 

```
SilverStripe\Core\Injector\Injector:
  SqsService:
    properties:
      queueName: your-queue-name

```


## Writing a task

Define a class with a method in it. This is the task runner; no need for any
specific implementations. 

eg

```php

namespace \Whatever\Class\Implements;

class Work {

    public function doStuff() {

    }
}
```

Add configuration to your project that binds the method name to the SqsService

```yml
SilverStripe\Core\Injector\Injector:
  MyJobName: 
    class: \Whatever\Class\Implements\Work
  SqsService:
    properties:
      handlers: 
        doStuff: %$MyJobName
```

## Triggering tasks

To trigger a task, the call

`$sqsService->sendSqsMessage(['args' => ['param1', 'param2']], 'taskName');` 

for convenience, SqsService implements a `__call()` method that remaps a call like

`$sqsService->taskName($arg1, $arg2)` 

into 

`$sqsService->sendSqsMessage(['args' => [$arg1, $arg2']], 'taskName');`

`sendSqsMessage` in turn converts this into a message structure such as

```php
$message = [
    'message' => [
        'args' => [$arg1, $arg2'],
        'handler' => 'taskName',
    ]
];

$sqsMessage = [
    'QueueUrl' => 'sqs://in.amazon'
    'MessageBody' => json_encode($message)
];

```

So, to manually trigger the messages, create the `$sqsMessage` structure from
your own code, and send using `$this->client->sendMessage($sqsMessage);`


## Running

```
php vendor/symbiote/silverstripe-sqs-jobqueue/sqs-worker.php
```


## Development environments

If you don't have SQS available, you can run a file-based queue system by swapping
out the AWS queue for the file based SQS queue.

```
---
Name: local_sqs_config
After: '#sqs_config'
---
SilverStripe\Core\Injector\Injector:
  SqsService:
    properties:
      client: %$FileSqsClient
  FileSqsClient:
    class: Symbiote\SqsJobQueue\Service\FileBasedSqsQueue

```

By default, this will create serialised data in sqs-jobqueue/code/service/.queueus (configurable
on the FileBasedSqsQueue class). 

Run sqs-worker as before.

### Docker

If you're using https://github.com/symbiote/docker-runtime, this will spin up an `sqsrunner` container for you that runs the sqs-worker automatically.

### Troubleshooting

So for a project, your config may look something like this where it defaults to the file-based queue system only for test environments:

```
---
Name: prod_sqs
After: '#sqs_config'
---
SilverStripe\Core\Injector\Injector:
  Symbiote\SqsJobQueue\Service\SqsService:
    properties:
      client: '%$SqsClient'
  SqsClient:
    class: Aws\Sqs\SqsClient
    constructor:
      connection_details:
        region: ap-southeast-2
        version: latest
---
Name: dev_sqs
After: '#sqs_config'
Only:
  environment: test
---
SilverStripe\Core\Injector\Injector:
  Symbiote\SqsJobQueue\Service\SqsService:
    properties:
      client: '%$Symbiote\SqsJobQueue\Service\FileBasedSqsQueue'
```

In the production environment you will still need to provide the `credentials` using local config.

To get the file-based queue system working, your local config will need something like this:

```
---
Name: jobrunner
After: 
  - queuedjobs
---
SilverStripe\Core\Injector\Injector:
  QueueHandler:
    class: Symbiote\SqsJobQueue\Service\SqsQueueHandler
    properties:
      sqsService: %$Symbiote\SqsJobQueue\Service\SqsService
  Symbiote\SqsJobQueue\Service\SqsService:
    properties:
      queueName: %sqs_jobqueue_name%
---
Name: sqs_location
After: '#sqs_config'
---
SilverStripe\Core\Injector\Injector:
  Symbiote\SqsJobQueue\Service\FileBasedSqsQueue:
    properties:
      queuePath: /var/www/html/mysite/fake-sqs-queues
```

The important part is making sure `/var/www/html/mysite/fake-sqs-queues` is writable since that's where your queued jobs will be written to.

#### Docker Testing

The simplest way to test this once all your configs are in place is to:

* Queue up a job (e.g. using the https://github.com/symbiote/silverstripe-queuedjobs/ module)
* Ensure a file is written to `/var/www/html/mysite/fake-sqs-queues`
* Run `docker logs sqsrunner` to see whether it picked up the job
* View the queued jobs admin to ensure the job was completed

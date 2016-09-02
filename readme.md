

## Config

```
---
Name: jobrunner
After: queuedjobs
---
Injector:
  QueueHandler:
    class: SqsQueueHandler
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
Injector:
  SqsService:
    properties:
      queueName: your-queue-name

```
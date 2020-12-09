<?php

namespace Symbiote\SqsJobQueue\Service;


use SilverStripe\Core\Injector\Factory;



/**
 * @author marcus
 */
class SqsClientFactory implements Factory {
    public function create($service, array $params = array()) {
        if (count($params)) {
            return \Aws\Sqs\SqsClient::factory(array_values($params)[0]);
        } else {
            return \Aws\Sqs\SqsClient::factory();
        }
    }
}

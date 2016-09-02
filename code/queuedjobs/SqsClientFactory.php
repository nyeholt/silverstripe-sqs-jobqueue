<?php

/**
 * @author marcus
 */
class SqsClientFactory implements \SilverStripe\Framework\Injector\Factory {
    public function create($service, array $params = array()) {
        if (count($params)) {
            return \Aws\Sqs\SqsClient::factory(array_values($params)[0]);
        } else {
            return \Aws\Sqs\SqsClient::factory();
        }
    }
}

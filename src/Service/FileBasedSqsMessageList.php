<?php

namespace Symbiote\SqsJobQueue\Service;

/**
 * @author marcus
 */
class FileBasedSqsMessageList
{
    protected $messages = [];

    public function __construct()
    {
    }

    public function add($msg)
    {
        $this->messages[] = $msg;
    }

    public function get($key)
    {
        if ($key === 'Messages') {
            return $this->messages;
        }
        return $key;
    }
}

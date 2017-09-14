<?php

/**
 * @author marcus
 */
class FileBasedSqsQueue
{
    public $queuePath;

    public function __construct()
    {
        
    }

    protected function getQueuePath()
    {
        if (!$this->queuePath) {
            $this->queuePath = __DIR__.'/.queues';
        }
        if (!is_dir($this->queuePath)) {
            mkdir($this->queuePath, 02770, true);
        }

        return $this->queuePath;
    }

    public function getQueueUrl()
    {
        return new FileBasedSqsMessageList();
    }

    public function sendMessage($message)
    {
        $data = json_encode($message);

        $path = $this->getQueuePath();

        $name = md5($data);

        file_put_contents($path.'/'.$name, $data);
    }

    public function receiveMessage($params = [])
    {
        $messages = glob($this->getQueuePath().'/*');
        $all = new FileBasedSqsMessageList();
        foreach ($messages as $file) {
            $content = file_get_contents($file);
            if (strlen($content)) {
                $data = json_decode($content, true);
                $message = [
                    'Body' => isset($data['MessageBody']) ? json_encode($data['MessageBody']) : '',
                    'ReceiptHandle' => $file
                ];
                
                $all->add($message);
            }
        }
        return $all;
    }
    
    public function deleteMessage($params) {
        $file = isset($params['ReceiptHandle']) ? $params['ReceiptHandle'] : null;
        if ($file && file_exists($file) && strpos($file, $this->getQueuePath()) !== false) {
            unlink($file);
        }
    }
    
}

class FileBasedSqsMessageList {
    protected $messages = [];

    public function __construct() {

    }

    public function add($msg) {
        $this->messages[] = $msg;
    }

    public function get($key) {
        if ($key === 'Messages') {
            return $this->messages;
        }
        return $key;
    }
}
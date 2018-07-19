<?php

/**
 * @author marcus
 */
class FileBasedSqsQueue
{
    const SYS_KEY = '__src_system';

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
        $message[self::SYS_KEY] = BASE_PATH;
        $data = json_encode($message);

        $path = $this->getQueuePath();

        $name = uniqid(md5($data)."_");

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

                //if message has delay
                if(isset($data["DelaySeconds"])) {
                    //get file age
                    $time_created = filectime($file);
                    $time_now = strtotime(date("Y-m-d H:i:s"));
                    $time_alive = $time_now - $time_created;

                    //if too young, skip message
                    if($time_alive < $data["DelaySeconds"]) {
                        continue;
                    }
                }

                if (isset($data[self::SYS_KEY]) && $data[self::SYS_KEY] == BASE_PATH) {
                    $message = [
                        'Body' => isset($data['MessageBody']) ? (is_string($data['MessageBody']) ? $data['MessageBody'] : json_encode($data['MessageBody']))  : '',
                        'ReceiptHandle' => $file
                    ];
                    $all->add($message);
                }
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
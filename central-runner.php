#!/usr/bin/php
<?php

if(PHP_SAPI !== 'cli') {
	echo "Can't be run from a web request, you have to run it on the command-line.";
	die();
}

const SYS_KEY = '__src_system';
const SQS_PATH = 'sqs-jobqueue/sqs-worker.php';

$settings = [
    'queuePath' => '/tmp/fake-sqs-queues',
];


$localConfig = __DIR__.'/runner-settings.php';
if (file_exists($localConfig)) {
    $local = include $localConfig;
    $settings = array_replace_recursive($settings, $local);
}


$path = $settings['queuePath'];

if (is_dir($path)) {
    $messages = glob($path . '/*');

    foreach ($messages as $file) {
        $content = file_get_contents($file);
        echo "Saw $file " . SYS_KEY ."\n";
        if (strlen($content)) {
            $data = json_decode($content, true);
            $sqsCmd = $data[SYS_KEY] .'/' . SQS_PATH;
            if (isset($data[SYS_KEY]) && file_exists($data[SYS_KEY] .'/' . SQS_PATH)) {

                $cmd = "php " . $data[SYS_KEY] .'/' . SQS_PATH . ' once';

                echo "Executing $cmd \n";

                echo `$cmd`;
            }
        }
    }
}

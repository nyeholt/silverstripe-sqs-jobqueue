#!/usr/bin/php
<?php
if (PHP_SAPI !== 'cli') {
    echo "Can't be run from a web request, you have to run it on the command-line.";
    die();
}

const SYS_KEY = '__src_system';
const SQS_PATH = 'sqs-jobqueue/sqs-worker.php';
const PER_FILE_THRESHOLD = 13;

$settings = [
    'queuePath' => __DIR__.'/fake-queue',
];

$localConfig = __DIR__.'/runner-settings.php';
if (file_exists($localConfig)) {
    $local    = include $localConfig;
    $settings = array_replace_recursive($settings, $local);
}

// ie. Linux - sys_get_temp_dir() - /tmp
// ie. Windows - sys_get_temp_dir() - C:\Users\USERNAME_HERE\AppData\Local\Temp
$settings['queuePath'] = str_replace('/tmp', sys_get_temp_dir(), $settings['queuePath']);

if (!file_exists($settings['queuePath'])) {
    mkdir($settings['queuePath'], 02770, true);
}

$loggingFunction = function ($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message ."\n";
};

$path = $settings['queuePath'];

$runningFunction = function ($logFunc, $path) {

    $max_memory = 128 * 1024 * 1024;

    $logFunc("Running with memory limit of {$max_memory} B");


    $executions = 20000;
    $i          = 0;

    // how many times has a file been tried?
    $fileAttempts = [];

    if (is_dir($path)) {
        $logFunc("Watching: {$path}");
        while ($i++ < $executions) {
            clearstatcache(true);
            try {

                $messages = glob($path.'/*');
                
                foreach ($messages as $file) {
                    $attempts = isset($fileAttempts[$file]) ? $fileAttempts[$file] : 0;
                    if ($attempts >= PER_FILE_THRESHOLD) {
                        $logFunc("Failed executing file $file, removing");
                        unset($fileAttempts[$file]);
                        if (file_exists($file)) {
                            @unlink($file);
                        }
                        continue;
                    }
                    $fileAttempts[$file] = ++$attempts;

                    $content = file_get_contents($file);
                    $logFunc("Saw $file ".SYS_KEY . " (in cycle $i )");
                    if (strlen($content)) {
                        $data   = json_decode($content, true);
                        $sqsCmd = $data[SYS_KEY].'/'.SQS_PATH;
                        if (isset($data[SYS_KEY]) && file_exists($data[SYS_KEY].'/'.SQS_PATH)) {

                            $cmd = "php ".$data[SYS_KEY].'/'.SQS_PATH.' once';

                            $logFunc("Executing $cmd");

                            passthru($cmd);
                        }

                        $memory = memory_get_peak_usage(false);
                        if ($memory > $max_memory) {
                            // break out of the loop
                            $logFunc("Memory expired: $memory bytes used of $max_memory. Closing for restart");
                            return;
                        }
                        $logFunc("Used $memory B ram");
                    }
                }
            } catch (Exception $ex) {
                $logFunc("Queue read failed (".get_class($ex)."): ".$ex->getMessage()."\n");
                echo($ex->getTraceAsString());
                $logFunc( "\n");

                if (strpos($ex->getMessage(), "Couldn't run query") !== false) {
                    $logFunc( "Unrecoverable failure, closing for restart\n");
                    return;
                }
            }
            sleep(5);
        }
    } else {
        echo "Queue path $path not found \n";
    }
};

$loggingFunction("Started queue-watcher");
$runningFunction($loggingFunction, $path);
$loggingFunction("Queue watcher shutdown");

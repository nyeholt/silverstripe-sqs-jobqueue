#!/usr/bin/php
<?php

// CLI specific bootstrapping
use SilverStripe\Control\CLIRequestBuilder;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Core\CoreKernel;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

use Symbiote\SqsJobQueue\Service\SqsService;
use Symbiote\SqsJobQueue\Service\FileBasedSqsQueue;

require dirname(__DIR__) . '/vendor/silverstripe/framework/src/includes/autoload.php';

// Ensure that people can't access this from a web-server
if (!in_array(PHP_SAPI, ["cli"])) {
    echo "cli-script.php can't be run from a web request, you have to run it on the command-line.";
    die();
}

// Build request and detect flush
$request = CLIRequestBuilder::createFromEnvironment();

// Default application
$kernel = new CoreKernel(BASE_PATH);
$kernel->boot(true);

$loggingFunction = function ($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message ."\n";
};

/**
 * Process arguments and load them into the $_GET and $_REQUEST arrays
 * For example,
 * sake my/url somearg otherarg key=val --otherkey=val third=val&fourth=val
 *
 * Will result int he following get data:
 *   args => array('somearg', 'otherarg'),
 *   key => val
 *   otherkey => val
 *   third => val
 *   fourth => val
 */
if(isset($_SERVER['argv'][2])) {
    $args = array_slice($_SERVER['argv'],2);
    if(!isset($_GET)) $_GET = array();
    if(!isset($_REQUEST)) $_REQUEST = array();
    foreach($args as $arg) {
       if(strpos($arg,'=') == false) {
           $_GET['args'][] = $arg;
       } else {
           $newItems = array();
           parse_str( (substr($arg,0,2) == '--') ? substr($arg,2) : $arg, $newItems );
           $_GET = array_merge($_GET, $newItems);
       }
    }
  $_REQUEST = array_merge($_REQUEST, $_GET);
}


$loggingFunction("Initialising SqsWorker");

/**
 * Closure to provide a small level of global scope protection
 */
$runningFunction = function ($logFunc, $perpetual = true) {

    $service = Injector::inst()->get(SqsService::class);
    $max_memory = Config::inst()->get('SqsWorker', 'mem_limit');
    if (!$max_memory) {
        $max_memory = 128 * 1024 * 1024;
    }

    $logFunc("Running with memory limit of {$max_memory} B");

    while (true) {
        // clear the file system stat cache
        clearstatcache(true);

        try {
            if ($service->client instanceof FileBasedSqsQueue) {
                $logFunc("Looking for jobs in " . $service->client->queuePath);
            }
            $executed = $service->readQueue();
            if (count($executed)) {
                foreach ($executed as $job) {
                    $logFunc("Ran {$job['name']} - {$job['method']}");
                    $memory = memory_get_peak_usage(false);
                    if ($memory > $max_memory) {
                        // break out of the loop
                        $logFunc("Memory expired: $memory bytes used of $max_memory. Closing for restart");
                        return;
                    }
                }
            }



            $fileLoc = $service->client instanceof FileBasedSqsQueue ? $service->client->queuePath : '';
            $logFunc("No jobs found in " . get_class($service->client) . ': ' . $fileLoc);

            if ($perpetual) {
                $service->checkScheduledTasks();
            }
        } catch (Exception $ex) {
            echo "Queue read failed (" . get_class($ex) . "): " . $ex->getMessage() . "\n";
            echo $ex->getTraceAsString();
            echo "\n";

            if (strpos($ex->getMessage(), "Couldn't run query") !== false) {
                echo "Unrecoverable failure, closing for restart\n";
                return;
            }
        }

        if (!$perpetual) {
            break;
        }
        sleep(6);
    }
};


// are we expecting to run forever? Or is this a once-through and die process?
$perpetual = true;
if(isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === 'once') {
	$perpetual = false;
}

$loggingFunction("Started SqsWorker " . ($perpetual ? "forever " : "once"));
$runningFunction($loggingFunction, $perpetual);
$loggingFunction("SqsWorker shutdown");


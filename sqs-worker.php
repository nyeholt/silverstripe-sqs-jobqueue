#!/usr/bin/php
<?php
/**
 * Ensure that people can't access this from a web-server
 */
if(isset($_SERVER['HTTP_HOST'])) {
	echo "cli-script.php can't be run from a web request, you have to run it on the command-line.";
	die();
}
/**
 * Identify the cli-script.php file and change to its container directory, so that require_once() works
 */
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir(dirname(dirname($_SERVER['SCRIPT_FILENAME'])) . '/framework');

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

// flush config before spinning the worker up
$_GET['flush'] = 1;

$loggingFunction("Initialising SqsWorker");

/**
 * Include SilverStripe's core code
 */
require_once("core/Core.php");
global $databaseConfig;
// We don't have a session in cli-script, but this prevents errors
$_SESSION = null;
// Connect to database
require_once("model/DB.php");
DB::connect($databaseConfig);
// Get the request URL from the querystring arguments
$_SERVER['REQUEST_URI'] = BASE_URL;
// Direct away - this is the "main" function, that hands control to the apporopriate controller
DataModel::set_inst(new DataModel());


/**
 * Closure to provide a small level of global scope protection
 */
$runningFunction = function ($logFunc, $perpetual = true) {

    $service = Injector::inst()->get('SqsService');
    $max_memory = Config::inst()->get('SqsWorker', 'mem_limit');
    if (!$max_memory) {
        $max_memory = 128 * 1024 * 1024;
    }

    $logFunc("Running with memory limit of {$max_memory} B");

    while (true) {
        // clear the file system stat cache
        clearstatcache(true);

        try {
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


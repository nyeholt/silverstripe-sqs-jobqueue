#!/usr/bin/php
<?php

// CLI specific bootstrapping
use SilverStripe\Control\CLIRequestBuilder;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Core\CoreKernel;

require dirname(__DIR__) . '/../silverstripe/framework/src/includes/autoload.php';

// Ensure that people can't access this from a web-server
if (!in_array(PHP_SAPI, ["cli"])) {
    echo "cli-script.php can't be run from a web request, you have to run it on the command-line.";
    die();
}


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
// if (isset($_SERVER['argv'][2])) {
//     $args = array_slice($_SERVER['argv'], 2);
//     if (!isset($_GET)) $_GET = array();
//     if (!isset($_REQUEST)) $_REQUEST = array();
//     foreach ($args as $arg) {
//         if (strpos($arg, '=') == false) {
//             $_GET['args'][] = $arg;
//         } else {
//             $newItems = array();
//             parse_str((substr($arg, 0, 2) == '--') ? substr($arg, 2) : $arg, $newItems);
//             $_GET = array_merge($_GET, $newItems);
//         }
//     }
//     $_REQUEST = array_merge($_REQUEST, $_GET);
// }




$loggingFunction = function ($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
};

$loggingFunction("Initialising SqsWorker");

/**
 * Closure to provide a small level of global scope protection
 */
$runningFunction = function ($logFunc) {
    $logFunc("Running");

    if (isset($_SERVER['argv'][1])) {
        // need to bump it down the line
        $_SERVER['argv'][2] = $_SERVER['argv'][1] == 'once' ? 'once=1' : $_SERVER['argv'][1];
    }
    // Fake the request URL - this is picked up in CLIRequestBuilder::cleanEnvironment
    $_SERVER['argv'][1] = 'sqs-runner';

    while (true) {
        // clear the file system stat cache
        clearstatcache(true);

        $request = CLIRequestBuilder::createFromEnvironment();

        $kernel = new CoreKernel(BASE_PATH);
        $app = new HTTPApplication($kernel);
        $response = $app->handle($request);
        if ($response->getBody() == 'restart') {
            return;
        }

        if ($request->getVar('once')) {
            break;
        }
        sleep(6);
    }
};


$loggingFunction("Started SqsWorker");
$runningFunction($loggingFunction);
$loggingFunction("SqsWorker shutdown");

<?php

namespace Symbiote\SqsJobQueue\Control;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Symbiote\SqsJobQueue\Service\FileBasedSqsQueue;
use Symbiote\SqsJobQueue\Service\SqsService;

class SqsRunnerController extends Controller
{
    public function doInit()
    {
        if (PHP_SAPI !== 'cli') {
            exit;
        }
        parent::doInit();
    }
    public function index()
    {
        $perpetual = is_null($this->getRequest()->getVar('once'));

        $logFunc = function ($message) {
            echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        };

        $service = Injector::inst()->get(SqsService::class);
        $max_memory = Config::inst()->get('SqsWorker', 'mem_limit');
        if (!$max_memory) {
            $max_memory = 128 * 1024 * 1024;
        }

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
                        return "restart";
                    }
                }
            }

            $fileLoc = $service->client instanceof FileBasedSqsQueue ? $service->client->queuePath : '';
            $logFunc("No jobs found in " . get_class($service->client) . ': ' . $fileLoc);

            if ($perpetual) {
                $service->checkScheduledTasks();
            }

            return "";

        } catch (Exception $ex) {
            echo "Queue read failed (" . get_class($ex) . "): " . $ex->getMessage() . "\n";
            echo $ex->getTraceAsString();
            echo "\n";

            if (strpos($ex->getMessage(), "Couldn't run query") !== false) {
                echo "Unrecoverable failure, closing for restart\n";
                return;
            }
        }
    }
}

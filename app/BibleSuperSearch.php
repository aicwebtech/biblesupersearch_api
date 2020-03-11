<?php

/**
 * class BibleSuperSearch
 *
 * Public interface for interacting with the Bible SuperSearch API locally, that is, on the same server
 * This class is intended to be included and instantiated by a third party application.
 * (To access the API funcitonality within the API, please use the Engine class instead.)
 *
 * NOTE: The Bible SuperSearch API is a Laravel application, and including it into another Laravel application is not reccommended at this time.
 **/

namespace aicwebtech\BibleSuperSearch;

class BibleSuperSearch {

    protected $app = NULL;
    protected $Engine = NULL;

    public function __construct() {
        $this->_makeApp();
        $this->Engine = \aicwebtech\BibleSuperSearch\Engine::getInstance();
    }

    /**
     * Performs an API query, such as a keyword search or passage retrieval
     * @param array $input - parameters for the query
     * @return array $results - query results
     **/
    public function actionQuery($input) {
        return $this->doAction('query', $input);
    }

    /**
     * Performs an API action
     * See Engine.php for a list of available actions 
     * @param string $action - name of the API action
     * @param array $input - parameters for the action
     * @return array $results - action results
     **/
    public function doAction($action, $input) {
        $method = 'action' . \Illuminate\Support\Str::studly($action);

        if(method_exists($this->Engine, $method)) {
            $this->Engine->resetErrors();
            return $this->Engine->$method($input);
        }

        throw new \Exception('Action does not exist:' . $action);
    }
    
    /**
     * Retrieves meta data, including errors, for the most recent API action
     * @param bool $include_errors - whether to include errors
     * @return stdclass $metadata - action metadata
     **/
    public function getActionMetadata($include_errors = FALSE) {
        return $this->Engine->getMetadata($include_errors);
    }

    private function _makeApp() {
        // Todo - figure out how to run along side another Laravel app - currently not possible?

        if(!defined('LARAVEL_START')) {
            require __DIR__ . '/../bootstrap/autoload.php';
            $this->app = require_once __DIR__ . '/../bootstrap/app.php';
        }
        else {
            $this->app = app();
        }
 
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); // From TestCase.php
    }
}

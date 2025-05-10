<?php
// For older, non-namespaced tests
namespace {
    
    // todo: use namespaces on all tests
    class TestCase extends Illuminate\Foundation\Testing\TestCase
    {
        /**
         * The base URL to use while testing the application.
         *
         * @var string
         */
        protected $baseUrl = 'http://localhost';
        protected $use_named_bindings = FALSE;
        protected $test_http = FALSE;
        
        /**
         * Creates the application.
         *
         * @return \Illuminate\Foundation\Application
         */
        public function createApplication()
        {
            ini_set('memory_limit','512M');
            set_exception_handler([new \Symfony\Component\ErrorHandler\ErrorHandler(), 'handleException']);
            $app = require __DIR__.'/../bootstrap/app.php';

            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
            $this->use_named_bindings = config('app.query_use_named_placeholders');
            $this->test_http = env('APP_TEST_HTTP', FALSE);
            return $app;
        }

        public function tearDown(): void
        {
            $this->beforeApplicationDestroyed(function () {
                DB::disconnect();
            });

            parent::tearDown();
        }

        public function setUp(): void
        {
            parent::setUp();

            if(!config('app.installed')) {
                // Skip all tests if not installed to database
                $this->markTestSkipped('APP NOT INSTALLED TO DATABASE, UNABLE TO TEST!!!');
            }
        }
    }
}

// For newer, namespaced tests
namespace Tests {
    class TestCase extends \TestCase {}
}


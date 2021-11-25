<?php
// For older, non-namespaced tests
namespace {
    
    // todo: use namespaces on all tests
    // todo2: refine this class in the Tests namespace
    class TestCase extends Illuminate\Foundation\Testing\TestCase
    {
        /**
         * The base URL to use while testing the application.
         *
         * @var string
         */
        protected $baseUrl = 'http://localhost';
        protected $use_named_bindings = FALSE;
        
        /**
         * Creates the application.
         *
         * @return \Illuminate\Foundation\Application
         */
        public function createApplication()
        {
            $app = require __DIR__.'/../bootstrap/app.php';

            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $this->use_named_bindings = config('app.query_use_named_placeholders');
            return $app;
        }

        public function tearDown(): void
        {
            $this->beforeApplicationDestroyed(function () {
                DB::disconnect();
            });

            parent::tearDown();
        }
    }
}

// For newer, namespaced tests
namespace Tests {
    class TestCase extends \TestCase {}
}


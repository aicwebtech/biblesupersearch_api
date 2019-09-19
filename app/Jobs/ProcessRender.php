<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\RenderManager;

class ProcessRender implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $form_data = NULL;
    public $tries = 5;
    public $timeout = 0; // Unlimited time

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($form_data)
    {
        $this->form_data = $form_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $Manager = new RenderManager($this->form_data['modules'], $this->form_data['format']);


        if(!$Manager->render(FALSE, TRUE, TRUE)) {

        }
        else {
            // Send email with link
        }
    }

    protected function _respawn() {
        // php artisan queue:work --once
    }
}

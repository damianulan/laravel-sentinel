<?php

namespace Sentinel\Console\Commands;

use Illuminate\Console\Command;

class AssignRca extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Roles and Permissions assignment';

    /**
     * Execute the console command.
     */
    public function handle() {}
}

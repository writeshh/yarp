<?php

namespace Writeshh\Yarp\Commands;

use Illuminate\Console\Command;
use Writeshh\Yarp\Service\RepositoryService;

class RepositoryPattern extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repo 
                            {name : Class (Singular), e.g User, Place, Car, Post} 
                            {--type=basic : Repository type (basic|extended)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Repository Pattern classes for Laravel models';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $type = $this->option('type');

        // Convert 'extended' to 'base' for the service function
        $repoType = ($type === 'extended') ? 'base' : 'basic';
        
        RepositoryService::ImplementNow($name, $repoType);

        $this->info("Repository class created for model " . $name . " using " . $type . " type.");
        $this->info("Remember to register App\\Providers\\RepositoryServiceProvider in your config/app.php file.");
        
        return self::SUCCESS;
    }
}

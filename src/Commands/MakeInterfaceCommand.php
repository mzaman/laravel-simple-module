<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use LaravelSimpleModule\AssistCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;

class MakeInterfaceCommand extends Command implements PromptsForMissingInput
{

    use AssistCommand, 
        SharedMethods;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:interface 
                            {name : The name of the Interface}
                            {--path= : Where the Interface should be created}
                            {--force : Create the interface even if the interface already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make an interface class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Interface';
    protected $stubPath = __DIR__ . '/stubs/interface.stub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the interface directory
        // This will be implement by the interface class
        $result = $this->createTrait(true);
        return $result;
        // $this->create();

    }
}
<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use LaravelSimpleModule\AssistCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;

class MakeAbstractClassCommand extends Command implements PromptsForMissingInput
{

    use AssistCommand,
        SharedMethods;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:abstract 
                            {name : The name of the abstract class}
                            {--path= : Where the abstract class should be created}
                            {--stub= : Use stub for abstract class creation}
                            {--parent= : The parent interface to extend}
                            {--interface= : The interface to implement}
                            {--force : Create the abstract class even if it already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make an abstract class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Abstract';
    protected $stubPath = __DIR__ . '/stubs/abstract.stub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set the stub path based on the provided option or use default if not provided
        $this->stubPath = $this->getStubPath();

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the interface directory
        // This will be implement by the interface class
        $result = $this->createTrait(true);
        return $result;
        // $this->create();

    }

}

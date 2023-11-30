<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use LaravelSimpleModule\AssistCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;

class MakeTraitCommand extends Command implements PromptsForMissingInput
{

    use AssistCommand, 
        SharedMethods;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:trait 
                            {name : The name of the Trait}
                            {--path= : Where the Trait should be created}
                            {--force : Create the trait even if the trait already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a trait class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Trait';
    protected $stubPath = __DIR__ . '/stubs/trait.stub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the trait directory
        // This will be implement by the interface class
        $result = $this->createTrait();
        return $result;
    }
}
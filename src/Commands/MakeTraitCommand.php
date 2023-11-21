<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use LaravelSimpleModule\AssistCommand;
use LaravelSimpleModule\CreateFile;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;
use File;

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
                            {--path : Where the Trait should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a Trait Class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Trait';
    // protected $defaultClass = 'DefaultTrait';
    // protected $defaultNamespace = 'App\\Traits';
    // protected $defaultPath = 'App/Traits';
    protected $stubPath = __DIR__ . '/stubs/trait.stub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $className = $this->getClass();

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the trait directory
        // This will be implement by the interface class
        $this->create(/*$className*/);

    }

    /**
     * Create trait
     *
     * @param string $className
     * @return void
     */
    public function create()
    {
        $namespace = $this->getNamespace();
        $class = $this->getClassName();
        $class = $this->removeLast($class, [$this->type]);
        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class
        ];

        $file = $this->getFile();
        $file = $this->removeLast($file, [$this->type]);
        new CreateFile(
            $stubProperties,
            $file,
            $this->stubPath
        );
        $this->line("<info>Created $class trait:</info> {$namespace}\\{$class}");

        return $namespace . "\\" . $class;
    }

    // /**
    //  * Get file path
    //  *
    //  * @return string
    //  */
    // private function getFile($className)
    // {
    //     return $this->getClassPath() . "/$className" . ".php";
    // }

}
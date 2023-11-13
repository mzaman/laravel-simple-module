<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use LaravelSimpleModule\AssistCommand;
use LaravelSimpleModule\CreateFile;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;
use File;

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
                            {--path : Where the Interface should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make an Interface Class';

    protected $defaultClass = 'DefaultInterface';
    protected $defaultNamespace = 'App\\Interface';
    protected $defaultPath = 'App/Interface';
    protected $stubPath = __DIR__ . '/stubs/interface.stub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $classBaseName = $this->getClassBaseName();

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the interface directory
        // This will be implement by the interface class
        $this->create($classBaseName);

    }

    /**
     * Create interface
     *
     * @param string $classBaseName
     * @return void
     */
    public function create(string $classBaseName)
    {
        $namespace = $this->recognizeNamespace($classBaseName);
        $class = $this->getClassName($classBaseName);
    
        $namespacedModel = $this->getModelNamespace();

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class
        ];

        $file = $this->getFile($classBaseName);
        new CreateFile(
            $stubProperties,
            $file,
            $this->stubPath
        );
        $this->line("<info>Created $classBaseName interface:</info> {$namespace}\\{$class}");

        return $namespace . "\\" . $class;
    }

    /**
     * Get file path
     *
     * @return string
     */
    private function getFile($classBaseName)
    {
        return $this->getPath() . "/$classBaseName" . ".php";
    }

}
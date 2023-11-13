<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use LaravelSimpleModule\AssistCommand;
use LaravelSimpleModule\CreateFile;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;
use File;

class CreateModelCommand extends Command implements PromptsForMissingInput
{

    use AssistCommand, 
        SharedMethods;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:model 
                            {name : The name of the Model}
                            {--path : Where the Model should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a Model Class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';
    protected $defaultClass = 'DefaultModel';
    protected $defaultNamespace;
    protected $defaultPath;
    protected $stubPath = __DIR__ . '/stubs/model.stub';

    public function __construct()
    {
        parent::__construct();

        $this->defaultNamespace = config('simple-module.model_namespace') ?? 'App\\Models';
        $this->defaultPath = config('simple-module.model_directory') ?? 'App/Models';
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $classBaseName = $this->getClassBaseName();

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the model directory
        // This will be implement by the interface class
        $this->create($classBaseName);

    }

    /**
     * Create model
     *
     * @param string $classBaseName
     * @return void
     */
    public function create(string $classBaseName)
    {
        $namespace = $this->recognizeNamespace($classBaseName);
        $class = $this->getClassName($classBaseName);

        // Create model traits
        $modelTraits = ['Attribute', 'Method', 'Relationship', 'Scope'];

        foreach ($modelTraits as $traitType) { 
            $traitClass = "{$class}{$traitType}";
            $this->call('make:trait', ['name' => $this->getModelNamespace() . "\\Traits\\$traitType\\$traitClass"]);
        }

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
        $this->line("<info>Created $classBaseName model:</info> {$namespace}\\{$class}");

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
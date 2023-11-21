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
                            {--path= : Where the Model should be created}';

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
    protected $stubPath = __DIR__ . '/stubs/model.stub';

    // public function __construct()
    // {
    //     parent::__construct();

    //     $this->defaultNamespace = config('simple-module.model_namespace') ?? 'App\\Models';
    //     $this->defaultPath = config('simple-module.model_directory') ?? 'App/Models';
    // }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // Second we create the model directory
        // This will be implement by the interface class
        $this->create();

    }

    /**
     * Create model
     *
     * @return void
     */
    public function create()
    {
        $model = $this->parseModelNamespaceAndClass($this->option("path"));
        $namespace = $model['namespace'];
        $class = $model['class'];

        $class = $this->removeLast($class, [$this->type]);
        // $classBaseName = $this->getClassBaseName();
        // Create model traits
        $modelTraits = ['Attribute', 'Method', 'Relationship', 'Scope'];

        foreach ($modelTraits as $traitType) { 
            $traitClass = "{$class}{$traitType}";
            $this->call('make:trait', ['name' => "$namespace\\Traits\\$traitType\\$traitClass"]);
        }

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class
        ];


        $file = $this->getFile();
        $class = $this->removeLast($class, [$this->type]);

        new CreateFile(
            $stubProperties,
            $file,
            $this->stubPath
        );
        $this->line("<info>Created $class model:</info> {$namespace}\\{$class}");

        return $namespace . "\\" . $class;
    }
}
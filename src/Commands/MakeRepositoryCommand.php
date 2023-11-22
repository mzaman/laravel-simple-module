<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use LaravelSimpleModule\AssistCommand;
use LaravelSimpleModule\CreateFile;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;
use File;

class MakeRepositoryCommand extends Command implements PromptsForMissingInput
{
    use AssistCommand, 
        SharedMethods;


    public $signature = 'make:repository
        {name : The name of the repository}
        {--other : If not put, it will create an eloquent repository}?
        {--service : Create a service along with the repository}?
        {--path= : Where the repository should be created}?
        {--model= : The model class for the repository}';

    public $description = 'Create a new repository class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';
    protected $interfaceStubPath = __DIR__ . '/stubs/repository-interface.stub';
    protected $stubPath = __DIR__ . '/stubs/eloquent-repository.stub';
    protected $customStubPath = __DIR__ . '/stubs/custom-repository.stub';

    /**
     * Handle the command
     *
     * @return void
     */
    public function handle()
    {
        $classBaseName = $this->getClassBaseName();
        $other = $this->option("other");

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // First we create the repoisitory interface in the interfaces directory
        // This will be implemented by the interface class
        $this->createInterface();


        // Second we create the repoisitory directory
        // This will be implement by the interface class
        $this->create(!$other);

        if ($this->option('service')) {
            $this->createService();
        }
    }

    /**
     * Create service for the repository
     *
     * @return void
     */
    private function createService()
    {
        $name = $this->getConvertedClass();
        $this->call("make:service", [
            "name" => $name,
        ]);
    } 

    /**
     * Create repository
     *
     * @param string $classBaseName
     * @return void
     */
    public function create($isDefault = true)
    {
        $namespace = $this->getNamespace();
        $class = $this->getClassName();
        $interface = $this->getInterfaceClassName();
        $model = $this->parseModelNamespaceAndClass($this->option("model"));

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class,
            "{{ interface }}" => $interface,
            "{{ namespacedModel }}"   => $model['namespace'],
            "{{ modelVariable }}"   => $model['class']
        ];

        // check command other
        $stubPath =  $isDefault ? $this->stubPath : $this->customStubPath;
        $file = $this->getFile($isDefault);
        new CreateFile(
            $stubProperties,
            $file,
            $stubPath
        );
        $this->line("<info>Created $class repository:</info> {$namespace}\\{$class}");

        return $namespace . "\\" . $class;
    }
}

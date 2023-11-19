<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaravelSimpleModule\AssistCommand;
use LaravelSimpleModule\CreateFile;
use Illuminate\Support\Pluralizer;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Commands\SharedMethods;
use File;

class MakeServiceCommand extends Command implements PromptsForMissingInput
{
    use AssistCommand, 
        SharedMethods;

    public $signature = 'make:service
        {name : The name of the service }
        {--repository : Create a repository along with the service}?
        {--api : Create a service with the api template}?
        {--path= : Where the service should be created}?';

    public $description = 'Create a new service class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service';
    protected $interfaceStubPath = __DIR__ . '/stubs/service-interface.stub';
    protected $stubPath = __DIR__ . '/stubs/service.stub';
    protected $apiStubPath = __DIR__ . '/stubs/service-api.stub';
    
    public function handle()
    { 
        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // First we create the service interface in the interfaces directory
        // This will be implemented by the interface class
        $this->createInterface();

        $this->create();

        if ($this->option('repository')) {
            $this->createRepository();
        }
    }

    /**
     * Create service
     *
     * @return void
     */
    public function create()
    {
        $namespace = $this->getNamespace();
        $class = $this->getClassName();
        $interface = $this->getInterfaceClassName();

        $repository = $this->getConvertedClass();
        $namespacedRepository = $this->parseNamespaceAndClass($repository);    

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class,
            "{{ interface }}" => $interface,
            "{{ namespacedRepository }}" => $namespacedRepository['namespace'],
            "{{ repositoryVariable }}" => $namespacedRepository['class'],
        ];

        // check folder exist
        $folder = str_replace('\\','/', $namespace);
        if (!file_exists($folder)) {
            File::makeDirectory($folder, 0775, true, true);
        }

        // check command api
        $stubPath =  $this->option("api") ? $this->apiStubPath : $this->stubPath;
        
        $file = $this->getFile();

        // create file
        new CreateFile(
            $stubProperties,
            $file,
            $stubPath
        );

        $this->line("<info>Created $class service:</info> {$namespace}\\{$class}");
    } 


    /**
     * Create repository for the service
     *
     * @return void
     */
    private function createRepository()
    {
        $name = $this->getConvertedClass();

        $this->call("make:repository", [
            "name" => $name,
        ]);
    }
}

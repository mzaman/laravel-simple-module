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
        {--path : Where the service should be created}?';

    public $description = 'Create a new service class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service';
    protected $defaultClass = 'DefaultService';
    protected $defaultNamespace;
    protected $defaultPath;
    protected $classSuffix;
    protected $interfaceSuffix;
    protected $interfaceStubPath = __DIR__ . '/stubs/service-interface.stub';
    protected $repositorySuffix;

    public function __construct()
    {
        parent::__construct();

        $this->defaultNamespace = config('simple-module.service_namespace') ?? 'App\\Services';
        $this->defaultPath = config('simple-module.service_directory') ?? 'App/Services';
        $this->classSuffix = config('simple-module.service_suffix', 'Service');
        $this->interfaceSuffix = config('simple-module.service_interface_suffix', 'ServiceInterface');
        $this->repositorySuffix = config('simple-module.repository_suffix', 'Repository');
    }
    
    public function handle()
    {

        $classBaseName = $this->getClassBaseName();

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // First we create the service interface in the interfaces directory
        // This will be implemented by the interface class
        $this->createInterface($classBaseName);

        $this->create($classBaseName);

        if ($this->option('repository')) {
            $this->createRepository();
        }
    }

    /**
     * Determine the repository namespace based on the service namespace.
     *
     * @return string The determined repository namespace.
     */
    function getRepositoryNamespace()
    {
        $namespace = $this->getNamespace();

        // Split the namespace by backslash ('\') into an array of segments
        $segments = explode('\\', $namespace);

        // Check if the first segment is $this->defaultNamespace
        if (reset($segments) === $this->defaultNamespace) {
            // If it is, return 'App\Repositories' as the repository namespace
            return 'App\Repositories';
        }

        // If not, remove the last segment to get the repository namespace
        array_pop($segments);

        // Reconstruct the repository namespace by joining the segments with backslash and adding '\Repositories'
        return implode('\\', $segments) . '\\Repositories';
    }

    /**
     * Create service
     *
     * @param string $classBaseName
     * @return void
     */
    public function create(string $classBaseName)
    {
        $namespace = $this->recognizeNamespace($classBaseName);
        $class = $this->getClassName($classBaseName);
        $class = $class . $this->classSuffix;
        $interface = $classBaseName . $this->interfaceSuffix;

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class,
            "{{ interface }}" => $interface,
            "{{ namespacedRepository }}" => $this->getRepositoryNamespace(),
            "{{ repositoryVariable }}" => $this->getRepositoryName($classBaseName),
        ];

        // check folder exist
        $folder = str_replace('\\','/', $namespace);
        if (!file_exists($folder)) {
            File::makeDirectory($folder, 0775, true, true);
        }

        // check command api
        if($this->option("api")) {
            $stubPath = __DIR__ . "/stubs/service-api.stub";
        } else {
            $stubPath = __DIR__ . "/stubs/service.stub";
        }
        
        $file = $this->getFile($classBaseName);

        // create file
        new CreateFile(
            $stubProperties,
            $file,
            $stubPath
        );

        $this->line("<info>Created $classBaseName service:</info> {$namespace}\\{$class}");
    } 

    /**
     * Get service path
     *
     * @return string
     */
    private function getFile($classBaseName)
    {
        return $this->getPath() . "/$classBaseName" . $this->classSuffix . ".php";
    }


    /**
     * get repository name
     * @param string $classBaseName
     * @return string
     */
    private function getRepositoryName(string $classBaseName) {
        return $classBaseName . $this->repositorySuffix;
    }
    
    /**
     * Create repository for the service
     *
     * @return void
     */
    private function createRepository()
    {
        $name = $this->getRepositoryNamespace() . '\\' . $this->getClassBaseName() . $this->repositorySuffix;

        $this->call("make:repository", [
            "name" => $name,
        ]);
    }
}

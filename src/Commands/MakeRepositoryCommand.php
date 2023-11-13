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
        {--path : Where the repository should be created}?';

    public $description = 'Create a new repository class';

    protected $defaultClass = 'DefaultRepository';
    protected $defaultNamespace = 'App\\Repositories';
    protected $defaultPath = 'App/Repositories';
    protected $classSuffix = 'Repository'; //config('simple-module.repository_suffix', 'Repository');
    protected $interfaceSuffix = 'RepositoryInterface'; //config('simple-module.repository_interface_suffix', 'RepositoryInterface');
    protected $interfaceStubPath = __DIR__ . '/stubs/repository-interface.stub';
    protected $serviceSuffix = 'Service'; //config('simple-module.service_suffix', 'Service');

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
        $this->createInterface($classBaseName);


        // Second we create the repoisitory directory
        // This will be implement by the interface class
        $this->create($classBaseName, ! $other);

        if ($this->option('service')) {
            $this->createService();
        }
    }


    /**
     * Determine the service namespace based on the service namespace.
     *
     * @return string The determined service namespace.
     */
    function getServiceNamespace()
    {
        $namespace = $this->getNamespace();

        // Split the namespace by backslash ('\') into an array of segments
        $segments = explode('\\', $namespace);

        // Check if the first segment is $this->defaultNamespace
        if (reset($segments) === $this->defaultNamespace) {
            // If it is, return 'App\Services' as the service namespace
            return 'App\Services';
        }

        // If not, remove the last segment to get the service namespace
        array_pop($segments);

        // Reconstruct the service namespace by joining the segments with backslash and adding '\Services'
        return implode('\\', $segments) . '\\Services';
    }


    /**
     * Create service for the repository
     *
     * @return void
     */
    private function createService()
    {

        $name = $this->getServiceNamespace() . '\\' . $this->getClassBaseName() . $this->serviceSuffix;

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
    public function create(string $classBaseName, $isDefault = true)
    {
        $namespace = $this->recognizeNamespace($classBaseName);
        $class = $this->getClassName($classBaseName);
        $class = $class . $this->classSuffix;
        $interface = $classBaseName . $this->interfaceSuffix;
        $namespacedModel = $this->getModelNamespace();

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class,
            "{{ interface }}" => $interface,
            "{{ namespacedModel }}"   => $namespacedModel,
            "{{ modelVariable }}"   => $classBaseName
        ];

        $stubName = $isDefault ? "eloquent-repository.stub" : "custom-repository.stub";
        $file = $this->getFile($classBaseName, $isDefault);
        new CreateFile(
            $stubProperties,
            $file,
            __DIR__ . "/stubs/$stubName"
        );
        $this->line("<info>Created $classBaseName repository:</info> {$namespace}\\{$class}");

        return $namespace . "\\" . $class;
    }


    /**
     * Get file path
     *
     * @return string
     */
    private function getFile($classBaseName, $isDefault)
    {
        $file = $isDefault
            ? "/$classBaseName" . $this->classSuffix . ".php"
            : "/Other/$classBaseName" .  $this->classSuffix . ".php";

        return $this->getPath() . $file;
    }

}

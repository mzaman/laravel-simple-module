<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use LaravelSimpleModule\AssistCommand;
use LaravelSimpleModule\CreateFile;
use LaravelSimpleModule\Constants\CommandType;
use Symfony\Component\Console\Input\InputOption;
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
                        {--model= : The model class for the repository}
                        {--force : Create the class even if the service already exists}';

    public $description = 'Create a new repository class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';
    protected $interfaceStubPath = __DIR__ . '/stubs/repository.interface.stub';
    protected $stubPath = __DIR__ . '/stubs/repository.nested.stub';
    protected $customStubPath = __DIR__ . '/stubs/repository.custom.stub';
    

    /**
     * Handle the command
     *
     * @return void
     */
    public function handle()
    {
        // Ensure that base classes exist
        $this->ensureBaseClassesExist();
        
        // Determine and assign the default parent option
        $this->addOption('parent', null, InputOption::VALUE_OPTIONAL, 'The parent interface to extend', $this->getDefaultQualifiedClass($this->type));


        // $classBaseName = $this->getClassBaseName();
        $other = $this->option("other");

        // Create the directory structure and generate relevant files
        $this->checkIfRequiredDirectoriesExist();

        // First we create the repository interface in the interfaces directory
        // This will be implemented by the interface class
        $this->createInterface();
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
     * @param bool $isDefault
     * @return void
     */
    public function create($isDefault = true)
    {
        $namespace = $this->getNamespace();
        $class = $this->getClassName();
        $namespacedClass = $namespace . "\\" . $class;
        $interface = $this->getInterfaceClassName();
        $model = $this->parseModelNamespaceAndClass($this->option("model"));
        $namespacedModel = $model['namespace'] . '\\' . $model['class'];

        if (! $this->entityExists($namespacedModel) && $this->confirm("A {$namespacedModel} model does not exist. Do you want to generate it?", true)) {
            $commandOptions = [
                'name' => $namespacedModel,
                '--path' => $namespacedModel,
                '--trait' => true
            ];

            $this->call('make:model', $commandOptions);
            // $command = $this->toCommandArgument([$this->getCommand('model'), $commandOptions], CommandType::SYMFONY);
            // $this->asyncRun([$command], CommandType::SYMFONY);

        }

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class,
            "{{ interface }}" => $interface,
            "{{ namespacedModel }}"   => $model['namespace'],
            "{{ modelVariable }}"   => $model['class']
        ];

        if ($this->hasOption('parent')) {
            $stubProperties["{{ parent }}"] = $this->fullyQualifyClass($this->option('parent'));
        }

        if($this->isAvailable($namespacedClass, $this->type)) {
            // check folder exist
            $folder = str_replace('\\','/', $namespace);
            if (!file_exists($folder)) {
                File::makeDirectory($folder, 0775, true, true);
            }

            // check command other
            $stubPath =  $isDefault ? $this->stubPath : $this->customStubPath;
            $file = $this->getFile($isDefault);
            new CreateFile(
                $stubProperties,
                $file,
                $stubPath
            );
            
            $this->printInfo($class, $this->type, $namespace);
            // $info = "<fg=yellow>{$this->type} <fg=green>{$class}</> [{$namespacedClass}]";

            // $path = $this->getPath($namespacedClass);
            // $this->components->info(sprintf('%s [%s] created successfully.', $info, $path));

            return $namespacedClass;
        } else {
            $this->handleAvailability($namespacedClass, $this->type);
        }
    }
}

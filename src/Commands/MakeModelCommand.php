<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use LaravelSimpleModule\Commands\SharedMethods;

class MakeModelCommand extends ModelMakeCommand
{
    use SharedMethods;
    /**
     * Execute the console command.
     *
     * @return void|bool
     */
    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return false;
        }
        // $class = $this->getQualifiedClass();
        // $this->handleAvailability($class);
        // dd($this->isAvailable($class));
        if ($this->option('all')) {
            // $this->input->setOption('module', true);
            $this->input->setOption('service', true);
            $this->input->setOption('repository', true);
        }

        $this->createModelTraits();
    }

    /**
     * Create model traits
     *
     * @return void
     */
    protected function createModelTraits()
    {
        $model = $this->parseModelNamespaceAndClass($this->option("path"));
        $namespace = $model['namespace'];
        $class = $model['class'];

        $class = $this->removeLast($class, [$this->type]);
        // $classBaseName = $this->getClassBaseName();
        // Create model traits
        $modelTraits = ['Attribute', 'Method', 'Relationship', 'Scope'];

        foreach ($modelTraits as $traitType) { 
            $traitClass = "{$namespace}\\Traits\\{$traitType}\\{$class}{$traitType}";
            $exists = $this->isAvailable($traitClass, 'Trait');
            if (!$this->isAvailable($traitClass, 'Trait')) {
                $this->components->error('Trait of Model '. $traitType . ' already exists.');
            } else {
                $this->call('make:trait', [
                    'name' => $traitClass,
                    '--force' => $this->isAvailable($class)
                ]);

            }
        }
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        $stub = null;
        if ($this->option('pivot')) {
            $stub = '/stubs/model.pivot.stub';
        }

        if ($this->option('morph-pivot')) {
            $stub = '/stubs/model.morph-pivot.stub';
        }

        $stub = $stub ?? '/stubs/model.stub';

        return __DIR__.$stub;
    }

    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function createController()
    {
        $controller = Str::studly(class_basename($this->argument('name')));

        $modelName = $this->qualifyClass($this->getNameInput());

        $options = [
            'name' => "{$controller}Controller",
            '--model' => $this->option('resource') ? $modelName : null,
            '--views' => true,
            '--requests' => true,
        ];

        // If the developer does not want to create explicitly a policy with the model,
        // the command for making a controller will deal with it.
        if (! $this->option('policy')) {
            $options['--policy'] = true;
        }

        $this->call('make:controller', $options);
    }


    /**
     * Create service for the model
     *
     * @return void
     */
    private function createService()
    {
        $name = Str::studly($this->argument('name'));

        $this->call("make:service", [
            "name" => $name,
        ]);
    }


    /**
     * Create a repository
     *
     * @return void
     */
    private function createRepository()
    {
        $name = Str::studly($this->argument('name'));

        $this->call("make:repository", [
            "name" => $name,
        ]);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a migration, factory and resource controller with request classes, views and policy for the model'],

            ['controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the model with request classes, views and a policy'],

            ['path', 'D', InputOption::VALUE_OPTIONAL, 'Where the controller should be created if specified'],

            ['repository', 'rt', InputOption::VALUE_NONE, 'Create a new repository file for the model'],

            ['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller with request classes, views and a policy'],

            ['service', 'sr', InputOption::VALUE_NONE, 'Create a new service file for the model'],
        ];

        $mergedOptions = array_merge(parent::getOptions(), $options);
        return $mergedOptions;
    }
}

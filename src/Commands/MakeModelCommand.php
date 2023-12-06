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
        $this->createModelTraits();

        if (!$this->isAvailable() || parent::handle() === false) {
            $this->handleAvailability();
        }

        // $class = $this->getQualifiedClass();
        // $this->handleAvailability($class);
        $this->qualifyOptionCreate('service');
        $this->qualifyOptionCreate('repository');

        if ($this->option('all')) {
            // $this->input->setOption('module', true);
            $this->input->setOption('service', true);
            $this->input->setOption('repository', true);
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

            ['repository', 'rt', InputOption::VALUE_OPTIONAL, 'Create a new repository file for the model', false],

            ['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller with request classes, views and a policy'],

            ['service', 'sr', InputOption::VALUE_OPTIONAL, 'Create a new service file for the model', false],
        ];

        return $this->mergeOptions(parent::getOptions(), $options);
        
    }
}

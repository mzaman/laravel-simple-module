<?php

/**
 * This script generates Laravel module scaffolding structure based on user input.
 */

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LaravelSimpleModule\AssistCommand;
use Illuminate\Support\Pluralizer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaravelSimpleModule\Commands\SharedMethods;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LaravelSimpleModule\Helpers\Change;

class MakeModuleCommand extends Command implements PromptsForMissingInput
{

    use AssistCommand, 
        SharedMethods;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module 
                            {name : The name of the module}
                            {--path : Where the module should be created}
                            {--force : Create the class even if the service already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Laravel module scaffolding structure';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Module';

    /**
     * The default class name.
     *
     * @var string
     */
    protected $defaultClass = 'DefaultModule';

    /**
     * Default namespace for the module.
     *
     * @var string
     */
    protected $defaultNamespace;

    /**
     * Default path for the module.
     *
     * @var string
     */
    protected $defaultPath;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->defaultNamespace = config('simple-module.module_namespace') ?? 'App\\Modules';
        $this->defaultPath = config('simple-module.module_directory') ?? 'App/Modules';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // Ask for module name
        $this->module = $this->getSingularClassName($this->argument('name'));

        $classBaseName = $this->getClassBaseName();

        // Choose the layers to generate (Api, Backend, Frontend)
        $this->layers = Change::case($this->handleChoices('Select scaffolding layers to generate', $this->applicationLayers, ['All']), 'lower');

        // If Models are selected, ask for model names (comma-separated)
        $this->models = $this->askNames('Model');

        // Choose the components to include (Events, Controllers, Middleware, Requests, Listeners, Models)
        $this->appComponents = Change::case($this->applicationComponents, 'lower');
        // $this->appComponents = Change::case($this->handleChoices('Select components to include', $this->applicationComponents), 'lower');

        $commands = [];
        foreach ($this->appComponents as $component) {
            $componentName = $component . 'Models';
            $$component = $this->handleChoices("Select models to create {$component} for", $this->models, ['All', 'None'], 0);
        }
        
        foreach ($this->models as $model) {
            $namespacedModel = $this->getModelNamespace() . '\\' . $model;
            // Add make model command with options
            $commandOptions = array_filter([
                'name' => $namespacedModel,
                '--migration' => in_array($model, $migration),
                '--factory' => in_array($model, $factory),
                '--seed' => in_array($model, $seeder),
                '--path' => $namespacedModel //TODO: Fix path option
            ]);

            $command = ['make:model', $commandOptions];
            array_push($commands, $command);

            // Add make event commands
            if (in_array($model, $event)) {
                $events = ['Created', 'Deleted', 'Updated'];
                foreach ($events as $eventType) {
                    $eventName = $model . $this->toPascal($eventType);
                    $commandOptions = [
                        'name' => $this->getNamespace() . "\\Events\\$model\\$eventName"
                    ];

                    $command = ['make:event', $commandOptions];
                    array_push($commands, $command);
                }
            }

            // Add make listener command
            if (in_array($model, $listener)) {
                    $listenerName = $model . 'EventListener';
                    $commandOptions = [
                        'name' => $this->getNamespace() . "\\Listeners\\$listenerName"
                    ];

                    $command = ['make:listener', $commandOptions];
                    array_push($commands, $command);
            }

            foreach ($this->layers as $layer) {
                $layerName = $this->toPascal($layer);
                // Add make controller command with options
                $commandOptions = array_filter([
                        'name' => $this->getNamespace() . "\\Http/Controllers/$layerName/{$model}{$layerName}Controller",
                        '--model' => $namespacedModel,
                        '--api' => $layer == 'api',
                        '--requests' => in_array($model, $request),
                        '--repository' => in_array($model, $repository),
                        '--service' => in_array($model, $service),
                        '--policy' => in_array($model, $policy),
                        '--views' => $layer !== 'api' && in_array($model, $view),
                    ]);

                $command = ['make:controller', $commandOptions];
                array_push($commands, $command);
            }

            // TODO: Add middleware command
        }


        // Create the directory structure and generate relevant files

        $this->checkIfRequiredDirectoriesExist();

        if ($this->confirm('Do you wish to continue...?', true)) {
            $this->info("Generating the $this->module module scaffolding structure..."); 
            $this->newLine(2);
        } else {
            $this->error('The scaffolding process is canceled!');
            return Command::FAILURE;
        }

        $this->bar = $this->output->createProgressBar(count($commands));

        $this->bar->start();
        print_r($commands);

        foreach ($commands as $key => $command) {
            Artisan::call($command[0], $command[1]);
            $this->bar->advance();
            sleep(1);
        }

        // $this->makeModels();
        // $this->makeSeeders($seederModels);
        // $this->makeFactories($factoryModels);
        // $this->makePolicies($policyModels);
        // $this->makeControllers($controllerModels);
        // $this->makeRepositories($serviceModels);
        // $this->makeServices($serviceModels);
        // $this->makeEvents();
        // $this->makeRequests();
        // $this->makeListeners();
        // $this->makeMigrations($migrationModels);

        $this->bar->finish();

        $this->newLine();
        $this->info('Scaffolding generated successfully!');

        return Command::SUCCESS;
    }

    /**
     * Initialize the command.
     *
     * @return void
     */
    protected function init()
    {
        $this->module = $this->getSingularClassName($this->argument('name'));
        $this->checkIfRequiredDirectoriesExist();
    }

    /**
     * Get the full path of the generated class file.
     *
     * @return string
     */
    protected function getClassPath()
    {
        $choices = $this->getChoices();
         
        return $choices['path'] . DIRECTORY_SEPARATOR . $choices['class'];
    }



    /**
     * Generate migrations for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeMigrations($models)
    {
        if (count($models)) {
            foreach ($models as $model) {
                $this->makeMigration($model);
            }
        }
    }

    /**
     * Generate migrations for selected model.
     *
     * @param array $models
     * @return void
     */
    private function makeMigration($model)
    {
        $table = Str::snake(Str::pluralStudly(class_basename($model)));
        Artisan::call('make:migration', [
            'name' => "create_{$table}_table",
            '--create' => $table,
            '--fullpath' => true,
        ]);
        $this->bar->advance();
 
    }


    /**
     * Generate seeder files for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeSeeders($models)
    {
        if (count($models)) {
            foreach ($models as $model) {
                $this->makeSeeder($model);
            }
        }
    }

    /**
     * Generate seeder files for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeSeeder($model)
    {
        if (count($models)) {
            foreach ($models as $model) {
                $seeder = Str::studly(class_basename($model));
                
                Artisan::call('make:seeder', [
                    'name' => "{$seeder}Seeder",
                ]);
                $this->bar->advance();
            }
        }
    }


    /**
     * Generate model factories for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeFactories($models)
    {
        if (count($models)) {
            foreach ($models as $model) {
                $factory = Str::studly(class_basename($model));
                
                Artisan::call('make:factory', [
                    'name' => "{$factory}Factory",
                    '--model' => $this->getModelNamespace() .  "\\$model",
                ]);
                $this->bar->advance();
            }
        }
    }

    /**
     * Generate model policies for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makePolicies($models)
    {
        if (count($models)) {
            foreach ($models as $model) {
                $policy = Str::studly(class_basename($model));
                
                Artisan::call('make:policy', [
                    'name' => $this->getNamespace() .  "\\Policies\\{$policy}Policy",
                    '--model' => $this->getModelNamespace() .  "\\$model",
                ]);
                $this->bar->advance();
            }
        }
    }


    /**
     * Generate controllers for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeControllers($models)
    {
        if (in_array('Controller', $this->appComponents) && count($models)) {

            // $this->info('Creating ' . implode(', ', $this->layers) . ' controllers for:' . implode(', ', $models) . ' models...');

            foreach ($this->layers as $layer) {
                $layer = $this->toPascal($layer);
                foreach ($models as $model) {
                    Artisan::call('make:controller', array_filter([
                        'name' => $this->getNamespace() . "\\Http/Controllers/$layer/{$model}Controller",
                        '--model' => $layer == 'Api' ? $this->getModelNamespace() . "\\$model" : null,
                        '--api' => $layer == 'Api',
                        '--requests' => in_array('Requests', $this->appComponents),
                    ]));
                    $this->bar->advance();
                }
            }
        }
    }

    /**
     * Generate repositories for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeRepositories($models)
    {
        if (in_array('Repository', $this->appComponents)) {

            // $this->info('Creating repositories file for:' . implode(', ', $models) . ' models...');
            foreach ($this->layers as $layer) {
                $layer = $this->toPascal($layer);
                foreach ($models as $model) {
                    $repository = $model . ($layer ? $layer : '') . 'Repository';

                    Artisan::call('make:repository', array_filter([
                            'name' => $this->getNamespace()  . "\\Repositories\\$layer\\$repository"
                        ]));
                    $this->bar->advance();
                }
            }
        }
    }

    /**
     * Generate services for selected models.
     *
     * @param array $models
     * @return void
     */
    private function makeServices($models)
    {
        if (in_array('Service', $this->appComponents)) {

            // $this->info('Creating service file for:' . implode(', ', $models) . ' models...');
            foreach ($this->layers as $layer) {
                $layer = $this->toPascal($layer);
                foreach ($models as $model) {
                    $service = $model . ($layer ? $layer : '') . 'Service';

                    Artisan::call('make:service', array_filter([
                            'name' => $this->getNamespace()  . "\\Services\\$layer\\$service",
                            '--api' => $layer == 'Api',
                        ]));
                    $this->bar->advance();
                }
            }
        }
    }

    /**
     * Generate models and model traits.
     *
     * @return void
     */
    private function makeModels()
    {
        if (in_array('Model', $this->appComponents)) {
            // $this->info('Creating ' . implode(', ', $this->models) . ' models...');

            foreach ($this->models as $model) {
                $this->makeModel($model);
            }
        }
    }

    /**
     * Generate model and model traits.
     *
     * @param array $model
     * @param array $options
     * @return void
     */
    private function makeModel($model, $options = [])
    {
            
        Artisan::call('make:model', array_filter([
            'name' => $this->getModelNamespace() . "\\$model", ...$options
        ]));
        $this->bar->advance();
  

    }

    /**
     * Generate events for selected models.
     *
     * @return void
     */
    private function makeEvents()
    {
        if (in_array('event', $this->appComponents)) {
            $events = ['Created', 'Deleted', 'Updated'];

            // $this->info('Creating events for:' . implode(', ', $this->models) . ' models...');
            foreach ($this->models as $model) {
                foreach ($events as $event) {
                    $eventName = $model . $this->toPascal($event);
                    Artisan::call('make:event', ['name' => $this->getNamespace() . "\\Events\\$model\\$eventName"]);
                    $this->bar->advance();
                }
            }
        }
    }
    
    /**
     * Generate event listeners for selected models.
     *
     * @return void
     */
    private function makeListeners()
    {
        if (in_array('listener', $this->appComponents)) {
            // $this->info('Creating event listeners for:' . implode(', ', $this->models) . ' models...');
            foreach ($this->models as $model) {
                $listenerName = $model . "EventListener";
                Artisan::call('make:listener', ['name' => $this->getNamespace() . "\\Listeners\\$listenerName"]);

                $this->bar->advance();
            }
        }
    }
    
    /**
     * Generate request classes for selected models and layers.
     *
     * @return void
     */
    private function makeRequests()
    {
        if (in_array('requests', $this->appComponents)) {
            $requests = ['Store', 'Edit', 'Delete', 'Update'];

            // $this->info('Creating ' . implode(', ', $this->layers) . ' requests for:' . implode(', ', $this->models) . ' models...');

            foreach ($this->layers as $layer) {
                $layer = $this->toPascal($layer);
                foreach ($this->models as $model) {
                    foreach ($requests as $request) {
                        $namespace = "\\Http\\Requests\\$layer";
                        $requestName = $this->toPascal($request) . $model . "Request";
                        $this->createRequest("{$namespace}\\{$requestName}");
                        // Artisan::call('make:request', ['name' => $this->getNamespace() . "\\Http\\Requests\\$layer\\$requestName"]);

                        $this->bar->advance();
                    }
                }
            }
        }
    }
}

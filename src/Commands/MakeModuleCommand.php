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
                            {--path : Where the module should be created}';

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
    protected $defaultClass = 'DefaultModule';
    protected $defaultNamespace;
    protected $defaultPath;

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
        $progress = 1;
        // Ask for module name
        $module = $this->getSingularClassName($this->argument('name'));
        $this->module = $module;

        $classBaseName = $this->getClassBaseName();
        // Create the directory structure and generate relevant files

        $this->checkIfRequiredDirectoriesExist();


        // Choose the features to generate (Api, Backend, Frontend)
        $featureChoices = ['Api', 'Backend', 'Frontend'];
        $this->features = $this->handleChoices('Select scaffolding parts to generate', $featureChoices);

        // Choose the parts to include (Events, Controllers, Middleware, Requests, Listeners, Models)
        $partChoices = ['Events', 'Controllers', 'Middleware', 'Requests', 'Listeners', 'Models', 'Repositories', 'Services', 'Policies', 'Factories', 'Seeder'];
        $this->parts = $this->handleChoices('Select parts to include', $partChoices);

        $models = [];

        // If Models are selected, ask for model names (comma-separated)
        if (in_array('Models', $this->parts)) {
            $modelNames = $this->ask('Enter model names (comma-separated)');
            $modelNames = array_map(function($modelName) {
                return $this->toPascal($modelName);
            }, explode(',', $modelNames));
            $models = $modelNames;
        }

        $this->models = $models;

        // Select models to create migrations for
        $migrationModels = $this->handleChoices('Select models to create migrations for', $models);

        // Select models to create seeders for
        $seederModels = $this->handleChoices('Select models to create seeders for', $models);

        // Select models to create factories for
        $factoryModels = $this->handleChoices('Select models to create factories for', $models);

        // Select models to create controllers for
        $controllerModels = $this->handleChoices('Select models to create controllers for', $models);

        // Select models to create policies for
        $policyModels = $this->handleChoices('Select models to create policies for', $models);

        // Select models to create repository for
        $repositoryModels = $this->handleChoices('Select models to create repository for', $models); 

        // Select models to create service for
        $serviceModels = $this->handleChoices('Select models to create service for', $models); 

        $progress = $progress + count($models) + (count($partChoices) * count($featureChoices)) + (count($controllerModels) * count($featureChoices)) + count($repositoryModels) + count($serviceModels) + count($migrationModels) + count($seederModels) + count($factoryModels) + count($policyModels);

        if ($this->confirm('Do you wish to continue...?', true)) {
            $progress += 1;
            $this->info("Generating the $module module scaffolding structure..."); 
            $this->newLine(2);
        } else {
            $this->error('The scaffolding process is canceled!');
            return false;
        }

        $this->bar = $this->output->createProgressBar($progress);

        $this->bar->start();

        $this->makeModels();
        $this->makeSeeders($seederModels);
        $this->makeFactories($factoryModels);
        $this->makePolicies($policyModels);
        $this->makeControllers($controllerModels);
        $this->makeRepositories($serviceModels);
        $this->makeServices($serviceModels);
        $this->makeEvents();
        $this->makeRequests();
        $this->makeListeners();
        $this->makeMigrations($migrationModels);

        $this->bar->finish();

        $this->newLine();
        $this->info('Scaffolding generated successfully!');

        return Command::SUCCESS;
    }


    /**
     * Get the full path of the generated class file.
     *
     * @return string
     */
    public function getClassPath()
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
                $table = Str::snake(Str::pluralStudly(class_basename($model)));
                Artisan::call('make:migration', [
                    'name' => "create_{$table}_table",
                    '--create' => $table,
                    '--fullpath' => true,
                ]);
                $this->bar->advance();
            }
        }
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
                $seeder = Str::studly(class_basename(class_basename($model)));
                
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
                $factory = Str::studly(class_basename(class_basename($model)));
                
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
                $policy = Str::studly(class_basename(class_basename($model)));
                
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
        if (in_array('Controllers', $this->parts) && count($models)) {

            // $this->info('Creating ' . implode(', ', $this->features) . ' controllers for:' . implode(', ', $models) . ' models...');

            foreach ($this->features as $feature) {
                $feature = $this->toPascal($feature);
                foreach ($models as $model) {
                    Artisan::call('make:controller', array_filter([
                        'name' => $this->getNamespace() . "\\Http/Controllers/$feature/{$model}Controller",
                        '--model' => $feature == 'Api' ? $this->getModelNamespace() . "\\$model" : null,
                        '--api' => $feature == 'Api',
                        '--requests' => in_array('Requests', $this->parts),
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
        if (in_array('Repositories', $this->parts)) {

            // $this->info('Creating repositories file for:' . implode(', ', $models) . ' models...');
            foreach ($this->features as $feature) {
                $feature = $this->toPascal($feature);
                foreach ($models as $model) {
                    $repository = $model . ($feature ? $feature : '') . 'Repository';

                    Artisan::call('make:repository', array_filter([
                            'name' => $this->getNamespace()  . "\\Repositories\\$feature\\$repository"
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
        if (in_array('Services', $this->parts)) {

            // $this->info('Creating service file for:' . implode(', ', $models) . ' models...');
            foreach ($this->features as $feature) {
                $feature = $this->toPascal($feature);
                foreach ($models as $model) {
                    $service = $model . ($feature ? $feature : '') . 'Service';

                    Artisan::call('make:service', array_filter([
                            'name' => $this->getNamespace()  . "\\Services\\$feature\\$service",
                            '--api' => $feature == 'Api',
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
        if (in_array('Models', $this->parts)) {
            // $this->info('Creating ' . implode(', ', $this->models) . ' models...');

            foreach ($this->models as $model) {
                Artisan::call('make:model', ['name' => $this->getModelNamespace() . "\\$model"]); 
                $this->bar->advance();
            }
        }
    }

    /**
     * Generate events for selected models.
     *
     * @return void
     */
    private function makeEvents()
    {
        if (in_array('Events', $this->parts)) {
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
        if (in_array('Listeners', $this->parts)) {
            // $this->info('Creating event listeners for:' . implode(', ', $this->models) . ' models...');
            foreach ($this->models as $model) {
                $listenerName = $model . "EventListener";
                Artisan::call('make:listener', ['name' => $this->getNamespace() . "\\Listeners\\$listenerName"]);

                $this->bar->advance();
            }
        }
    }
    
    /**
     * Generate request classes for selected models and features.
     *
     * @return void
     */
    private function makeRequests()
    {
        if (in_array('Requests', $this->parts)) {
            $requests = ['Store', 'Edit', 'Delete', 'Update'];

            // $this->info('Creating ' . implode(', ', $this->features) . ' requests for:' . implode(', ', $this->models) . ' models...');

            foreach ($this->features as $feature) {
                $feature = $this->toPascal($feature);
                foreach ($this->models as $model) {
                    foreach ($requests as $request) {
                        $namespace = "\\Http\\Requests\\$feature";
                        $requestName = $this->toPascal($request) . $model . "Request";
                        $this->createRequest("{$namespace}\\{$requestName}");
                        // Artisan::call('make:request', ['name' => $this->getNamespace() . "\\Http\\Requests\\$feature\\$requestName"]);

                        $this->bar->advance();
                    }
                }
            }
        }
    }
}

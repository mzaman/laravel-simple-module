<?php

/**
 * This script generates Laravel module scaffolding structure based on user input.
 */

namespace LaravelSimpleModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Pluralizer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class MakeModuleCommand extends Command implements PromptsForMissingInput
{
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
     * Filesystem instance
     * @var Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
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
        if ($this->confirm('Do you wish to continue?', true)) {
            $progress += 1;
            $this->info("Generating the $module module scaffolding structure..."); 
            $this->newLine(3);
        } else {
            $this->error('The scaffolding process is canceled!');
            return false;
        }


        // Create the directory structure and generate relevant files
        $modulePath = $this->getModulePath();
        $this->modulePath = $modulePath;

        $moduleNamespace = str_replace('/', '\\', $this->getSingularClassName($modulePath));
        $this->moduleNamespace = $moduleNamespace;


        // Choose the features to generate (Api, Backend, Frontend)
        $featureChoices = ['Api', 'Backend', 'Frontend'];
        $this->features = $this->handleChoices('Select scaffolding parts to generate', $featureChoices);

        // Choose the parts to include (Events, Controllers, Middleware, Requests, Listeners, Models)
        $partChoices = ['Events', 'Controllers', 'Middleware', 'Requests', 'Listeners', 'Models', 'Repositories', 'Services'];
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

        // Select models to create controllers for
        $controllerModels = $this->handleChoices('Select models to create controllers for', $models);

        // Select models to create repository for
        $repositoryModels = $this->handleChoices('Select models to create repository for', $models); 

        // Select models to create service for
        $serviceModels = $this->handleChoices('Select models to create service for', $models); 

        $progress = $progress + count($models) + (count($partChoices) * count($featureChoices)) + (count($controllerModels) * count($featureChoices)) + count($repositoryModels) + count($serviceModels) + count($migrationModels);

        $this->bar = $this->output->createProgressBar($progress);

        $this->bar->start();

        $this->newLine();
        $this->makeModels();
        $this->newLine();
        $this->makeControllers($controllerModels);
        $this->newLine();
        $this->makeRepositories($serviceModels);
        $this->newLine();
        $this->makeServices($serviceModels);
        $this->newLine();
        $this->makeEvents();
        $this->newLine();
        $this->makeRequests();
        $this->newLine();
        $this->makeListeners();
        $this->newLine();
        $this->makeMigrations($migrationModels);
        $this->newLine();

        $this->bar->finish();

        $this->info('Scaffolding generated successfully!');

        return Command::SUCCESS;
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => 'What should the module be named (e.g., DefaultModule) ?',
        ];
    }

    /**
     * Simplified method for handling choices and default values.
     *
     * @param string $question
     * @param array $choices
     * @param int $defaultIndex
     * @param bool $allowMultipleSelections
     * @return array
     */
    private function handleChoices($question, $choices, $defaultIndex = 0, $allowMultipleSelections = true)
    {
        $commonChoices = ['All', 'None'];
        $selectedChoices = $this->choice($question,  [...$commonChoices, ...$choices], $defaultIndex, $maxAttempts = null, $allowMultipleSelections);
        
        if (in_array('All', $selectedChoices)) {
            return $choices;
        } elseif (in_array('None', $selectedChoices)) {
            return [];
        } else {
            return $this->removeByValues($selectedChoices, $commonChoices);;
        }
    }

    /**
    **
    * Map the stub variables present in stub to its value
    *
    * @return array
    *
    */
    public function getChoices()
    {
        $name = $this->argument('name');
        $path = $this->option('path');

        switch (true) {
            // Case 1: name argument contains namespace, path not provided
            case preg_match('/(.*)\\\\([a-zA-Z]+)$/', $name, $matches) && empty($path):
                $namespace = $matches[1];
                $className = $matches[2];
                $fullPath = str_replace('\\', '/', $namespace);
                break;

            // Case 2: name argument contains class name, path not provided
            case !preg_match('/\\\\/', $name) && empty($path):
                $namespace = 'App\\Modules';
                $className = $name;
                $fullPath = 'App/Modules';
                break;

            // Case 3: name argument contains namespace, path provided
            case preg_match('/(.*)\\\\([a-zA-Z]+)$/', $name, $matches) && !empty($path):
                $namespace = $matches[1];
                $className = $matches[2];
                $fullPath = $path;
                break;

            // Case 4: name argument contains class name, path provided
            case !preg_match('/\\\\/', $name) && !empty($path):
                $namespace = $path;
                $className = $name;
                $fullPath = $path;
                break;

            default:
                // Handle any other cases or provide default values if needed
                $namespace = 'App\\Modules';
                $className = 'DefaultModule';
                $fullPath = 'App/Modules';
                break;
        }

        return [
            'namespace' => $this->getSingularClassName($namespace),
            'class' => $this->getSingularClassName($className),
            'path' => $fullPath,
        ];
    }

    /**
     * Get the full path of the generated class file.
     *
     * @return string
     */
    public function getModulePath()
    {
        $choices = $this->getChoices();
         
        return $choices['path'] . DIRECTORY_SEPARATOR . $choices['class'];
    }

    /**
     * Return the Singular Capitalize Name
     * @param $name
     * @return string
     */
    public function getSingularClassName($name)
    {
        return ucwords(Pluralizer::singular($name));
    }

    /**
     * Remove items from an array by their values if they exist in another array.
     *
     * @param array $array The input array.
     * @param array $values The array of values to be removed from the input array.
     * @return array The modified array with the specified values removed.
     */
    private function removeByValues($array, $values) {
        if (!is_array($array) || !is_array($values)) {
            return $array; // Return the original value if it's not an array.
        }

        return array_values(array_diff($array, $values));
    }


    /**
     * Convert a string to PascalCase.
     *
     * @param string $str
     * @return string
     */
    private function toPascal($str)
    {
       return Str::studly(Str::lower($str));
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
            $this->newLine();
            foreach ($models as $model) {
                $table = Str::snake(Str::pluralStudly(class_basename($model)));
                $this->call('make:migration', [
                    'name' => "create_{$table}_table",
                    '--create' => $table,
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

            $this->info('Creating ' . implode(', ', $this->features) . ' controllers for:' . implode(', ', $models) . ' models...');

            foreach ($this->features as $feature) {
                $feature = $this->toPascal($feature);
                foreach ($models as $model) {
                    $controllerName = $model . 'Controller';
                    Artisan::call('make:controller', ['name' => $this->moduleNamespace . "\\Http/Controllers/$feature/$model/$controllerName"]);
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

            $this->info('Creating repositories file for:' . implode(', ', $models) . ' models...');
            // foreach ($this->features as $feature) {}
                // $feature = $this->toPascal($feature);
            foreach ($models as $model) {
                    $repositoryName = $model . 'Repository';
                    Artisan::call('make:repository', ['name' => $this->moduleNamespace  . "\\Repositories\\$repositoryName"]);
                    // Artisan::call('make:service', ['name' => $this->moduleNamespace . "\\$feature/$repositoryName"]);
                    $this->bar->advance();
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

            $this->info('Creating service file for:' . implode(', ', $models) . ' models...');
            // foreach ($this->features as $feature) {}
                // $feature = $this->toPascal($feature);
            foreach ($models as $model) {
                    $serviceName = $model . 'Service';
                    Artisan::call('make:service', ['name' => $this->moduleNamespace  . "\\Services\\$serviceName"]);
                    // Artisan::call('make:service', ['name' => $this->moduleNamespace . "\\$feature/$serviceName"]);
                    $this->bar->advance();
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
            $modelTraits = ['Attribute', 'Method', 'Relationship', 'Scope'];
            $this->info('Creating ' . implode(', ', $this->models) . ' models...');

            foreach ($this->models as $model) {
                Artisan::call('create:model', ['name' => $this->moduleNamespace . "\\Models\\$model"]);
                foreach ($modelTraits as $traitType) {
                    // File::ensureDirectoryExists($this->modulePath . "/Models/$model/Traits/$traitType");
                    $traitClass = "${model}${traitType}";
                    Artisan::call('make:trait', ['name' => $this->moduleNamespace . "\\Models\\Traits\\$traitType\\$traitClass"]);

                    $this->bar->advance();
                }
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

            $this->info('Creating events for:' . implode(', ', $this->models) . ' models...');
            foreach ($this->models as $model) {
                foreach ($events as $event) {
                    $eventName = $model . $this->toPascal($event);
                    Artisan::call('make:event', ['name' => $this->moduleNamespace . "\\Events\\$model\\$eventName"]);
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
            $this->info('Creating event listeners for:' . implode(', ', $this->models) . ' models...');
            foreach ($this->models as $model) {
                $listenerName = $model . "EventListener";
                Artisan::call('make:listener', ['name' => $this->moduleNamespace . "\\Listeners\\$listenerName"]);

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

            $this->info('Creating ' . implode(', ', $this->features) . ' requests for:' . implode(', ', $this->models) . ' models...');

            foreach ($this->features as $feature) {
                $feature = $this->toPascal($feature);
                foreach ($this->models as $model) {
                    foreach ($requests as $request) {
                        $requestName = $this->toPascal($request) . $model . "Request";
                        Artisan::call('make:request', ['name' => $this->moduleNamespace . "\\Http\\Requests\\$feature\\$requestName"]);

                        $this->bar->advance();
                    }
                }
            }
        }
    }


    /**
     * Import attribute namespaces into the generated Model class.
     *
     * @param string $modulePath
     * @param string $modelClass
     * @return void
     */
    // private function importAttributeNamespaces($modulePath, $modelClass)
    // {
    //     $module = $this->module;
    //     $version = $this->version;
    //     $modelPath = app_path("Domains/$version/$module/Models/$modelClass.php");
    //     $attributeNamespace = "App\\Domains\\$version\\$module\\Models\\Traits\\Attribute\\";
    //     $content = File::get($modelPath);
    //     $content = str_replace('use Illuminate\\Database\\Eloquent\\Model;', "use Illuminate\\Database\\Eloquent\\Model;\nuse $attributeNamespace{$modelClass}Attribute;", $content);
    //     File::put($modelPath, $content);
    // }
}

<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use LaravelSimpleModule\Commands\SharedMethods;

class MakeControllerCommand extends ControllerMakeCommand
{
    use SharedMethods;
    // protected $isFresh = true;
    /**
     * Execute the console command.
     *
     * @return void|bool
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {

        if (!$this->isAvailable() || parent::handle() === false) {
            $this->handleAvailability();
        }

        if ($this->option('requests')) {
            $this->createRequests();
        }

        $this->qualifyOptionCreate('policy');

        // if ($this->option('policy')) {
        //     $this->createPolicy();
        // }

        if ($this->option('views') && ! $this->option('api')) {
            $this->createViews();
        }
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->getNamespace();
    }

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in base namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {

        $controllerNamespace = $this->getNamespace(/*$name*/);

        $replace = [];

        if ($this->option('parent')) {
            $replace = $this->buildParentReplacements();
        }

        if ($this->qualifyOption('model')) {
            $replace = $this->buildModelReplacements($replace);
        }

        $replace = $this->buildRequestsReplacements($replace);

        if (! $this->option('invokable')) {
            $hasResource = $this->option('parent') || $this->qualifyOption('model') || $this->option('resource');

            if ($this->qualifyOption('service')) {
                $replace = $this->buildServiceReplacements($replace);
            }


            if ($hasResource && $this->option('views') && ! $this->option('api')) {
                $replace = $this->buildViewsReplacements($replace);
            }
        }

        $replace["use {$controllerNamespace};\n"] = '';
        // $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
            array_keys($replace),
            array_values($replace),
            call_user_func([$this->getGrandparentClass(), 'buildClass'], $name)
        );
    }

    /**
     * Build the model replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildModelReplacements(array $replace)
    {
        $modelClass = $this->parseModel($this->qualifyOption('model'));

        // $modelClass = class_basename($this->qualifyOption('model') ? $this->parseModel($this->qualifyOption('model')) : $this->getModelClass());

        if (! class_exists($modelClass) && $this->components->confirm("A {$modelClass} model does not exist. Do you want to generate it?", true)) {
            $this->call('make:model', [
                'name' => $modelClass,
                '--path' => $modelClass
            ]);
        }

        $replace = $this->buildFormRequestReplacements($replace, $modelClass);

        return array_merge($replace, [
            'DummyFullModelClass' => $modelClass,
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            'DummyModelClass' => class_basename($modelClass),
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            'DummyModelVariable' => lcfirst(class_basename($modelClass)),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}' => lcfirst(class_basename($modelClass)),
        ]);
    }

    /**
     * Build the service replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildServiceReplacements(array $replace)
    {
        $serviceClass = $this->parseModel($this->qualifyOption('service'));

        $serviceClass = $this->generateService($serviceClass);

        return array_merge($replace, [
            'DummyFullServiceClass' => $serviceClass,
            '{{ namespacedService }}' => $serviceClass,
            '{{namespacedService}}' => $serviceClass,
            'DummyServiceClass' => class_basename($serviceClass),
            '{{ service }}' => class_basename($serviceClass),
            '{{service}}' => class_basename($serviceClass),
            'DummyServiceVariable' => lcfirst(class_basename($serviceClass)),
            '{{ serviceVariable }}' => lcfirst(class_basename($serviceClass)),
            '{{serviceVariable}}' => lcfirst(class_basename($serviceClass)),
        ]);
    }

    /**
     * Generate the service class for the given model.
     *
     * @param  string  $modelClass
     * @return string
     */
    protected function generateService($modelClass)
    {
        $serviceNamespace = $this->getQualifiedNamespace('Services');
        $serviceClass = class_basename($modelClass);
        $namespacedService = "{$serviceNamespace}\\{$serviceClass}";
        $this->createService($namespacedService);

        return $namespacedService;
    }

    /**
     * Create a service class file.
     *
     * @param  string  $serviceClass
     * @return void
     */
    protected function createService($serviceClass)
    {
        $this->call('make:service', [
            'name' => $serviceClass,
            "--model" => $this->option("model")
        ]);
    }

    /**
     * Build the model replacement values.
     *
     * @param  array  $replace
     * @param  string  $modelClass
     * @return array
     */
    protected function buildFormRequestReplacements(array $replace, $modelClass)
    {
        [$namespace, $storeRequestClass, $updateRequestClass] = [
            'Illuminate\\Http', 'Request', 'Request',
        ];

        if ($this->option('requests')) {
            $namespace = $this->getQualifiedNamespace('Request');
            // $namespace = 'App\\Http\\Requests';

            [$storeRequestClass, $updateRequestClass] = $this->generateFormRequests(
                $modelClass, $storeRequestClass, $updateRequestClass
            );
        }

        $namespacedRequests = $namespace.'\\'.$storeRequestClass.';';

        if ($storeRequestClass !== $updateRequestClass) {
            $namespacedRequests .= PHP_EOL.'use '.$namespace.'\\'.$updateRequestClass.';';
        }

        return array_merge($replace, [
            '{{ storeRequest }}' => $storeRequestClass,
            '{{storeRequest}}' => $storeRequestClass,
            '{{ updateRequest }}' => $updateRequestClass,
            '{{updateRequest}}' => $updateRequestClass,
            '{{ namespacedStoreRequest }}' => $namespace.'\\'.$storeRequestClass,
            '{{namespacedStoreRequest}}' => $namespace.'\\'.$storeRequestClass,
            '{{ namespacedUpdateRequest }}' => $namespace.'\\'.$updateRequestClass,
            '{{namespacedUpdateRequest}}' => $namespace.'\\'.$updateRequestClass,
            '{{ namespacedRequests }}' => $namespacedRequests,
            '{{namespacedRequests}}' => $namespacedRequests,
        ]);
    }

    /**
     * Generate the form requests for the given model and classes.
     *
     * @param  string  $modelClass
     * @param  string  $storeRequestClass
     * @param  string  $updateRequestClass
     * @return array
     */
    protected function generateFormRequests($modelClass, $storeRequestClass, $updateRequestClass)
    {

        $namespace = $this->getQualifiedNamespace('Request');

        // $namespace = 'App\Http\Requests';
        $storeRequestClass = 'Store'.class_basename($modelClass).'Request';

        $this->createRequest("{$namespace}\\{$storeRequestClass}");
        // $this->call('make:request', [
        //     'name' => "{$namespace}\\{$storeRequestClass}",
        // ]);

        $updateRequestClass = 'Update'.class_basename($modelClass).'Request';

        $this->createRequest("{$namespace}\\{$updateRequestClass}");
        // $this->call('make:request', [
        //     'name' => "{$namespace}\\{$updateRequestClass}",
        // ]);
        return [$storeRequestClass, $updateRequestClass];
    }

    /**
     * Build the requests replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildRequestsReplacements(array $replace)
    {
        $modelClass = class_basename($this->qualifyOption('model') ? $this->parseModel($this->qualifyOption('model')) : $this->getModelClass());
        // $controller = Str::studly($this->getBaseClassName());

        $requestNamespace = $this->getQualifiedNamespace('Request');
        // $requestPath = str_replace('/', '\\', $controller);

        if ($this->option('requests')) {
            $replace['use Illuminate\\Http\\Request;'] = '';

            $replace['DummyStoreRequestClass'] = "Store{$modelClass}Request";
            $replace['DummyFullStoreRequestClass;'] = "{$requestNamespace}\\Store{$modelClass}Request;";
            $replace['DummyFullStoreRequestMethodClass'] = "Store{$modelClass}Request";

            $replace['DummyUpdateRequestClass'] = "Update{$modelClass}Request";
            $replace['DummyFullUpdateRequestClass;'] = "{$requestNamespace}\\Update{$modelClass}Request;";
            $replace['DummyFullUpdateRequestMethodClass'] = "Update{$modelClass}Request";
        } else {
            $replace['DummyStoreRequestClass'] = 'Request';
            $replace['DummyFullStoreRequestClass;'] = 'Illuminate\Http\Request;';
            $replace['DummyFullStoreRequestMethodClass'] = 'Request';

            $replace['DummyUpdateRequestClass'] = 'Request';
            $replace["use DummyFullUpdateRequestClass;\n"] = '';
            $replace['DummyFullUpdateRequestMethodClass'] = 'Request';
        }

        return $replace;
    }

    /**
     * Build the views replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildViewsReplacements(array $replace)
    {
        $controller = strtolower(Str::studly($this->getBaseClassName()));
        $viewPath = str_replace('/', '.', $controller);

        return array_merge($replace, [
            'DummyViewPath' => $viewPath,
        ]);
    }

    /**
     * Create a policy for the model.
     *
     * @return void
     */
    protected function createPolicy()
    {

        $model = $this->getQualifiedClass($this->getModelName()/*, 'Model'*/);
        $policy = $this->option('policy');
        $namespace = $this->getQualifiedNamespace('Policy');
        if ($policy != '' && class_exists("{$namespace}\\{$policy}")) {
            return;
        }

        $policyName = Str::studly(class_basename($model)) . 'Policy';
        $this->call('make:policy', [
            'name' => "{$namespace}\\{$policyName}",
            '--model' => $model,
        ]);
    }

    /**
     * Create request files for the model.
     *
     * @return void
     */
    protected function createRequests()
    {
        $requests = ['Store', 'Edit', 'Delete', 'Update'];
        $namespace = $this->getQualifiedNamespace('Request');
        
        $model = class_basename($this->qualifyOption('model') ? $this->parseModel($this->qualifyOption('model')) : $this->getModelClass());

        // $model = Str::studly($this->getModelClass()); 

        foreach ($requests as $request) {
            $requestName =  $request . $model . "Request";
            $this->createRequest("{$namespace}\\{$requestName}");
        }
    }

    
    /**
     * Create the views for the model.
     *
     * @return void
     */
    protected function createViews()
    {
        $views = ['index', 'create', 'show', 'edit'];
        $controller = strtolower(Str::studly($this->getBaseClassName()));

        foreach ($views as $view) {
            $this->call('make:view', [
                'name' => "{$controller}/{$view}",
            ]);
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

        if ($this->option('parent')) {
            $stub = '/stubs/controller.nested.stub';
        } elseif ($this->qualifyOption('model')) {
            $stub = '/stubs/controller.model.stub';
        } elseif ($this->option('invokable')) {
            $stub = '/stubs/controller.invokable.stub';
        } elseif ($this->option('resource')) {
            $stub = '/stubs/controller.stub';
        }

        if ($this->option('api') && is_null($stub)) {
            $stub = '/stubs/controller.api.stub';
        } elseif ($this->option('api') && ! is_null($stub) && ! $this->option('invokable')) {
            $stub = str_replace('.stub', '.api.stub', $stub);
        }

        if (! is_null($stub) && ! $this->option('invokable')) {
            $hasResource = $this->option('parent') || $this->qualifyOption('model') || $this->option('resource');

            if (/*$this->qualifyOption('model') && */$this->qualifyOption('service')) {
                $stub = str_replace('.stub', '.service.stub', $stub);
            }

            if ($this->qualifyOption('model') && $this->qualifyOption('policy')) {
                $stub = str_replace('.stub', '.policy.stub', $stub);
            }

            if ($hasResource && $this->option('views') && ! $this->option('api')) {
                $stub = str_replace('.stub', '.views.stub', $stub);
            }
        }

        $stub = $stub ?? '/stubs/controller.plain.stub';

        return __DIR__.$stub;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {

        $options = [

            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Generate a resource controller for the given model', false],

            ['parent', 'p', InputOption::VALUE_OPTIONAL, 'Generate a nested resource controller class', false],

            ['path', 'D', InputOption::VALUE_OPTIONAL, 'Where the controller should be created if specified'],

            ['policy', 'P', InputOption::VALUE_OPTIONAL, 'Create a new policy', false],

            ['requests', 'R', InputOption::VALUE_NONE, 'Create new request classes'],

            ['views', null, InputOption::VALUE_NONE, 'Create new view files if the controller is not for the API'],

            ['repository', 'rt', InputOption::VALUE_OPTIONAL, 'Create a new repository file for the model', false],

            ['service', 'sr', InputOption::VALUE_OPTIONAL, 'Create a new service file for the model', false],
        ];

        return $this->mergeOptions(parent::getOptions(), $options);
    }

    // /**
    //  * Get the path with the name of the class without the controller suffix.
    //  *
    //  * @return string
    //  */
    // protected function getBaseClassName()
    // {
    //     return $this->getClassBaseName();
    //     // return preg_replace('/Controller$/', '', $this->argument('name'));
    // }

    // /**
    //  * Get the model class name with the path.
    //  *
    //  * @return string
    //  */
    // protected function getModelName()
    // {
    //     if ($this->qualifyOption('model')) {
    //         return $this->qualifyOption('model');
    //         // return str_replace(['App\\', 'Model\\'], ['', ''], $this->qualifyOption('model'));
    //     }

    //     return $this->getBaseClassName();
    // } 
}
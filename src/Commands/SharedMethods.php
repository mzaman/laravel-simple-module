<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use LaravelSimpleModule\CreateFile;
use LaravelSimpleModule\Helpers\Change;

trait SharedMethods
{

    protected $defaultClassPrefix = 'Default';
    protected $isFresh = true;
    protected $applicationLayers = ['Api', 'Backend', 'Frontend'];
    protected $applicationComponents = ['Migration', 'Event', 'Controller', 'Middleware', 'Request', 'Listener', 'Repository', 'Service', 'Policy', 'Factory', 'Seeder', 'View'];
    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => 'What should the '. (strtolower($this->type) ?? 'class') . ' be named?',
        ];
    }
    

    /**
    **
    * Map the stub variables present in stub to its value
    *
    * @return array
    *
    */
    protected function getChoices()
    {
        $name = $this->getQualifedNameInput();
        $path = $this->option('path');

        $hasNamespace = $this->hasNamespace($name);
        list($namespace, $class) = array_values($this->parseNamespaceAndClass($name));
        switch (true) {
            // Case 1: name argument contains namespace, path not provided
            case $hasNamespace && empty($path):
                $fullPath = $this->getPathFromNamespace($namespace);
                break;

            // Case 2: name argument contains class name, path not provided
            case !$hasNamespace && empty($path):
                $fullPath = $this->getDefautPath();
                break;

            // Case 3: name argument contains namespace, path provided
            case $hasNamespace && !empty($path):
                $fullPath = $path;
                break;

            // Case 4: name argument contains class name, path provided
            case !$hasNamespace && !empty($path):
                $namespace = $path;
                $fullPath = $path;
                break;

            default:
                // Handle any other cases or provide default values if needed
                $namespace = $this->getDefautlNamespace();
                $class = $this->getDefautlClass();
                $fullPath = $this->getDefautPath();
                break;
        }

        return [
            'namespace' => $this->getNamespaceFromPath($namespace),
            'class' => $this->getSingularClassName($class),
            'path' => $this->getPathFromNamespace($fullPath),
        ];
    }

    protected function hasNamespace($class) {
        // If class parameter contains namespace
        preg_match('/(.*)\\\\([a-zA-Z_][a-zA-Z0-9_\\\\]+)$/', $class, $matches);
        if ($matches) {
            return [
                'namespace' => $matches[1] ?? '',
                'class' => $matches[2] ?? ''
            ];
        }

        return false;
    }

    /**
     * Return the Singular Capitalize Name
     * @param $name
     * @return string
     */
    protected function getSingularClassName($name)
    {
        return ucwords(Pluralizer::singular($name));
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
     * Check to make sure if all required directories are available
     *
     * @return void
     */
    private function checkIfRequiredDirectoriesExist()
    {
        $path = $this->getClassPath();
        $this->ensureDirectoryExists($path);
    }

    /**
     * Create trait
     * @param string|bool|null $suffix
     *
     * @return string
     */
    protected function createTrait($suffix = null)
    {
        $namespace = $this->getNamespace();
        $class = $this->getClassName();
        $file = $this->getFile();

        switch (true) {
            case is_bool($suffix) && $suffix === true:
                $class = $this->removeLast($class, [$this->type, $this->getSuffix()]);
                $class .= $this->getSuffix();
                break;
            case is_bool($suffix):
            case empty($suffix):
                $class = $this->removeLast($class, [$this->type]);
                $file = $this->removeLast($file, [$this->type]);
                break;
            case is_string($suffix) && !empty($suffix):
                $class = $this->removeLast($class, [$this->type, $this->getSuffix()]) . $suffix;
                $file = $this->removeLast($file, [$this->type, $this->getSuffix(), '.php']) . $suffix . '.php';
                break;
            default:
        }

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ class }}" => $class
        ];

        $namespacedClass = $namespace . "\\" . $class;

        if($this->isAvailable($namespacedClass)) {

            new CreateFile(
                $stubProperties,
                $file,
                $this->stubPath
            );

            $info = "<fg=yellow>{$this->type} <fg=green>{$class}</> [{$namespacedClass}]";
            $path = $this->getPath($namespacedClass);
            $this->components->info(sprintf('%s [%s] created successfully.', $info, $path));

            return $namespacedClass;

        } else { 
            $this->handleAvailability($namespacedClass);
        }


    }

    /**
     * Create model traits
     *
     * @return void
     */
    protected function createModelTraits()
    {

        $model = $this->parseModelNamespaceAndClass($this->option("path")); //TODO: Fix empty path issue
        $namespace = $model['namespace'];
        $class = $model['class'];

        if($this->type !== 'Model') {
            $class = $this->removeLast($class, [$this->type]);
        }

        // $classBaseName = $this->getClassBaseName();
        // Create model traits
        $modelTraits = ['Attribute', 'Method', 'Relationship', 'Scope'];

        foreach ($modelTraits as $traitType) { 
            $traitClass = "{$namespace}\\Traits\\{$traitType}\\{$class}{$traitType}";

            $this->call('make:trait', [
                'name' => $traitClass,
                '--force' => $this->isAvailable($traitClass)
            ]);
        }
    } 
    
    /**
     * Create the qualified option based on $type.
     *
     * @param string $type The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @return void
     */
    protected function qualifyOptionCreate($type, $model = null) {
        $type = $this->toLowerSingular($type);
        $name = $this->qualifyOption($type); 
        if($name) {
            $this->call("make:{$type}", array_filter([
                "name" => $name,
                '--model' => $model ? $this->qualifyOption($model) : null,
                "--force" => $this->isAvailable($name, $type)
            ]));
        }
    }

    /**
     * Create request file for the model.
     *
     * @param  string|null  $name
     * @param bool|null $isFresh Create a fresh request file 
     * @return void
     */
    protected function createRequest($name, $isFresh = null)
    {
        $isFresh = $isFresh ?: ($this->isFresh ? $this->isAvailable() : false);
        $this->call('make:request', array_filter([
            'name' => $name,
            '--force' => $isFresh
        ]));
    }

    /**
     * Get the qualified option.
     *
     * @param string $name
     * @param string|null $type The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @return string The converted class.
     */
    protected function qualifyOption($name, $type = null)
    {
        $option = $this->option($name);

        // When option is not provided
        if(is_bool($option) && !$option) {
            return $option;
        }

        $class = $this->getClassBaseName();
        $class = $this->removeLast($class, ['Repository', 'Service']);
        // $class = class_basename($this->getModelClass());
        $normalizedType = $this->toPascalSingular($type ?: $name);
        $namespace = $this->getQualifiedNamespace($normalizedType);
        $suffix = $this->getSuffix($normalizedType);
        $class .= $suffix;

        if ($normalizedType == 'Model') {
            $class = $this->removeLast($class, ['Api', 'Backend', 'Frontend', 'Model']);
        }
        
        // When option is expected, but name is not provided
        if(is_null($option)) {
            $option = "{$namespace}\\{$class}";
        }

        // When option is expected, but name is provided
        if(is_string($option)) {
            $option = $this->getQualifiedClass($option, $normalizedType);
        }


        return $option;

    }

    /**
     * Simplified method for handling choices and default values.
     *
     * @param string $question
     * @param array $choices
     * @param array $commonChoices
     * @param int $defaultIndex
     * @param bool $allowMultipleSelections
     * @return array
     */
    protected function handleChoices($question, $choices, $commonChoices = ['All', 'None'], $defaultIndex = 0, $allowMultipleSelections = true)
    {
        $selectedChoices = $this->choice($question,  [...$commonChoices, ...$choices], $defaultIndex, $maxAttempts = null, $allowMultipleSelections);
        
        if (in_array($commonChoices[0], $selectedChoices)) {
            return $choices;
        } elseif (isset($commonChoices[1]) && in_array($commonChoices[1], $selectedChoices)) {
            return [];
        } else {
            return $this->removeByValues($selectedChoices, $commonChoices);;
        }
    }

    /**
     * Prompt the user for names of a specific type of instance (e.g., model, service, repository, etc.).
     *
     * @param string|null $type The type of instance to prompt for.
     * @return array The names of the instances in Pascal case.
     */
    protected function askNames($type = null)
    {
        // Use the provided $type or fallback to the default type and format the type name for display.
        $type = $this->toPascalSingular($type ?: $this->type);

        do {
            // Ask for instance names
            $names = $this->ask("Enter {$type} names (comma-separated)");

            // Convert to Pascal case and filter out empty and duplicate names
            $instances = array_values(array_unique(array_filter(array_map(function ($name) {
                return $this->toPascal($name);
            }, explode(',', $names)))));

            // Check if at least one instance is provided
            if (empty($instances)) {
                $this->error("At least one valid {$type} name is needed. Please try again.");
            }
        } while (empty($instances)); // Continue prompting until a valid input is provided

        return $instances;
    }

    /**
     * Handle availability.
     *
     * @param  string|null  $class
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function handleAvailability($class = null, $type = null)
    {
        $type = $type ?: $this->type;
        $isAvailable = $this->isAvailable($class, $type);
        $class = $this->getQualifiedClass($class, $type);
        $className = class_basename($class);
        
        if (!$isAvailable) {
            $this->components->error("<fg=yellow>{$type}</> <fg=green>{$className} [{$class}] <fg=yellow>already exists.</>");

            if ($this->confirm('Do you wish to replace...?', false)) {
                $this->input->setOption('force', true);
                if($type == 'Model' || $type == 'Controller') {
                    parent::handle();
                } else {
                    $this->handle();
                }

            }
        }

        return true;
    }

    /**
     * Check if available.
     *
     * @param  string  $class
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function isAvailable($class = null, $type = null)
    {
        $type = $type ?: $this->type;
        $class = $this->getQualifiedClass($class, $type);
        $exists = $this->exists($class, $type);
        $isAvailable = (! $this->hasOption('force') ||
             ! $this->option('force')) && $exists ? false : true; 
        return $isAvailable ? $class : $isAvailable;
    }

    /**
     * Determine if not exists.
     *
     * @param  string|null  $class
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function notExists($class = null, $type = null)
    {
        return !$this->exists($class, $type);
    }

    /**
     * Determine if already exists.
     *
     * @param  string|null  $name
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function exists($name = null, $type = null)
    {
        $name = $this->getQualifiedClass($name, $type);
        return (interface_exists($name) || trait_exists($name) || class_exists($name)) ? $name : false;
    }


    /**
     * Create the interface
     * @param string|null $type
     *
     * @return void
     */
    protected function createInterface($type = null)
    {
        $type = $type ?: $this->type;
        $class = $this->getClassName($type);
        $interface = $this->getInterfaceClassName($type);
        $namespace = $this->getNamespace();
        // $classBaseName = $this->getClassBaseName($type);
        // $namespace = $this->buildNamespace($classBaseName);

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ interface }}" => $interface,
        ];


        $interfaceFile = $this->getInterfaceFile();

        $namespacedInterface = $namespace . "\\" . $interface;

        if($this->isAvailable($namespacedInterface, 'Interface')) {
            // check folder exist
            $folder = str_replace('\\','/', $namespace);
            if (!file_exists($folder)) {
                File::makeDirectory($folder, 0775, true, true);
            }

            new CreateFile(
                $stubProperties,
                $interfaceFile,
                $this->interfaceStubPath
            );

            // $this->line("<info>Created $interface interface:</info> {$namespacedInterface}");

            $info = "<fg=yellow>{$this->type} Interface <fg=green>{$interface}</> [{$namespacedInterface}]";
            $path = $this->getPath($namespacedInterface);
            $this->components->info(sprintf('%s [%s] created successfully.', $info, $path));

            return $namespacedInterface;
        } else {
            $this->handleAvailability($namespacedInterface);
        }

    }

    /**
     * Get interface file path
     * @param bool $isDefault
     * @param string|null $type
     *
     * @return string
     */
    private function getFile($isDefault = true, $type = null)
    {
        $type = $type ?: $this->type;
        $fileName =  $isDefault
            ? $this->getClassName($type)
            : "/Other/". $this->getClassName($type); 
        return $this->getClassPath() . DIRECTORY_SEPARATOR . $fileName . '.php';
    }

    /**
     * Remove the substring from the input string
     * @param string|null $string
     * @param array $substrArr
     *
     * @return string
     */
    private function removeLast($string, $substrArr = ['Model', 'Trait'])
    {
        // Iterate through each substring in the array
        foreach ($substrArr as $substring) {
            // Remove the substring from the input string
            $string = Str::replaceLast($substring, '', $string);
        }
        return $string;
    }

    /**
     * Get the relative path based on the specified type or the default type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return string The relative path.
     */
    protected function getRelativePath($type = null)
    {
        // Use the provided type or the default type
        $type = $type ?: $this->type;

        // Return the relative path by removing the root namespace
        return Str::replaceFirst($this->getRootNamespace($type), '', $this->getNamespace());
    }


    /**
     * Get the qualified namespace based on $this->type.
     *
     * @param string $type The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @param array|int $withSubPath
     * @return string The converted namespace.
     */
    protected function getQualifiedNamespace($type = null, $withSubPath = true)
    {
        $type = $type ?: $this->type;
        $namespace =$this->getNamespaceFromPath($this->getRootNamespace() . '\\' . $this->toPascalPlural($type));

        if(!$this->isHttpType($type)) {
            $suffix = '\\Http';
            if (Str::contains($namespace, $suffix)) {
                $namespace = Str::remove($suffix, $namespace);
            }
        }

        if($withSubPath && $type !== 'Model') {
            $namespace .= $this->getRelativePath(/*$type*/);
        }

        return $namespace;
        // return $this->qualifyNamespace($namespace);
    }

    /**
     * Get the converted namespace based on $this->type.
     *
     * @param string $type The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @param array|int $needle
     * @return string The converted namespace.
     */
    protected function getConvertedNamespace($type, $needle = 1) {
        $namespace = $this->namespaceSlice($this->getConvertedClass($type), $needle);
        return $namespace;
    }

    /**
     * Get interface file path
     * @param string|null $type
     *
     * @return string
     */
    private function getInterfaceFile($type = null)
    {
        $type = $type ?: $this->type;
        $interfaceFile = $this->getInterfaceClassName($type) . ".php";
        return $this->getClassPath() . DIRECTORY_SEPARATOR . $interfaceFile;
    }

    /**
     * Get interface namespace
     *
     * @return string
     */
    private function getInterfaceNamespace(string $className)
    {
        return $this->getNamespace() . "\\". $className;
    }


    /**
     * get iterface class name with suffix
     * 
     * @param string|null $type
     * @return string
     */
    private function getInterfaceClassName($type = null) : string {
        $type = $type ?: $this->type;
        $suffix = $this->getInterfaceSuffix($type);
        return $this->getClassBaseName($type) . $suffix;
    }

    /**
     * get class name with suffix
     * 
     * @param string|null $type
     * @return string
     */
    private function getClassName($type = null) : string {
        $type = $type ?: $this->type;
        $suffix = $this->getSuffix($type)?: null;
        return $this->getClassBaseName($type) . $suffix;
    }

    // /**
    //  * get class name
    //  * @param $classBaseName
    //  * 
    //  * @return string
    //  */
    // private function getClassName($classBaseName) : string {
    //     $explode = explode('/', $classBaseName);
    //     return $explode[array_key_last($explode)];
    // }

    /**
     * get namespace
     * @param $classBaseName
     * @return string
     */
    private function recognizeNamespace($classBaseName) : string {
        $explode = explode('\\', $classBaseName);
        if (count($explode) > 1) {
            $namespace = '';
            for($i=0; $i < count($explode)-1; $i++) {
                $namespace .= '\\' . $explode[$i];
            }
            return $this->getNamespace() . $namespace;
        } else {
            return $this->getNamespace();
        }
    }

    /**
     * Determine the model class based on the provided namespace.
     *
     * @return string The determined model namespace.
     */
    function getModelClass()
    {
        return $this->parseModelNamespaceAndClass()['class'];
    }

    /**
     * Get the class name of the grandparent class.
     *
     * @return string
     */
    protected function getGrandparentClass()
    {
        return get_parent_class(
            get_parent_class($this)
        );
    }

    /**
     * Determine the model namespace based on the provided namespace.
     *
     * @return string The determined model namespace.
     */
    function getModelNamespace()
    {
        return $this->parseModelNamespaceAndClass()['namespace'];
    }

    /**
     * Get the model class name with the path.
     *
     * @return string
     */
    protected function getModelName()
    {
        if ($this->option('model')) {
            return $this->option('model');
            // return str_replace(['App\\', 'Model\\'], ['', ''], $this->option('model'));
        }

        return $this->getBaseClassName();
    } 

    /**
     * Parse the model namespace and class from a namespaced class.
     *
     * @param string|null $model The namespaced class name.
     *
     * @return array Associative array with 'namespace' and 'class' keys.
     */
    protected function parseModelNamespaceAndClass($model = null) {
        $model = $model ?: $this->getNamespacedModel();
        $model = $this->removeLast($model, [!in_array($this->type, ['Model', 'Module']) ? $this->type : null, '\\Modules', 'Api', 'Backend', 'Frontend', 'Services', 'Repositories']);
        return $this->parseNamespaceAndClass($model);
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Qualify the given model class base name.
     *
     * @param  string  $model
     * @return string
     */
    protected function qualifyModel(string $model)
    {
        $model = ltrim($model, '\\/');

        $model = str_replace('/', '\\', $model);

        $laravelNamespace = $this->laravelNamespace();

        if (Str::startsWith($model, $laravelNamespace)) {
            return $model;
        }

        return is_dir(app_path('Models'))
                    ? $laravelNamespace.'Models\\'.$model
                    : $laravelNamespace.$model;
    }

    /**
     * Build a name corresponding to the given class.
     *
     * @param string|null $class
     * @return string|null
     */
    protected function buildClassName($class = null, array $suffixes= [])
    {
        $class = $class ?: $this->getNamespacedClass();
  
        $type = $this->type ?: $this->getDefautlNamespace();
        $suffixes = $suffixes ?: ['Api', 'Backend', 'Frontend'];

        // Remove common suffixes like 'Api', 'Backend', 'Frontend' from the last part of the repository class name
        $classVariable = class_basename($class);
        $commonSuffixes = [$type, ...$suffixes];


        foreach ($commonSuffixes as $suffix) {
            $commonSuffixes[] = Str::studly($suffix . $type);
            $commonSuffixes[] = Str::studly($type . $suffix);
        }

        foreach ($commonSuffixes as $suffix) {
            if (Str::contains($classVariable, $suffix)) {
                $classVariable = Str::remove($suffix, $classVariable);
    
            }
        }


        return $classVariable;
    }

    /**
     * Get the model class corresponding to the given class.
     *
     * @param string|null $class
     * @return string|null
     */
    protected function getNamespacedModel($class = null)
    {
        $class = $class ?: $this->getNamespacedClass();
        // Get the root namespace based on the position of the type of class directory
        $modelNamespace = $this->getRootNamespace() . '\\Models\\';
        $modelVariable = $this->buildClassName($class);

        if($this->isHttpType()) {
            $suffix = '\\Http';
            if (Str::contains($modelNamespace, $suffix)) {
                $modelNamespace = Str::remove($suffix, $modelNamespace);
            }
        }

        // $rootNamespace = $this->getRootNamespace();

        // $namespace = $this->getNamespace();
        // // Split the namespace by backslash ('\') into an array of segments
        // $namespaceSegments = explode('\\', $namespace);

        // // Check if the first segment is $this->getDefautlNamespace()
        // if (reset($namespaceSegments) === $this->getDefautlNamespace()) {
        //     // If it is, return 'App\Models' as the model namespace
        //     $modelNamespace = $this->getDefautlNamespace('Model');
        // } else {
        //     $modelNamespace =  $rootNamespace . '\\Models';
        // }

        // $type = $this->type ?: $this->getDefautlNamespace();

        // // Remove common suffixes like 'Api', 'Backend', 'Frontend' from the last part of the repository class name
        // $modelVariable = class_basename($class);
        // $commonSuffixes = ['Api', 'Backend', 'Frontend', $type];


        // foreach ($commonSuffixes as $suffix) {
        //     $commonSuffixes[] = Str::studly($suffix . $type);
        //     $commonSuffixes[] = Str::studly($type . $suffix);
        // }

        // foreach ($commonSuffixes as $suffix) {
        //     if (Str::contains($modelVariable, $suffix)) {
        //         $modelVariable = Str::remove($suffix, $modelVariable);
    
        //     }
        // }

        // Combine the segments to get the full model class
        return $modelNamespace . $modelVariable;
    }


    /**
     * Get the class corresponding to the given class.
     *
     * @param string|null $class
     * @return string|null
     */
    protected function getNamespacedRepositoryOrService($class = null)
    {
        $class = $class ?: $this->getNamespacedClass();
        // Get the root namespace based on the position of the type of class directory
        $rootNamespace = $this->getRootNamespace($class);

        $namespace = $this->getNamespace();
        // Split the namespace by backslash ('\') into an array of segments
        $namespaceSegments = explode('\\', $namespace);

        $classType = $this->toPascalPlural($this->type);

        // Check if the first segment is $this->getDefautlNamespace()
        if (reset($namespaceSegments) === $this->getDefautlNamespace()) {
            // If it is, return the default namespace
            if($classType == 'Services') {
                $classNamespace = config('simple-module.repository_namespace') ?? 'App\\Repositories';
            }
            
            if($classType == 'Repositories') {
                $classNamespace = config('simple-module.service_namespace') ?? 'App\\Services';
            }

        } else {
            if($classType == 'Services') {
                $classNamespace = $rootNamespace . '\\Repositories';
            }
            
            if($classType == 'Repositories') {
                $classNamespace = $rootNamespace . '\\Services';
            }
            $classNamespace =  $rootNamespace . '\\Models';
        }

        $classVariable = class_basename($class);

        // Combine the segments to get the full model class
        return $classNamespace . '\\' . $classVariable;

    }


    /**
     * Get the converted class based on $this->type.
     *
     * @param string|null $namespace The input namespace.
     * @param string|null $to The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @param string|null $from The source type for conversion (e.g., 'Model', 'Service', etc.).
     * @return string The converted namespace.
     */
    protected function getConvertedClass($to = null, $from = null, $namespace = null)
    {
        // Set default values if not provided
        $from = $from ?: $this->type;
        $namespace = $namespace ?: $this->getNamespacedClass();

        switch ($from) {
            case 'Service':
                $to = $to ?: 'Repository';
                break;
            case 'Repository':
                $to = $to ?: 'Model';
                break;
            default:
                $to = $to ?: 'Repository';
        }

        // Check if the namespace contains the "from" string
        if (Str::endsWith($namespace, $from)) {
            // Remove "from" from the end of the namespace
            $namespaceWithoutClass = Str::beforeLast($namespace, $from);

            // Replace "from" with "to"
            $convertedNamespace = $this->convertNamespace(
                $namespaceWithoutClass,
                $this->toPascalPlural($from),
                $this->toPascalPlural($to)
            );

            // Append "to" to the singularized class name
            $convertedClass = $convertedNamespace . $this->getSuffix($to);

            return $convertedClass;
        }

        return $namespace;
    }

    /**
     * Convert namespace based on the provided type.
     *
     * @param string $namespace The input namespace.
     * @param string $from The source type for conversion (e.g., 'Model', 'Service', etc.).
     * @param string $to The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @return string The converted namespace.
     */
    protected function convertNamespace($namespace, $from, $to)
    {
        // Check if the namespace contains the "from" string
        if (Str::contains($namespace, $from)) {
            // Replace "from" with "to"
            $convertedNamespace = Str::replace($from, $to, $namespace);

            return $convertedNamespace;
        }

        return $namespace;
    }

    /**
     * get the class suffix from type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     *
     * @return string
     */
    protected function getSuffix($type = null) {
        $type = $type ?: $this->type;
        $normalizedType = $this->toPascalSingular($type);
        $key = 'simple-module-sys.' . $this->toLowerSingular($type) . '_suffix';
        return config($key) ?: $normalizedType;
    }

    /**
     * get the interface class suffix from type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     *
     * @return string
     */
    protected function getInterfaceSuffix($type = null) {
        $type = $type ?: $this->type;
        $normalizedType = $this->toPascalSingular($type);
        $key = 'simple-module-sys.' . $this->toLowerSingular($type) . '_interface_suffix';
        return config($key) ?: $normalizedType . 'Interface';
    }

    /**
     * get the default class name from type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     *
     * @return string
     */
    protected function getDefautlClass($type = null) {
        $type = $type ?: $this->type;
        $normalizedType = $this->toPascalSingular($type);
        $key = 'simple-module.' . $this->toLowerSingular($type) . '_class';
        return config($key) ?: $defaultClassPrefix . $normalizedType;
    }

    /**
     * get the default namespace from type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     *
     * @return string
     */
    protected function getDefautlNamespace($type = null) {
        $type = $type ?: $this->type;
        $normalizedNamespace = $this->laravelNamespace() . ($this->isHttpType() ? 'Http\\' : null) . $this->toPascalPlural($type);

        $key = 'simple-module.' . $this->toLowerSingular($type) . '_namespace';

        return config($key) ?: $normalizedNamespace;
    }

    /**
     * Check if Http type component from type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     *
     * @return bool
     */
    protected function isHttpType($type = null) {
        $type = $type ?: $this->type;
        $httpTypes = ['Controller', 'Middleware', 'Request', 'Resource', 'Livewire'];
        return in_array($type, $httpTypes);

    }

    /**
     * get the default path from type.
     *
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     *
     * @return string
     */
    protected function getDefautPath($type = null) {
        $type = $type ?: $this->type;
        $normalizedPath = $this->laravelPath() . ($this->isHttpType() ? DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR : DIRECTORY_SEPARATOR) . $this->toPascalPlural($type);
        $key = 'simple-module.' . $this->toLowerSingular($type) . '_directory';
        return config($key) ?: $normalizedPath;
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $laravelNamespace = $this->laravelNamespace();

        if (Str::startsWith($name, $laravelNamespace)) {
            return $name;
        }

        return $this->qualifyClass(trim($laravelNamespace, '\\').'\\'.$name
        );
    }

    /**
     * Get the desired class name from the input and format.
     *
     * @return string
     */
    protected function getQualifedName($name)
    {
        $name = ltrim($name, '\\/');
        return str_replace('/', '\\', $name);
    }

    /**
     * Get the desired class name from the input and format.
     *
     * @return string
     */
    protected function getQualifedNameInput()
    {
        $name = $this->getNameInput();
        $name = ltrim($name, '\\/');
        return str_replace('/', '\\', $name);
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function laravelNamespace()
    {
        return $this->laravel->getNamespace();
    }

    /**
     * Get the root path for the class.
     *
     * @return string
     */
    protected function laravelPath()
    {
        return $this->laravel['path'];
    }

    /**
     * Parse the namespace and class from a namespaced class.
     *
     * @param string $name The namespaced class name.
     * @param string|null The type of the class (e.g., 'Model', 'Service', etc.).
     *
     * @return array Associative array with 'namespace' and 'class' keys.
     */
    protected function parseNamespaceAndClass($name, $type = null) {
        // Case 1: Name parameter contains namespace
        $hasNamespace = $this->hasNamespace($name); 
        if ($hasNamespace) {
            return $hasNamespace;
        }

        // Case 2: Name parameter contains only class name
        $defaultNamespace = $this->getDefautlNamespace($type);

        return [
            'namespace' => $defaultNamespace ?: '',
            'class' => $name ?: ''
        ];
    }

    protected function getQualifiedClass($class = null, $type = null) 
    {
        $type = $type ?: $this->type;
        $class = $class ?: $this->getNamespacedClass();
        $class = ltrim($class, '\\/');
        $class = str_replace('/', '\\', $class);
        $class = implode('\\', $this->parseNamespaceAndClass($class, $type));
        return $class;
    }
    /**
     * Get the root namespace based on the position of the type of class directory.
     *
     * @param string|null $type
     * @param string|null $class
     * @return string
     */
    private function getRootNamespace($type = null, $class = null)
    {
        $hasType = $type;
        $type = $type ?: $this->type;
        $isNotSelfType = $hasType && $type !== $this->type;
        $normalizedType = $this->toPascalPlural($type);
        $class = $class ?: $this->getNamespacedClass();
        $namespace = $this->parseNamespaceAndClass($class, $type)['namespace'];

        // Get the namespace slice based on the type
        $namespaceSlice = $this->namespaceSlice($namespace, [$normalizedType]);
        
        // If the namespace slice is not empty, return it
        if (!empty($namespaceSlice)) {
            $namespacedSlice = ucwords($hasType ? $namespaceSlice . '\\' . $normalizedType : $namespaceSlice);

            return $namespacedSlice;
        }

        // If not found, return the default root namespace
        return $this->buildNamespace($namespace, $type);
    }

    /**
     * get namespace
     * @param $namespacedClass
     * @param string|null $type
     * @return string
     */
    private function buildNamespace($namespacedClass = null, $type = null) : string {
        $hasType = $type;
        $type = $type ?: $this->type;
        $isNotSelfType = $type !== $this->type;
        $normalizedType = $this->toPascalPlural($type);
        $class = $namespacedClass ?: $this->getNamespacedClass();
        // Get the namespace slice based on the type
        $namespaceSlice = $this->namespaceSlice($class, [$normalizedType]);
        $class = $this->getNamespaceFromPath($namespaceSlice ?: $class);
        $defaultNamespace = $this->getDefautlNamespace($type);

        if($this->hasNamespace($class) && $class !== $defaultNamespace){
            $namespace = Str::start($class . '\\' . $normalizedType, $this->laravelNamespace());
            return ucwords($namespace);
        } else {
            return ucwords($defaultNamespace);
        }

        // $namespace = $this->hasNamespace($class) ? $class . '\\' . $normalizedType : $this->getDefautlNamespace($type);

        // $explode = explode('\\', $namespacedClass);
        // if (count($explode) > 1) {
        //     $namespace = '';
        //     for($i=0; $i < count($explode)-1; $i++) {
        //         $namespace .= '\\' . $explode[$i];
        //     }

        //     echo 'buildNamespace__namespace ' . $namespace . "\n";
        //     return $this->getNamespace() . $namespace;
        // } else {
        //     return $this->getNamespace();
        // }
    }

    /**
     * Get the namespace slice based on the given needles.
     *
     * @param string $namespace
     * @param array|int $needles
     * @return string
     */
    protected function namespaceSlice(string $namespace, $needles)
    {
        $namespace = $this->getNamespaceFromPath($namespace);
        // Extract the segments from the namespace
        $segments = explode('\\', $namespace);

        // Check if the namespace contains any of the given needles
        if(is_array($needles)) {
            foreach ($needles as $needle) {
                if (in_array($needle, $segments)) {
                    // Return the namespace up to the needle
                    $namespaceSlice = implode('\\', array_slice($segments, 0, array_search($needle, $segments, true)));
                    return $namespaceSlice;
                }
            }  
        }

        if(is_int($needles)) {
            // Remove $needles number of segments 
            for ($i=0; $i < $needles; $i++) { 
                array_pop($segments); // Remove last segment
            }

            $namespaceSlice = implode('\\', $segments);

            return $namespaceSlice;
        }

        // Return an empty string if none of the needles are found
        return '';
    }

    /**
     * Get the base name of the class.
     *
     * @param string|null $type
     * @return string
     */
    protected function getClassBaseName($type = null)
    {
        $suffix = $this->getSuffix($type ?: $this->type);
        $class = $this->getClass();

        // Extract the class name from the input
        $className = class_basename($class);

         // Check if the string ends with $suffix
        if (Str::endsWith($className, $suffix)) {
            // Remove the last occurrence of $suffix from the end of the string
            $className = Str::beforeLast($className, $suffix);
        }

        // Use a regular expression to extract the last word as the base class name
        if (preg_match('/\\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $className, $matches)) {
            return $matches[1] ?? '';
        }

        // return the result
        return $className;
    }

    /**
     * Get the path with the name of the class without the controller suffix.
     *
     * @return string
     */
    protected function getBaseClassName()
    {
        return $this->getClassBaseName();
    }

    /**
     * Get the full path of the class file.
     *
     * @return string
     */
    protected function getClassPath()
    {
        return $this->getChoices()['path'];
    }

    /**
     * Get the full namespace for a given class, without the class name.
     *
     * @param  string|null  $name
     * @return string
     */
    protected function getNamespace($name = null)
    {
        if($name) {
            return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
        } else { 
            $namespace = $this->qualifyNamespace($this->getClassPath());
            return $namespace;
        }
    }

    /**
     * Qualify a namespace based on the Laravel path and namespace.
     *
     * @param string      $namespace The given namespace to qualify.
     * @param string|null $rootPath  The root path to use for normalization.
     * @return string The qualified namespace.
     */
    protected function qualifyNamespace($namespace, $rootPath = null)
    {
        // Normalize paths for comparison
        $namespace = realpath($namespace);
        $laravelPath = realpath($rootPath ?: $this->laravelPath());

        // Check if the given namespace is within the Laravel path
        if (Str::startsWith($namespace, $laravelPath)) {
            // Remove the Laravel path and convert backslashes to slashes
            $relativePath = Str::replaceFirst($laravelPath, '', $namespace);
            $relativePath = Str::replace('\\', '/', $relativePath);

            // Combine the Laravel namespace and the relative path
            $qualifiedNamespace = rtrim($this->laravelNamespace(), '\\') . Str::replace('/', '\\', $relativePath);

            return $qualifiedNamespace;
        }
        // Return the original namespace if it's not within the Laravel path
        return $namespace;
    }

    /**
     * Get the pluralized pascal name.
     * @param $path
     *
     * @return string
     */
    protected function toPascalPlural($name)
    {
        return Str::studly(Pluralizer::plural(Str::lower($name)));
    }
    
    /**
     * Get the lower singularized name.
     * @param $path
     *
     * @return string
     */
    protected function toLowerSingular($name)
    {
        return Pluralizer::singular(Str::lower($name));
    }

    /**
     * Get the singular pascal name.
     * @param $path
     *
     * @return string
     */
    protected function toPascalSingular($name)
    {
        return Str::studly(Pluralizer::singular(Str::lower($name)));
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->laravelNamespace(), '', $name);

        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    /**
     * Get the name of class.
     *
     * @return string
     */
    protected function getClass()
    {
        return $this->getChoices()['class'];
    }

    /**
     * Get namespaced class
     *
     * @return string
     */
    private function getNamespacedClass()
    {
        return $this->getNamespace() . '\\' . $this->getClass();


    }

    /**
     * Get namespace from path
     * @param $path
     *
     * @return string
     */
    private function getNamespaceFromPath($path)
    {
        return ucwords(str_replace('/', '\\', $path));
    }

    /**
     * Get path from namespace
     * @param $namespace
     *
     * @return string
     */
    private function getPathFromNamespace($namespace)
    {
        return str_replace('\\', '/', $namespace);
    }

    /**
     * Merge two arrays of options, removing any duplicates from the second array.
     *
     * @param  array  $options1
     * @param  array  $options2
     * @return array
     */
    protected function mergeOptions($options1, $options2)
    {
        // Extract keys to remove from the second array
        $keysToRemove = array_column($options2, 0);

        // Filter options from the first array based on keys to remove
        $mergedOptions = array_filter($options1, function ($option) use ($keysToRemove) {
            return !in_array($option[0], $keysToRemove);
        });

        // Combine filtered options with the second array
        return array_merge($mergedOptions, $options2);
    }
}

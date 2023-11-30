<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use LaravelSimpleModule\CreateFile;

trait SharedMethods
{

    protected $defaultClassPrefix = 'Default';

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
    public function getChoices()
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

    public function hasNamespace($class) {
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
    public function getSingularClassName($name)
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
    public function createTrait($suffix = null)
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
		$this->handleAvailability($namespacedClass);
        new CreateFile(
            $stubProperties,
            $file,
            $this->stubPath
        );

        $this->line("<info>Created {$class} {$this->toLowerSingular($this->type) }:</info> {$namespacedClass}");

        return $namespacedClass;
    }

    /**
     * Handle availability.
     *
     * @param  string  $class
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function handleAvailability($class, $type = null)
    {
        $type = $type ?: $this->type;
        if (!$this->isAvailable($class, $type)) {
            $this->components->error($type.' already exists.');
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
    protected function isAvailable($class, $type = null)
    {
        $type = $type ?: $this->type;
        if ((! $this->hasOption('force') ||
             ! $this->option('force')) &&
             $this->exists($class, $type)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if not exists.
     *
     * @param  string  $class
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function notExists($class, $type = null)
    {
        return !$this->exists($class, $type);
    }

    /**
     * Determine if already exists.
     *
     * @param  string  $name
     * @param string|null $type The type of the namespace (e.g., 'Model', 'Service', etc.).
     * @return bool
     */
    protected function exists($name, $type = null)
    {
        $name = $this->getQualifiedClass($name, $type);
        return interface_exists($name) || trait_exists($name) || class_exists($name);
    }


    /**
     * Create the interface
     * @param string|null $type
     *
     * @return void
     */
    public function createInterface($type = null)
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

        // check folder exist
        $folder = str_replace('\\','/', $namespace);
        if (!file_exists($folder)) {
            File::makeDirectory($folder, 0775, true, true);
        }

        $interfaceFile = $this->getInterfaceFile();

        new CreateFile(
            $stubProperties,
            $interfaceFile,
            $this->interfaceStubPath
        );

        $this->line("<info>Created $interface interface:</info> {$namespace}\\{$interface}");

        return $namespace . "\\" . $interface;
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
     * Determine the model namespace based on the provided namespace.
     *
     * @return string The determined model namespace.
     */
    function getModelNamespace()
    {
        return $this->parseModelNamespaceAndClass()['namespace'];
    }

    /**
     * Parse the model namespace and class from a namespaced class.
     *
     * @param string|null $model The namespaced class name.
     *
     * @return array Associative array with 'namespace' and 'class' keys.
     */
    public function parseModelNamespaceAndClass($model = null) {
        $model = $model ?: $this->getNamespacedModel();
        return $this->parseNamespaceAndClass($model);
    }

    /**
     * Build a name corresponding to the given class.
     *
     * @param string|null $class
     * @return string|null
     */
    public function buildClassName($class = null, array $suffixes= [])
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
    public function getNamespacedModel($class = null)
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
    public function getNamespacedRepositoryOrService($class = null)
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
    public function getConvertedClass($to = null, $from = null, $namespace = null)
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
    public function getSuffix($type = null) {
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
    public function getInterfaceSuffix($type = null) {
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
    public function getDefautlClass($type = null) {
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
    public function getDefautlNamespace($type = null) {
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
    public function getDefautPath($type = null) {
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
    public function parseNamespaceAndClass($name, $type = null) {
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
    public function namespaceSlice(string $namespace, $needles)
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
    public function getClassBaseName($type = null)
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
     * Get the full path of the class file.
     *
     * @return string
     */
    public function getClassPath()
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
    public function toPascalPlural($name)
    {
        return Str::studly(Pluralizer::plural(Str::lower($name)));
    }
    
    /**
     * Get the lower singularized name.
     * @param $path
     *
     * @return string
     */
    public function toLowerSingular($name)
    {
        return Pluralizer::singular(Str::lower($name));
    }

    /**
     * Get the singular pascal name.
     * @param $path
     *
     * @return string
     */
    public function toPascalSingular($name)
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
    public function getClass()
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

}

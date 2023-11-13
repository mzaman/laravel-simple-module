<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use LaravelSimpleModule\CreateFile;

trait SharedMethods
{

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => 'What should the '.strtolower($this->type) ?? 'class'.' be named?',
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
        $name = Str::studly($this->argument("name"));
        $path = $this->option('path');

        switch (true) {
            // Case 1: name argument contains namespace, path not provided
            case preg_match('/(.*)\\\\([a-zA-Z]+)$/', $name, $matches) && empty($path):
                $namespace = $matches[1];
                $className = $matches[2];
                $fullPath = $this->getPathFromNamespace($namespace);
                break;

            // Case 2: name argument contains class name, path not provided
            case !preg_match('/\\\\/', $name) && empty($path):
                $namespace = $this->defaultNamespace;
                $className = $name;
                $fullPath = $this->defaultPath;
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
                $namespace = $this->defaultNamespace;
                $className = $this->defaultClass;
                $fullPath = $this->defaultPath;
                break;
        }

        return [
            'namespace' => $namespace,
            'class' => $this->getSingularClassName($className),
            'path' => $fullPath,
        ];
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
        $path = $this->getPath();
        $this->ensureDirectoryExists($path);
    }

    /**
     * Create the interface
     *
     * @param string $className
     * @return void
     */
    public function createInterface(string $classBaseName)
    {
        $class = $this->getClassName($classBaseName);
        $interface = $class . $this->interfaceSuffix;
        $namespace = $this->recognizeNamespace($classBaseName);

        $stubProperties = [
            "{{ namespace }}" => $namespace,
            "{{ interface }}" => $interface,
        ];

        // check folder exist
        $folder = str_replace('\\','/', $namespace);
        if (!file_exists($folder)) {
            File::makeDirectory($folder, 0775, true, true);
        }

        $interfaceFile = $this->getInterfaceFile($classBaseName);

        new CreateFile(
            $stubProperties,
            $interfaceFile,
            $this->interfaceStubPath
        );

        // $this->line("<info>Created $classBaseName  interface:</info> {$namespace}\\{$class}");

        return $namespace . "\\" . $classBaseName;
    }

    /**
     * Get repository interface path
     * @param $classBaseName
     *
     * @return string
     */
    private function getInterfaceFile($classBaseName)
    {
        return $this->getPath() . "/$classBaseName" . $this->interfaceSuffix . ".php";
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
     * get class name
     * @param $classBaseName
     * 
     * @return string
     */
    private function getClassName($classBaseName):string {
        $explode = explode('/', $classBaseName);
        return $explode[array_key_last($explode)];
    }

    /**
     * get namespace
     * @param $classBaseName
     * @return string
     */
    private function recognizeNamespace($classBaseName):string {
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
        // $namespace = $this->getNamespace();
        // // Split the namespace by backslash ('\') into an array of segments
        // $segments = explode('\\', $namespace);

        // // Check if the first segment is 'App\Repositories'
        // if (reset($segments) === $this->defaultNamespace) {
        //     // If it is, return 'App\Models' as the model namespace
        //     return 'App\Models';
        // }

        // // If not, remove the last segment to get the model namespace
        // array_pop($segments);

        // // Reconstruct the model namespace by joining the segments with backslash and adding '\Models'
        // return implode('\\', $segments) . '\\Models';
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
     * Get the model class corresponding to the given class.
     *
     * @param string|null $class
     * @return string|null
     */
    public function getNamespacedModel($class = null)
    {
        $class = $class ?: $this->getNamespacedClass();
        // Get the root namespace based on the position of the type of class directory
        $rootNamespace = $this->getRootNamespace($class);

        $namespace = $this->getNamespace();
        // Split the namespace by backslash ('\') into an array of segments
        $namespaceSegments = explode('\\', $namespace);

        // Check if the first segment is $this->defaultNamespace
        if (reset($namespaceSegments) === $this->defaultNamespace) {
            // If it is, return 'App\Models' as the model namespace
            $modelNamespace = config('simple-module.model_namespace') ?? 'App\\Models';
        } else {
            $modelNamespace =  $rootNamespace . '\\Models';
        }

        $type = $this->type ?: $this->defaultNamespace;

        // Remove common suffixes like 'Api', 'Backend', 'Frontend' from the last part of the repository class name
        $lastPart = class_basename($class);
        $commonSuffixes = ['Api', 'Backend', 'Frontend', $type];


        foreach ($commonSuffixes as $suffix) {
            $commonSuffixes[] = Str::studly($suffix . $type);
            $commonSuffixes[] = Str::studly($type . $suffix);
        }

        foreach ($commonSuffixes as $suffix) {
            if (Str::contains($lastPart, $suffix)) {
                $lastPart = Str::remove($suffix, $lastPart);
    
            }
        }

        // Combine the segments to get the full model class
        $modelClass = $modelNamespace . '\\' . $lastPart;

        return $modelClass;
        
    }

    /**
     * Parse the namespace and class from a namespaced class.
     *
     * @param string $name The namespaced class name.
     * @param string $type The type of the class (e.g., 'Model', 'Service', etc.).
     *
     * @return array Associative array with 'namespace' and 'class' keys.
     */
    public function parseNamespaceAndClass($name, $type = 'Model') {
        // Case 1: Name parameter contains namespace
        if (strpos($name, '\\') !== false) {
            preg_match('/(.*)\\\\([a-zA-Z]+)$/', $name, $matches);
            return [
                'namespace' => $matches[1] ?? '',
                'class' => $matches[2] ?? ''
            ];
        }

        // Case 2: Name parameter contains only class name
        $normalizedType = $this->toPluralizedPascal($type);
        $defaultNamespace = 'App\\' . $normalizedType;

        return [
            'namespace' => $defaultNamespace,
            'class' => $name
        ];
    }

    /**
     * Get the root namespace based on the position of the type of class directory.
     *
     * @param string|null $class
     * @param string|null $type
     * @return string
     */
    private function getRootNamespace($class = null, $type = null)
    {
        $class = $class ?: $this->getNamespacedClass();
        $type = $type ?: $this->type;
        $type = $this->toPluralizedPascal($type);

        // Get the namespace slice based on the type
        $namespaceSlice = $this->namespaceSlice($class, [$type]);

        // If the namespace slice is not empty, return it
        if (!empty($namespaceSlice)) {
            return $namespaceSlice;
        }

        // If not found, return the default root namespace
        return $this->recognizeNamespace($class);
    }

    /**
     * Get the namespace slice based on the given needles.
     *
     * @param string $namespace
     * @param array $needles
     * @return string
     */
    public function namespaceSlice(string $namespace, array $needles)
    {

        $namespace = $this->getNamespaceFromPath($namespace);
        // Extract the segments from the namespace
        $segments = explode('\\', $namespace);

        // Check if the namespace contains any of the given needles
        foreach ($needles as $needle) {
            if (in_array($needle, $segments)) {
                // Return the namespace up to the needle
                return implode('\\', array_slice($segments, 0, array_search($needle, $segments, true)));
            }
        }

        // Return an empty string if none of the needles are found
        return '';
    }

    /**
     * Get the base name of the repository class.
     *
     * @return string
     */
    public function getClassBaseName()
    {
        $class = $this->getClass();

        // Extract the class name from the input
        $className = class_basename($class);

        // Convert the class name to snake_case
        $className = Str::snake($className);

        // Split the snake_case string and remove the last part
        $parts = explode('_', $className);
        
        // Remove the last part from the array
        if(count($parts) > 1) {
            array_pop($parts);
            // Join the remaining parts and Convert the class name to PascalCase
            $className = implode('_', $parts);
        }

        $className = Str::studly($className);
        // return the result
        return $className;
    }

    /**
     * Get the full path of the class file.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getChoices()['path'];
    }

    /**
     * Get namespace
     *
     * @return string
     */
    private function getNamespace()
    {
        return $this->getNamespaceFromPath($this->getPath());
    }

    
    /**
     * Get the pluralized pascal name.
     * @param $path
     *
     * @return string
     */
    public function toPluralizedPascal($name)
    {
        return Str::studly(Pluralizer::plural(Str::lower($name)));
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
        return str_replace('/', '\\', $path);
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

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
            "name" => "What should the it be named (e.g., {$this->defaultClass}) ?",
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
                $fullPath = str_replace('\\', '/', $namespace);
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

        $this->line("<info>Created $classBaseName  interface:</info> {$namespace}\\{$class}");

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
     * Get the base name of the repository class.
     *
     * @return string
     */
    public function getClassBaseName()
    {
        $class = $this->getChoices()['class'];

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
     * Determine the model namespace based on the provided namespace.
     *
     * @return string The determined model namespace.
     */
    function getModelNamespace()
    {

        $namespace = $this->getNamespace();
        // Split the namespace by backslash ('\') into an array of segments
        $segments = explode('\\', $namespace);

        // Check if the first segment is 'App\Repositories'
        if (reset($segments) === $this->defaultNamespace) {
            // If it is, return 'App\Models' as the model namespace
            return 'App\Models';
        }

        // If not, remove the last segment to get the model namespace
        array_pop($segments);

        // Reconstruct the model namespace by joining the segments with backslash and adding '\Models'
        return implode('\\', $segments) . '\\Models';
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
        return str_replace('/', '\\', $this->getPath());
    }

}

<?php

namespace LaravelSimpleModule\Commands;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelSimpleModule\CreateFile;
use LaravelSimpleModule\Helpers\Change;
use LaravelSimpleModule\Constants\CommandType;
use LaravelSimpleModule\Helpers\AsyncCommand;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

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
            
            $this->printInfo($class, $this->type, $namespace);

            return $namespacedClass;

        } else { 
            $this->handleAvailability($namespacedClass);
        }
    }

    /**
     * Print information about a class creation.
     *
     * @param string $class The class name.
     * @param string|null $namespace The namespace of the class.
     * @param string|null $type The type of the class.
     *
     * @return void
     */
    protected function printInfo($class, $type = null, $namespace = null)
    {
        $type = $type ?: $this->type;
        $className = class_basename($class);
        $namespacedClass = $namespace ? $namespace . "\\" . $className : $this->getQualifiedClass($class, $type);

        $info = "<fg=yellow>{$type} <fg=green>{$className}</> [{$namespacedClass}]";
        $path = $this->getPath($namespacedClass);
        $this->components->info(sprintf('%s [%s] created successfully.', $info, $path));
    }

    /**
     * Execute commands asynchronously or synchronously with the given options.
     *
     * @param array $commands The list of commands with options.
     * @param callable|null $finalCallback Final callback to be invoked when all processes are completed.
     * @param callable|null $callback Callback function to be invoked.
     *
     * @return array An array of results from each command.
     */
    protected function exec($commands, $finalCallback = null, $callback = null)
    {
        $numberOfProcesses = count($commands);
        $processes = [];
        $classes = [];

        foreach ($commands as $key => $command) {
            $arguments = $this->toSymfonyArgument($command);
            $type = $this->getType($command);
            $class = $this->getClassFromArgument($command);
            $classes[$key] = $class;

            // Start the process asynchronously
            $process = new Process($arguments); 
            // $process->setTty(true); // TTY mode is not supported on Windows platform.
            $process->start();
            $processes[$key] = $process;

            // Invoke the callback with the current command, class, and initial result
            if ($callback instanceof \Closure) {
                $initialResult = null; // Set your initial result here
                $callback($class, $type, $initialResult);
            }
        }

        $timeout = 600; // Set a reasonable timeout in seconds
        $startTime = time();
        $results = [
            'success' => true, // Assume success initially
            'results' => []
        ];

        $success = true;

        while (count($processes) > 0) {
            foreach ($processes as $key => $process) {
                // Check if the process is still running
                if (!$process->isRunning()) {
                    // specific process has finished, so we remove it
                    unset($processes[$key]);

                    // Check if the associated class file is created
                    $class = $classes[$key];
                    // if (true || $this->isClassFileCreated($class)) 
                    { // Fix $class array unset issue using another process
                        // echo "Removed: $class\n";
                        unset($classes[$key]);
                    }

                    // Store the result of the finished process
                    // $results[$class] = $process->getExitCode();
                    $exitCode = $process->getExitCode();

                    $results['results'][] = [
                        'success' => $exitCode === 0, // Check if exitCode is 0
                        'status' => $process->getStatus(),
                        'exitCode' => $exitCode,
                        'commandline' => $process->getCommandline(),
                        'startTime' => $process->getStartTime(),
                        'lastOutputTime' => $process->getLastOutputTime(),
                    ];

                    // If any process has a non-zero exitCode, set success to false
                    if ($exitCode !== 0) {
                        $success = false;
                    }
                    echo $process->getOutput();
                }
            }

            // Check if the total elapsed time exceeds the timeout
            if (time() - $startTime > $timeout) {
                echo "Timeout reached. Remaining classes: " . implode(', ', $classes) . "\n";

                // Terminate any remaining processes
                foreach ($processes as $process) {
                    $process->stop(0);
                }

                // Invoke the final callback after all processes are executed with the accumulated results
                if ($finalCallback instanceof \Closure) {
                    $finalCallback($results);
                }

                $results['success'] = false;
                // Return the results collected so far
                return $results;
            }

            // Sleep for a short interval before checking again
            usleep(10000); // sleep for 10 milliseconds
            // print_r($classes);
        }

        // Wait for the processes to finish
        foreach ($processes as $process) {
            $process->wait();
        }

        $results['success'] = $success;

        // Invoke the final callback after all processes are executed with the accumulated results
        if ($finalCallback instanceof \Closure) {
            $finalCallback($results);
        }

        // Return the results
        return $results;
    }


    /**
     * Check if a class file is created.
     *
     * @param string $class The class name.
     *
     * @return bool Whether the class file is created.
     */
    protected function isClassFileCreated($class)
    {
        $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $classPath = app_path($classPath . '.php');

        clearstatcache(true, $classPath);

        return file_exists($classPath) && is_readable($classPath);
    }


    /**
     * Asynchronously call Artisan command and wait for the class file to be created.
     *
     * @param string $command The Artisan command.
     * @param array $arguments The arguments for the command.
     *
     * @return void
     */
    protected function asyncCall($command, $arguments) 
    {
        // Execute the command using Artisan::call
        $this->call($command, $arguments);
        $className = $this->getClassFromArgument([$command, $arguments]);
        // Wait for the class file to be created
        $this->waitUntilExists($className, 60); // Adjust the timeout as needed
    }

    /**
     * Get class from Artisan argument.
     *
     * @param array $parameter The Artisan argument array.
     *
     * @return string|null The class name or null if not found.
     */
    protected function getClassFromArgument($parameter)
    {
        $arguments = $this->toArtisanArgument($parameter);
        $type = $this->getType($arguments);
        $class = is_array(end($arguments)) ? head(end($arguments)) : null;
        return $this->getQualifiedClass($class, $type);
    }

    /**
     * Wait for a class file to exist.
     *
     * @param string $class The class name.
     * @param int $initialTimeout Initial timeout in seconds.
     *
     * @return bool Whether the class file exists.
     * @throws \RuntimeException if the timeout is reached.
     */
    protected function waitUntilExists($class, $initialTimeout = 60)
    {
        while ($elapsedTime <= $initialTimeout) {
            usleep(10000); // sleep for 10 milliseconds before checking again

            if ($this->classFileExists($class)) {
                // The following code will be executed if the file is created within the timeout
                $this->printInfo($class);
                // return true; // Return true once the file is created
            }
        }

        // If the loop completes without returning, it means the timeout is reached
        $adjustedTimeout = max($initialTimeout, $elapsedTime + 5); // Adjust by 5 seconds
        throw new \RuntimeException("Timeout waiting for class file: $class (Adjusted timeout: $adjustedTimeout seconds)");

    }

    /**
     * Check if a class file exists.
     *
     * @param string $class The class name.
     *
     * @return bool Whether the class file exists.
     */
    protected function classFileExists($class)
    {
        $exists = interface_exists($class) || trait_exists($class) || class_exists($class);

        return $exists;
    }

    /**
     * Execute commands asynchronously with the given options.
     *
     * @param array        $commands    The list of commands with options.
     * @param string|null  $commandType The type of command to execute (CommandType::ARTISAN, CommandType::SYMFONY, etc.).
     *
     * @return void
     */
    protected function asyncRun($commands, $commandType = null)
    {
        // Create an instance of AsyncCommand with the provided commands
        $asyncCommand = new AsyncCommand($commands);

        // Run the commands asynchronously with the specified command type
        return $asyncCommand->run($commandType);

    }


    /**
     * Create model traits
     *
     * @return void
     */
    protected function createModelTraits()
    {
        $traitCommands = $this->getModelTraitCommands(CommandType::SYMFONY);
        return $this->asyncRun($traitCommands);
        // return $this->exec($traitCommands/*, function ($results) {
        //     return $results;
        // }*/);
    } 
    
    /**
     * Get model trait commands.
     *
     * @param string|null  $commandType  The type of command (CommandType::ARTISAN, CommandType::SYMFONY, CommandType::SHELL, etc.).
     *
     * @return array|string[]
     */
    protected function getModelTraitCommands($commandType = CommandType::SYMFONY)
    {
        $model = $this->parseModelNamespaceAndClass($this->option("path")); //TODO: Fix empty path issue

        $namespace = $model['namespace'];
        $class = $model['class'];

        if ($this->type !== 'Model') {
            $class = $this->removeLast($class, [$this->type]);
        }

        $modelTraits = ['Attribute', 'Method', 'Relationship', 'Scope'];

        $commands = [];

        foreach ($modelTraits as $traitType) {
            $traitClass = "{$namespace}\\Traits\\{$traitType}\\{$class}{$traitType}";

            $commandOptions = array_filter([
                'name' => $traitClass,
                '--force' => $this->isAvailable($traitClass) ? true : false,
            ]);

            $command = $this->toCommandArgument([$this->getCommand('trait'), $commandOptions], $commandType);
            array_push($commands, $command);
        }

        return $commands;
    }

    /**
     * Convert the given parameter to command arguments based on the command type.
     *
     * @param array|string $parameter    The command parameter.
     * @param string|null  $commandType  The type of command (CommandType::ARTISAN, CommandType::SYMFONY, CommandType::SHELL, etc.).
     *
     * @return array|null An associative array containing the command and its arguments, or null if not a recognized command type.
     */
    protected function toCommandArgument($parameter, $commandType = null)
    {

        $commandType = $commandType ?? $this->determineCommandType($parameter);

        switch ($commandType) {
            case CommandType::ARTISAN:
                return $this->toArtisanArgument($parameter);
            
            case CommandType::SYMFONY:
                return $this->toSymfonyArgument($parameter);
            
            case CommandType::SHELL:
                return $this->toShellArgument($parameter);
            
            default:
                // Handle other command types if needed
                return $this->toArtisanArgument($parameter);
        }
    }

    /**
     * Determine the type of command.
     *
     * @param string|array $command The command to be executed.
     *
     * @return string The command type.
     */
    protected function determineCommandType($command)
    {
        if ($this->isArtisanArgument($command)) {
            return CommandType::ARTISAN;
        } elseif ($this->isSymfonyArgument($command)) {
            return CommandType::SYMFONY;
        } elseif ($this->isShellArgument($command)) {
            return CommandType::SHELL;
        }

        // Return a default type or handle other cases if needed
        return CommandType::ARTISAN;
    }

    /**
     * Extract the Artisan command and its arguments from the given parameter.
     *
     * @param array $parameter The command parameter.
     * @return array|null An associative array containing the command and its arguments, or null if not an Artisan command.
     */
    protected function toArtisanArgument($parameter)
    {
        if ($this->isArtisanArgument($parameter)) {
            $command = $parameter[count($parameter) - 2];
            $arguments = end($parameter);

            // Return the result as an associative array
            return [$command, $arguments];
        }

        if ($this->isSymfonyArgument($parameter)) {
            $parameter = $this->unflattenArguments($parameter);
            return $this->toArtisanArgument($parameter);
        }

        if ($this->isShellArgument($parameter)) {
            $parameter = explode(' ', Str::squish($parameter));
            return $this->toArtisanArgument($parameter);
        }

        // Return null if not an Artisan command
        return null;
    }

    /**
     * Extract the Symfony command and its arguments from the given parameter.
     *
     * @param array $parameter The command parameter.
     * @return array|null An associative array containing the command and its arguments, or null if not an Symfony command.
     */
    protected function toSymfonyArgument($parameter)
    {
        if ($this->isArtisanArgument($parameter)) {
            $parameter = $this->flattenArguments($parameter);
            return $this->toSymfonyArgument($parameter);
        }

        if ($this->isSymfonyArgument($parameter)) {
            return $this->qualifyArtisanPrefix($parameter);
        }

        if ($this->isShellArgument($parameter)) {
            $parameter = explode(' ', Str::squish($parameter));
            return $this->toSymfonyArgument($parameter);
        }

        // Return null if not an Artisan command
        return null;
    }
    
    /**
     * Extract the Shell command and its arguments from the given parameter.
     *
     * @param array $parameter The command parameter.
     * @return string|null An associative array containing the command and its arguments, or null if not an Shell command.
     */
    protected function toShellArgument($parameter)
    {
        if ($this->isArtisanArgument($parameter)) {
            $parameter = implode(' ', $this->flattenArguments($parameter));
            return $this->toShellArgument($parameter);
        }

        if ($this->isSymfonyArgument($parameter)) {
            $parameter = implode(' ', $parameter);
            return $this->toShellArgument($parameter);
        }

        if ($this->isShellArgument($parameter)) {
            $parameter = Str::squish($parameter);
            return $this->qualifyArtisanPrefix($parameter);
        }

        // Return null if not an Artisan command
        return null;
    }
    
    /**
     * Ensure that the "php artisan" part is present in the command.
     *
     * @param array $parameter The parameter array.
     *
     * @return array The modified parameter array with "php artisan" included.
     */
    protected function qualifyArtisanPrefix($parameter)
    {
        $parameter = $this->removeArtisanPrefix($parameter);
        $parameter = $this->firstOrPrepend($parameter, [base_path('artisan'), 'artisan'], base_path('artisan'));
        $parameter = $this->firstOrPrepend($parameter, [PHP_BINARY, 'php'], PHP_BINARY);
        return $parameter;
    }

    /**
     * Remove "php artisan" if it exists from the input array or string.
     *
     * @param array|string $parameter The input array or string.
     *
     * @return array|string The modified array or string.
     */
    protected function removeArtisanPrefix($parameter)
    {
        // Convert string to array if needed
        $array = is_string($parameter) ? explode(' ', $parameter) : $parameter;

        // Remove "php artisan" if it exists
        $array = array_diff($array, [PHP_BINARY, 'php', base_path('artisan'), 'artisan']);

        // Return the modified array or string
        return is_string($parameter) ? implode(' ', $array) : array_values($array);
    }

    /**
     * Convert the provided options into a process command, either flattened arguments or a command string.
     *
     * @param array|string $options        The options for the command.
     * @param string|null  $type           The type of instance (e.g., model, service, repository, etc.).
     * @param bool|string  $isFlatten      Whether to flatten the arguments or create a command string.
     *                                     If a string is provided, it determines the command string type.
     * @param bool         $isArtisanCommand Whether the command is an Artisan command (default: true).
     *
     * @return array|string The process command, flattened arguments, or command string.
     */
    protected function toProcessCommand($options, $type = null, $isFlatten = true, $isArtisanCommand = true)
    {
        $command = $this->addCommandArgument($options, $type, $isArtisanCommand);

        return is_bool($isFlatten) && $isFlatten
            ? $this->flattenArguments($command)
            : (is_string($isFlatten) ? $this->toCommandString($command, $isArtisanCommand) : $command);
    }

    /**
     * Call an Artisan command.
     *
     * @param string|array $command The Artisan command to be executed.
     */
    protected function callArtisanCommand($command)
    {
        $arguments = $this->toArtisanArgument($command);

        // Artisan command
        $exitCode = Artisan::call($arguments[0], $arguments[1]);

        // Output the result
        echo Artisan::output();
    }

    /**
     * Call a Symfony Process command.
     *
     * @param string|array $command The Symfony Process command to be executed.
     */
    protected function callSymfonyCommand($command)
    {
        $arguments = $this->toSymfonyArgument($command);

        // Symfony Process command
        $process = new Process($arguments);

        // Start the process asynchronously
        $process->start();

        // Wait for the process to finish
        $process->wait();

        echo $process->getOutput();
    }

    /**
     * Call a Shell command.
     *
     * @param string|array $command The Shell command to be executed.
     */
    protected function callShellCommand($command)
    {
        $arguments = $this->toShellArgument($command);
        // Symfony Process command
        $process = Process::fromShellCommandline($arguments);

        // Start the process asynchronously
        $process->start();

        // Wait for the process to finish
        $process->wait();

        // Output the result
        echo $process->getOutput();
    }
    /**
     * Add command arguments based on the type and return the complete command array.
     *
     * @param array|string $options        The options for the command.
     * @param string|null  $type           The type of instance (e.g., model, service, repository, etc.).
     * @param bool         $isArtisanCommand Whether the command is an Artisan command (default: true).
     *
     * @return array The complete command array.
     */
    protected function addCommandArgument($options, $type = null, $isArtisanCommand = true)
    {
        $command = $this->getCommand($type);

        // If it's an Artisan command and PHP_BINARY and artisan are not already present, add them
        if ($isArtisanCommand/* && !in_array(PHP_BINARY, $options) && !in_array(base_path('artisan'), $options)*/) {
            $parameter = is_array($options) ? [$command, $options] : [$command, ...$options];
            return $this->qualifyArtisanPrefix($parameter);
            // $options = [PHP_BINARY, base_path('artisan'), ...(is_array($options) ? [$command, $options] : [$command, ...$options])];
        } else {
            $options = is_array($options) ? [$command, $options] : [$command, ...$options];
        }

        return $options;

        // return $isArtisanCommand
        //     ? [PHP_BINARY, base_path('artisan'), ...(is_array($options) ? [$command, $options] : [$command, ...$options])]
        //     : (is_array($options) ? [$command, $options] : [$command, ...$options]);
    }

    /**
     * Find matching items in an array and prepend another item if no matches are found.
     *
     * @param array|string $array      The input array or string.
     * @param array        $search     The array of items to search for.
     * @param string       $item       The item to prepend if no matches are found.
     *
     * @return array|string The modified array or string.
     */
    protected function firstOrPrepend($parameter, $search, $item)
    {
        // Convert string to array if needed
        $array = is_string($parameter) ? [$parameter] : $parameter;

        // Check if any item in the search array exists in the input array
        if (count(array_filter($array, 'is_string')) > 0 && count(array_intersect($search, array_filter($array, 'is_string'))) > 0) {
            // Matching item found, return the original array or string
            return is_string($parameter) ? $parameter : array_values($array);
        } else {
            // Prepend the new item to the array
            array_unshift($array, $item);

            // Return the modified array or string
            return is_string($parameter) ? implode(' ', $array) : array_values($array);
        }
    }

    /**
     * Find a value in an array and split the array based on its position.
     *
     * @param array  $array The input array.
     * @param string $value The value to find in the array.
     *
     * @return array|null The split array or null if the value is not found.
     */
    protected function splitArrayByValue($array, $value)
    {
        $key = array_search($value, $array);

        if ($key !== false) {
            $before = array_slice($array, 0, $key);
            $after = array_slice($array, $key + 1);

            return [$before, $array[$key], $after];
        }

        return null;
    }

    /**
     * Find the position of the command in the array and slice it accordingly.
     *
     * @param array $parameter The array of command parameter.
     *
     * @return array|null The sliced array or null if the command position is not found.
     */
    protected function unflattenArguments($parameter)
    {
        $command = $this->getCommand($parameter);
        if (is_array($parameter) && $command !== null) {
            $arguments = array_splice($parameter, array_search($command, $parameter) + 1);
        } else {
            $arguments = $parameter;
        }

        // Convert the remaining arguments to an associative array
        $options = [];
        
        foreach ($arguments as $key => $argument) {
            // Split each argument into option and value (if applicable)
            list($option, $value) = explode('=', $argument, 2) + [null, true];

            // Set the name argument
            if ($key == 0) {
                $value = $option;
                $option = 'name';
            }

            // If no value is provided, assume it's a boolean option
            $options[$option] = $value;
        }

        // Return the sliced array with the command and options
        return isset($command) ? [...$parameter, $options] : [$options];
    }


    /**
     * Flatten the command arguments to an array.
     *
     * @param array $arguments Command arguments.
     *
     * @return array Flattened options.
     */
    protected function flattenArguments($arguments)
    {
        // Initialize an array to store formatted options
        $options = [];
        // Recursive function to flatten nested arrays
        $flatten = function ($argument) use (&$options, &$flatten) {
            if (is_array($argument)) {
                foreach ($argument as $option => $value) {
                    // If the value is true, include only the option without a value
                    if ($value === true) {
                        $options[] = $option;
                    } else {
                        // If the value is an array, recursively flatten it
                        if (is_array($value)) {
                            $flatten($value);
                        } else {
                            // If the option has a non-boolean value, include both the option and its value
                            if($option == 'name' || is_int($option)) {
                               $options[] = $value;
                            } else {
                               $options[] = "$option=$value";
                            }
                        }
                    }
                }
            }
        };

        // Call the recursive function with the initial arguments
        $flatten($arguments);

        return $options;
    }

    /**
     * Find the position of the command in the array and return the name argument command. Get the command name for a specific type of instance (e.g., model, service, repository, etc.).
     *
     * @param array|string|null $arguments The type of instance or command arguments.
     *
     * @return string The command name.
     */
    protected function getCommand($parameter = null)
    {
        if(is_array($parameter)) {
            foreach ($parameter as $key => $argument) {
                if ($this->isArtisanCommand($argument)) {
                    // Store the result of array_slice() in a variable
                    $slicedArray = array_slice($parameter, $key);

                    // Found the command, extract the name argument command
                    return array_shift($slicedArray);
                }
            }
            return null;
        }

        // Use the provided $type or fallback to the default type and format the type name for display.
        $type = $this->toLowerSingular($parameter ?: $this->type);

        return "make:{$type}";
    }

    /**
     * Format the given command array into a command string.
     *
     * @param array $arguments An array representing the command and its options.
     *                       Example: ["make:controller", ["name" => "DummyController", "--model" => "DummyModel"]]
     * @param bool  $isArtisanCommand Add prefix for artisan command.
     *
     * @return string The formatted command string.
     */
    private function toCommandString($arguments, $isArtisanCommand = false)
    {
        // Build the base command string with 'php artisan' and the command name
        // $argumentString = $arguments[0] . ' ';

        // Initialize an array to store formatted options
        $options = $this->flattenArguments($arguments);

        // Concatenate the options and append them to the command string
        $argumentString = implode(' ', $options);

        if ($isArtisanCommand) {
            $argumentString = PHP_BINARY . ' ' . base_path('artisan') . ' ' . $argumentString;
        }

        // Return the formatted command string
        return $argumentString;
    }

    /**
     * Convert the output command string to an array representation.
     *
     * @param string $output The output command string.
     *
     * @return array The array representation of the command.
     */
    private function parseCommandString($output)
    {
        // Split the command string into parts
        $parts = explode(' ', $output);

        // Extract the command name (first part)
        $commandName = array_shift($parts);

        // Initialize an array to store options
        $options = [];

        // Iterate through the remaining parts to extract options and their values
        foreach ($parts as $part) {
            // Split each part into option and value (if applicable)
            list($option, $value) = explode('=', $part, 2) + [null, null];

            // If no value is provided, assume it's a boolean option
            $value = ($value === null) ? true : $value;

            // Add the option and its value (if applicable) to the options array
            $options[$option] = $value;
        }

        // Return the array representation of the command
        return [$commandName, $options];
    }

    /**
     * Check if the parameters match the Symfony Process format (flattened array).
     *
     * @param array|string $parameters The command parameters.
     * @return bool True if it's a Symfony Process, false otherwise.
     */
    protected function isSymfonyArgument($parameters)
    {
        // Check if the parameters match the Symfony Process format (flattened array)
        return is_array($parameters) && !is_array(end($parameters));
    }
    
    /**
     * Check if the parameter match the Artisan Command format.
     *
     * @param array $parameter The command parameter.
     * @return bool True if it's an Artisan Command, false otherwise.
     */
    protected function isArtisanArgument($parameter)
    {
        return (
            is_array($parameter) &&
            count($parameter) >= 2 &&
            $this->isArtisanCommand($parameter[count($parameter) - 2]) &&
            is_array(end($parameter))
        );
    }

    /**
     * Check if a command exists in the registered Artisan commands.
     *
     * @param string $command The command name to check.
     * @return bool True if the command exists, false otherwise.
     */
    protected function isArtisanCommand($command)
    {
        return is_string($command) && array_key_exists($command, Artisan::all());
    }
    /**
     * Check if the parameters match the Shell Command Line format.
     *
     * @param string $parameters The command parameters.
     * @return bool True if it's a Shell Command Line, false otherwise.
     */
    protected function isShellArgument($parameters)
    {
        // Check if the parameters match the Shell Command Line format
        return is_string($parameters);
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
     * Get the qualified instance.
     *
     * @param string $name
     * @param string|null $type The target type for conversion (e.g., 'Model', 'Service', etc.).
     * @return string The converted class.
     */
    protected function qualifyInstance($name, $type = null)
    {
        // When option is not provided
        if(is_bool($name) && !$name) {
            return $name;
        }

        $normalizedType = $this->toPascalSingular($type ?: $name);
        $class = $this->getClassBaseName($normalizedType);
        $class = $this->removeLast($class, ['Repository', 'Service']);
        // $class = class_basename($this->getModelClass());
        $namespace = $this->getQualifiedNamespace($normalizedType);
        $suffix = $this->getSuffix($normalizedType);
        $class .= $suffix;

        if ($normalizedType == 'Model') {
            $class = $this->removeLast($class, ['Api', 'Backend', 'Frontend', 'Model']);
        }
        
        // When option is expected, but name is not provided
        if(is_null($name)) {
            $name = "{$namespace}\\{$class}";
        }

        // When option is expected, but name is provided
        if(is_string($name)) {
            $name = $this->getQualifiedClass($name, $normalizedType);
        }


        return $name;

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

            $this->printInfo($interface, $this->type . ' Interface', $namespacedInterface);

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
     * Get type from Artisan argument.
     *
     * @param array $argument The Artisan argument array.
     *
     * @return string|null The type of argument (option, argument, or command) or null if not recognized.
     */
    protected function getType($parameter = null) : string
    {
        if(is_array($parameter)) {
            $arguments = $this->toArtisanArgument($parameter);
            // Assuming the class name is the first argument
            $typeArgument = head($arguments);
            $classArgument = end($arguments);
            $type = null;
            if(is_array($classArgument) && is_string($typeArgument) && Str::contains($typeArgument, ':')) {
                $commandArg = explode(':', $typeArgument);
                $type = $this->toPascalSingular(end($commandArg));
            }
        }

        return is_string($parameter) ? $this->toPascalSingular($parameter ?: $this->type) : ($type ?? $this->type);
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
     * @param string|null $class
     * @return string
     */
    protected function getClassBaseName($type = null, $class = null)
    {
        $suffix = $this->getSuffix($type ?: $this->type);
        $class = $class ?? $this->getClass();

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

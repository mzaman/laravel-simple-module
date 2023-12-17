<?php

namespace LaravelSimpleModule\Helpers;

use Spatie\Async\Pool;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use LaravelSimpleModule\Commands\SharedMethods;
use LaravelSimpleModule\Constants\CommandType;

class AsyncCommand
{
    use SharedMethods;

    /**
     * @var array List of commands to be executed asynchronously
     */
    protected $commands;

    /**
     * AsyncCommand constructor.
     *
     * @param array $commands List of commands to be executed asynchronously
     */
    public function __construct(array $commands)
    {
        $this->commands = $commands;
    }

    /**
     * Call the commands asynchronously using Spatie\Async\Pool.
     *
     * @param string|null $commandType The type of command to execute (CommandType::ARTISAN, CommandType::SYMFONY, etc.).
     * @param callable|null $finalCallback Final callback to be invoked when all processes are completed.
     * @param callable|null $callback Callback function to be invoked.
     *
     * @return array An array of results from each command.
     */
    public function run($commandType = null, $finalCallback = null, $callback = null)
    {
        $pool = Pool::create();
        $processes = [];
        $results = [
            'success' => true, // Assume success initially
            'results' => [],
        ];
        // $success = true;
        
        foreach ($this->commands as $command) {
            $pool->add(function () use ($command, $commandType, $callback) {
                $commandType = $commandType ?? $this->determineCommandType($command);
                $arguments = $this->getCommandArguments($command, $commandType);

                // Start the process asynchronously
                $process = new Process($arguments);
                $process->start();

                // Invoke the callback with the current command, class, and initial result
                if ($callback instanceof \Closure) {
                    $initialResult = null; // Set your initial result here
                    $callback($this->getClassFromArgument($command), $this->getType($command), $initialResult);
                }

                // Wait for the process to finish
                $process->wait();

                $exitCode = $process->getExitCode();

                // If any process has a non-zero exitCode, set success to false
                if ($exitCode !== 0) {
                    $success = false;
                }

                echo $process->getOutput();

                return [
                    'success' => $exitCode === 0, // Check if exitCode is 0
                    'status' => $process->getStatus(),
                    'exitCode' => $exitCode,
                    'commandline' => $process->getCommandline(),
                    'startTime' => $process->getStartTime(),
                    'lastOutputTime' => $process->getLastOutputTime(),
                ];
            })
            ->then(function ($output) use (&$processes, &$results) {
                // On success, `$output` is returned by the process or callable you passed to the queue.
                $results['results'][] = $output;

                // Execute after all processes are done with their task
            })
            ->catch(function ($exception) {
                // When an exception is thrown from within a process, it's caught and passed here.
            })
            ->timeout(function () {
                // A process took too long to finish.
            });
        }

        // Wait for all processes to complete
        $pool->wait();

        // $results['success'] = $success;

        // Invoke the final callback after all processes are executed with the accumulated results
        if ($finalCallback instanceof \Closure) {
            $finalCallback($results);
        }

        return $results;
    }

    /**
     * Get command arguments based on the command type.
     *
     * @param string|array $command The command to be executed.
     * @param string|null $commandType The type of command (CommandType::ARTISAN, CommandType::SYMFONY, etc.).
     *
     * @return array The command arguments.
     */
    protected function getCommandArguments($command, $commandType)
    {
        switch ($commandType) {
            case CommandType::ARTISAN:
                return $this->toArtisanArgument($command);
                break;

            case CommandType::SYMFONY:
                return $this->toSymfonyArgument($command);
                break;

            case CommandType::SHELL:
                return $this->toShellArgument($command);
                break;

            // Handle other command types if needed

            default:
                return [];
        }
    }
}

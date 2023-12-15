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
     * AsyncCommandExecutor constructor.
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
     */
    public function run($commandType = null)
    {
        $pool = Pool::create();
        foreach ($this->commands as $command) {
            $pool->add(function () use ($command, $commandType) {
                $commandType = $commandType ?? $this->determineCommandType($command);
                
                switch ($commandType) {
                    case CommandType::ARTISAN:
                        $this->callArtisanCommand($command);
                        break;

                    case CommandType::SYMFONY:
                        $this->callSymfonyCommand($command);
                        break;

                    case CommandType::SHELL:
                        $this->callShellCommand($command);
                        break;

                    default:
                        // Handle other command types if needed
                        break;
                }
            })
            ->then(function ($output) {
                // On success, `$output` is returned by the process or callable you passed to the queue.
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

        // Output the result
        while ($process->isRunning()) {
            $pid = $process->getPid();
            echo "waiting for process $pid to finish...";
        }

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
        while ($process->isRunning()) {
            $pid = $process->getPid();
            echo "waiting for process $pid to finish...";
        }

        // Output the result
        echo $process->getOutput();
    }
}

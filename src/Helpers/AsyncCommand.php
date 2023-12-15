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
            $pool->add(function () use ($command) {
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
            });
        }

        // Wait for all processes to complete
        $pool->wait();
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
     * Call an Artisan command.
     *
     * @param string|array $command The Artisan command to be executed.
     */
    protected function callArtisanCommand($command)
    {
        $arguments = $this->toArtisanArgument($command);
        // Artisan command
        $exitCode = Artisan::call($arguments);

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
            $this->info("waiting for process $pid to finish...");
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
            $this->info("waiting for process $pid to finish...");
        }

        // Output the result
        echo $process->getOutput();
    }
}

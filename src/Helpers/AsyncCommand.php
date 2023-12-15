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
     * Execute the commands asynchronously using Spatie\Async\Pool.
     */
    public function run()
    {
        $pool = Pool::create();
        foreach ($this->commands as $command) {
            $pool->add(function () use ($command) {
                // Check if the command is an Artisan command or a Symfony Process command
                if($this->isArtisanArgument($command)) {
                    $arguments = $this->toArtisanArgument($command);
                    // Artisan command
                    $exitCode = Artisan::call($arguments);

                    // Output the result
                    echo Artisan::output();

                }

                else if($this->isSymfonyArgument($command)) {
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

                } elseif($this->isShellArgument($command)) {
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
            });
        }

        // Wait for all processes to complete
        $pool->wait();
    }
}

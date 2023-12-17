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
     */
    public function run($commandType = null)
    {
        $pool = Pool::create();
        $numberOfProcess = count($this->commands);
        $processes = [];
        foreach ($this->commands as $key => $command) {
            $pool->add(function () use ($processes, $key, $command, $commandType) {
                $commandType = $commandType ?? $this->determineCommandType($command);
                
                switch ($commandType) {
                    case CommandType::ARTISAN:
                        return $this->callArtisanCommand($command);
                        break;

                    case CommandType::SYMFONY:
                        $arguments = $this->toSymfonyArgument($command);
                        // Symfony Process command
                        $process = new Process($arguments);

                        // Start the process asynchronously
                        $process->start();

                        // Wait for the process to finish
                        $process->wait();
                        $processes[] = $process;
                        // return $this->callSymfonyCommand($command);

                        break;

                    case CommandType::SHELL:
                        $arguments = $this->toShellArgument($command);
                        // Symfony Process command
                        $process = Process::fromShellCommandline($arguments);
                        
                        // Start the process asynchronously
                        $process->start();

                        // Wait for the process to finish
                        $process->wait();
                        $processes[] = $process;
                        // return $this->callShellCommand($command);
                        break;

                    default:
                        // Handle other command types if needed
                        break;
                }
                    $results = [];
                    foreach ($processes as $process) {
                        $results[] = $process->getOutput();
                    }

                    return $results;
            })
            ->then(function ($output) use ($pool, $processes, $numberOfProcess) {
                // On success, `$output` is returned by the process or callable you passed to the queue.
                // print_r([$processes, $numberOfProcess, $output]);
                // while (count($processes)) {
                //     foreach ($processes as $i => $runningProcess) {
                //         // specific process is finished, so we remove it
                //         if (! $runningProcess->isRunning()) {
                //             unset($processes[$i]);
                //             echo $i ."\n";
                //         }
                //         sleep(1);
                //     }
                // }

                // if(count($processes) == 0) {
                //     // $pool->stop();
                // }
                // return count($processes);
                //Executes after all processes are done with their task
                // return $output;
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

}

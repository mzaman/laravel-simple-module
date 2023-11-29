<?php

namespace LaravelSimpleModule;
use LaravelSimpleModule\Commands\MakeInterfaceCommand;
use LaravelSimpleModule\Commands\MakeTraitCommand;
use LaravelSimpleModule\Commands\CreateModelCommand;
use LaravelSimpleModule\Commands\MakeModelCommand;
use LaravelSimpleModule\Commands\MakeControllerCommand;
use LaravelSimpleModule\Commands\MakeRepositoryCommand;
use LaravelSimpleModule\Commands\MakeServiceCommand;
use LaravelSimpleModule\Commands\MakeModuleCommand;
use LaravelSimpleModule\Commands\MakeViewCommand;
// use LaravelSimpleModule\Commands\ModelMakeCommand;
use Spatie\LaravelPackageTools\Exceptions\InvalidPackage;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSimpleModuleServiceProvider extends PackageServiceProvider
{
    public function register()
    {
        $this->registeringPackage();

        $this->package = new Package();

        $this->package->setBasePath($this->getPackageBaseDir());

        $this->configurePackage($this->package);

        if (empty($this->package->name)) {
            throw InvalidPackage::nameIsRequired();
        }

        foreach ($this->package->configFileNames as $configFileName) {
            $this->mergeConfigFrom($this->package->basePath("/../config/{$configFileName}.php"), $configFileName);
        }

        $this->mergeConfigFrom(__DIR__ . "/../config/simple-module-sys.php", "simple-module");

        $this->packageRegistered();

        $this->overrideCommands();

        return $this;
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-simple-module')
            ->hasConfigFile()
            ->hasCommand(MakeInterfaceCommand::class)
            ->hasCommand(MakeTraitCommand::class)
            ->hasCommand(MakeModelCommand::class)
            ->hasCommand(CreateModelCommand::class)
            ->hasCommand(MakeControllerCommand::class)
            ->hasCommand(MakeRepositoryCommand::class)
            ->hasCommand(MakeServiceCommand::class)
            ->hasCommand(MakeViewCommand::class)
            ->hasCommand(MakeModuleCommand::class);
    }

    public function overrideCommands()
    {
        // $this->app->extend('command.model.make', function () {
        //     return app()->make(ModelMakeCommand::class);
        // });
        $this->app->extend('command.model.make', function () {
            return app()->make(MakeModelCommand::class);
        });
        $this->app->extend('command.controller.make', function () {
            return app()->make(MakeControllerCommand::class);
        });
    }
}

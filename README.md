# Simple module, repository pattern for laravel, with services!

With easy repository, you can have the power of the repository pattern, without having to write too much code altogether. The package automatically binds the interfaces to the implementations, all you have to do is change in the configuration which implementation is being used at the moment!

## Requirement

- Minimum PHP ^8.1

## Installation

You can install the package via composer for latest version
```bash
composer require mzaman/laravel-simple-module
```


Publish the config file with (Important):

```bash
php artisan vendor:publish --provider="LaravelSimpleModule\LaravelSimpleModuleServiceProvider" --tag="simple-module-config"
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

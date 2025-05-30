# 🛠️ Laravel Simple Module : Domain-Driven Code Generator

This package provides a set of powerful and flexible Artisan commands to streamline your development. It's especially suited for **DDD (Domain-Driven Design)** or modular architectures in laravel. This simple and customizable package enables you to rapidly scaffold modules with repository-service pattern in a Laravel application using a clean structure that includes Models, Traits, Controllers, Services, Repositories, Events, Listeners, and more — all following your custom directory conventions.

## 🧰 Features

Create modular components with a single command
Automatically generates layered architecture (API, Backend, Frontend)
Supports Events, Listeners, Repositories, Services, Requests, Policies, Views
Minimal configuration needed
Fully customizable module path and namespace

## 📦 Requirements

- Minimum PHP ^8.1
Laravel ^9.0 or ^10.0

## 🚀 Installation

Install via Composer:

You can install the package via composer for latest version
```bash
composer require mzaman/laravel-simple-module
```

Then publish the config file (⚠️ must be published for proper usage):

```bash
php artisan vendor:publish --provider="LaravelSimpleModule\LaravelSimpleModuleServiceProvider" --tag="simple-module-config"
```
This will create a config/simple-module.php file, where you can set default module paths, namespaces, and component settings.

## ⚙️ Configuration (config/simple-module.php)

```php
return [
    "request_directory" => "app/Requests",
    "request_namespace" => "App\Requests",

    "module_directory" => "app/Modules",
    "module_namespace" => "App\Modules",

    "interface_directory" => "app/Interfaces",
    "interface_namespace" => "App\Interfaces",

    "abstract_directory" => "app/Abstracts",
    "abstract_namespace" => "App\Abstracts",

    "model_directory" => "app/Models",
    "model_namespace" => "App\Models",

    "policy_directory" => "app/Policies",
    "policy_namespace" => "App\Policies",

    "repository_directory" => "app/Repositories",
    "repository_namespace" => "App\Repositories",

    "service_directory" => "app/Services",
    "service_namespace" => "App\Services",
];
```

## 🧪 Example Usage

### 🎯 Generate a Complete Module

```bash
php artisan make:module Blog
```

You will be prompted to:
- Select layers (API, Backend, Frontend)
- Enter model names (e.g., `Post, Comment`)
- Choose which components to generate (Models, Events, Listeners, Controllers, Requests, etc.)

The module will be generated at:

```
app/Modules/Blog/
├── Events/
├── Http/
│   └── Controllers/
├── Listeners/
├── Models/
├── Policies/
├── Repositories/
├── Requests/
├── Services/
├── Traits/
└── Views/
```

### ⚙️ Optional Flags

You can customize the generation path or force overwrite existing files:

```bash
php artisan make:module Blog --path=custom/modules --force
```

## 🧱 What It Generates

Given a model `Post` and choosing API layer, you will get:

- `Models/Post.php`
- `Repositories/PostApiRepository.php`
- `Services/PostApiService.php`
- `Http/Controllers/Api/PostApiController.php`
- `Requests/StorePostRequest.php`, `UpdatePostRequest.php`
- `Events/PostCreated.php`, `PostUpdated.php`, etc.
- `Listeners/PostEventListener.php`
- `Migrations`, `Seeders`, `Factories` if selected

## 🔄 Auto-Binding

The package automatically binds:

```php
PostApiRepositoryInterface => PostApiRepository
PostApiServiceInterface    => PostApiService
```

So you can type-hint interfaces in your controllers and services.

## 📘 Example Controller Usage

```
<?php

namespace App\Modules\Blog\Services\Api;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Modules\Blog\Repositories\Api\PostApiRepository;
use \Exception;

/**
 * Class PostApiService.
 * 
 * @extends \App\Services\BaseApiService
 * @implements PostApiServiceInterface
 */
class PostApiService extends \App\Services\BaseApiService implements PostApiServiceInterface { 

    /**
     * Set message api for CRUD
     * @param string $title
     * @param string $create_message
     * @param string $update_message
     * @param string $delete_message
     */
     protected $title = "";
     protected $create_message = "";
     protected $update_message = "";
     protected $delete_message = "";

     /**
     * Don't change $this->repository variable name
     * because used in extends service class
     */
     protected $repository;

    public function __construct(PostApiRepository $repository)
    {
      $this->repository = $repository;
    }

    // Additional methods specific to PostApiService
    // New methods for the Api Service
}
```

## 📁 File Structure

Below is the standard directory structure generated by the command tools:

```
app
 └── Domains
     └── V1
         └── ${ModuleName}
             ├── Events
             │   └── ${ModuleName}
             │       ├── ${ModelName}Created.php
             │       ├── ${ModelName}Deleted.php
             │       └── ${ModelName}Updated.php
             ├── Http
             │   ├── Controllers
             │   │   ├── Api
             │   │   │   └── ${ModuleName}/${ModelName}Controller.php
             │   │   ├── Backend
             │   │   │   └── ${ModuleName}/${ModelName}Controller.php
             │   │   └── Frontend
             │   │       └── ${ModuleName}/${ModelName}Controller.php
             │   ├── Middleware
             │   └── Requests
             │       ├── Backend/${ModuleName}
             │       │   ├── Delete${ModelName}Request.php
             │       │   ├── Edit${ModelName}Request.php
             │       │   ├── Store${ModelName}Request.php
             │       │   └── Update${ModelName}Request.php
             │       └── Frontend/${ModuleName}
             │           └── Get${ModelName}Request.php
             ├── Listeners
             │   └── ${ModelName}EventListener.php
             ├── Models
             │   ├── Traits
             │   │   ├── Attribute/${ModelName}Attribute.php
             │   │   ├── Method/${ModelName}Method.php
             │   │   ├── Relationship/${ModelName}Relationship.php
             │   │   └── Scope/${ModelName}Scope.php
             │   └── ${ModelName}.php
             ├── Notifications
             │   └── Frontend
             │       └── Get${ModuleName}Notification.php
             ├── Observers
             │   └── ${ModelName}Observer.php
             ├── Rules
             │   └── ${ModelName}Rule.php
             ├── Repository
             │   └── ${ModelName}Repository.php
             └── Services
                 └── ${ModelName}Service.php

Modules
 └── BaseModule.php
```

## 📚 Here is an example of the directory structure generated by the Laravel Simple Module package:

```
./
app/ - Laravel application code
├── Domains
│   └── V1
│       └── News
│           ├── Events
│           │   └── Post
│           │       ├── PostCreated.php
│           │       ├── PostDeleted.php
│           │       └── PostUpdated.php
│           ├── Http
│           │   ├── Controllers
│           │   │   └── Api
│           │   │       └── PostApiController.php
│           │   └── Requests
│           │       └── Api
│           │           └── Post
│           │               ├── DeletePostRequest.php
│           │               ├── EditPostRequest.php
│           │               ├── StorePostRequest.php
│           │               └── UpdatePostRequest.php
│           ├── Listeners
│           │   └── PostEventListener.php
│           ├── Models
│           │   ├── Post.php
│           │   └── Traits
│           │       ├── Attribute
│           │       │   └── PostAttribute.php
│           │       ├── Method
│           │       │   └── PostMethod.php
│           │       ├── Relationship
│           │       │   └── PostRelationship.php
│           │       └── Scope
│           │           └── PostScope.php
│           ├── Policies
│           │   └── Post
│           │       └── PostApiPolicy.php
│           ├── Repositories
│           │   └── Api
│           │       ├── PostApiRepository.php
│           │       └── PostApiRepositoryInterface.php
│           └── Services
│               └── Api
│                   ├── PostApiService.php
│                   └── PostApiServiceInterface.php
├── Interfaces
│   └── BaseInterface.php
├── Repositories
│   ├── BaseRepository.php
│   ├── BaseRepositoryInterface.php
└── Services
    ├── BaseApiService.php
    ├── BaseApiServiceInterface.php
    ├── BaseService.php
    └── BaseServiceInterface.php
```

---

## 🚀 Artisan Commands

#### Directory Separator
- On **Windows**, the directory separator is `\`.
- On **macOS** and other Unix-based systems (Linux, etc.), the directory separator is `/`.

To ensure compatibility, always use the appropriate separator for your operating system.

#### 🔧 Service Generator
```bash
php artisan make:service App/Domains/V1/Analytics/Services/ChannelBackendService
```

### 🧱 Model Generator

With optional generators:
```bash
php artisan make:model Channel --path=App/Domains/V1/Analytics/Models --trait --service --repository --requests --policy --force
```

Examples:
```bash
php artisan make:model App/Domains/V1/Test/Models/Dummy --trait --service --repository
php artisan make:model App/Domains/V1/Test/Models/Dummy --all
php artisan make:model App/Domains/V1/Test_a/Models/Dummy --service=DemoService --repository=DemoRepository
```

### 🧬 Trait Generator
```bash
php artisan make:trait App/Domains/V1/Test_a/Models/Traits/Dummy_testApiTrait
```

### 📦 Module Generator
```bash
php artisan make:module App/Domains/V1/Subscription
```

### 🧠 Interface Generator
```bash
php artisan make:interface App/Domains/V1/Test_a/Models/Traits/Dummy_testApi
```

### 📂 View Generator
```bash
php artisan make:view backend/plan/show
```

### 🎮 Controller Generator

```bash
php artisan make:controller App/Domains/V1/Test/Http/Controllers/Api/DummyApiController --requests --repository --service --policy
```

With model:
```bash
php artisan make:controller App/Domains/V1/Test/Http/Controllers/Api/DummyApiController --model=App/Domains/V1/Test/Models/Dummy --api --requests --repository --service --policy
```

---

## 🧩 Flags Reference

| Flag            | Description                                               |
|-----------------|-----------------------------------------------------------|
| `--trait`       | Generate trait structure for Attribute, Scope, etc.       |
| `--service`     | Create a Service class for the model                      |
| `--repository`  | Create a Repository class for the model                   |
| `--requests`    | Generate request classes (Store, Update, etc.)            |
| `--policy`      | Generate a Policy file                                    |
| `--api`         | Generate a REST-style API controller                      |
| `--resource`    | Include resource methods in controller                    |
| `--plain`       | Generate plain class without extra logic                  |
| `--all`         | Generate everything (trait, service, repository, etc.)    |
| `--force`       | Overwrite existing files                                  |
| `--model`       | Specify model class for controller                        |
| `--path`        | Override the default path for file generation             |

---

## 📌 Notes

- All generation paths/namespaces are configurable in `config/simple-module.php`
- Designed to support **modular** and **scalable** Laravel architecture
- Compatible with Laravel's **service-repository** and **policy-driven** design


## 🧪 Testing
```
rm -rf vendor composer.lock && composer install && composer exec -- testbench test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

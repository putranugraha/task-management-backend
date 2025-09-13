<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeRepositoryService extends Command
{
    protected $signature = 'make:reposervice {name}';
    protected $description = 'Generate Repository and Service classes along with their interfaces';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $name = $this->argument('name');
        $this->createRepositoryInterface($name);
        $this->createRepository($name);
        $this->createServiceInterface($name);
        $this->createService($name);
        $this->info('Repository and Service classes created successfully.');
    }

    protected function createRepositoryInterface($name)
    {
        $path = app_path("Repositories/Contracts/{$name}RepositoryInterface.php");
        $stub = $this->getStub('repository_interface');
        $this->generateFile($path, $stub, $name);
    }

    protected function createRepository($name)
    {
        $path = app_path("Repositories/Eloquent/{$name}Repository.php");
        $stub = $this->getStub('repository');
        $this->generateFile($path, $stub, $name);
    }

    protected function createServiceInterface($name)
    {
        $path = app_path("Services/Contracts/{$name}ServiceInterface.php");
        $stub = $this->getStub('service_interface');
        $this->generateFile($path, $stub, $name);
    }

    protected function createService($name)
    {
        $path = app_path("Services/Implementations/{$name}Service.php");
        $stub = $this->getStub('service');
        $this->generateFile($path, $stub, $name);
    }

    protected function getStub($type)
    {
        return file_get_contents(resource_path("stubs/{$type}.stub"));
    }

    protected function generateFile($path, $stub, $name)
    {
        $filesystem = new Filesystem;
        $content = str_replace('{{name}}', $name, $stub);

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!$filesystem->exists($directory)) {
            $filesystem->makeDirectory($directory, 0755, true);
        }

        if (!$filesystem->exists($path)) {
            $filesystem->put($path, $content);
            $this->info("Created: {$path}");
        } else {
            $this->error("File already exists: {$path}");
        }
    }
}
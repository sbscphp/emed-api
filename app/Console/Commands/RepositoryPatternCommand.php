<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RepositoryPatternCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:pattern {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup repository pattern command e.g. php artisan setup:pattern Customer/CustomerInformation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelWithNamespace = $this->argument('model');

        $namespace = $this->getModelNamespace($modelWithNamespace)['namespace'];
        $model = $this->getModelNamespace($modelWithNamespace)['model'];


        $this->createService($namespace, $model);
        $this->createInterface($namespace, $model);
        $this->createRepository($namespace, $model);

        $this->info("Service, Interface, and Repository created for {$model}");

        return 0;
    }

    protected function createService(string $namespace, string $model): void
    {
        $this->createFolder('Services', Str::studly($model));

        $path = app_path('Services/' . Str::studly($model) . '/' . Str::studly($model) . 'Service.php');
        $this->createFile($path, $this->buildServiceStub($namespace, $model));
    }

    protected function createInterface(string $namespace, string $model): void
    {
        $this->createFolder('Repositories', Str::studly($model));

        $path = app_path('Repositories/' . Str::studly($model) . '/' . Str::studly($model) . 'Interface.php');
        $this->createFile($path, $this->buildInterfaceStub($namespace, $model));
    }

    protected function createRepository(string $namespace, string $model): void
    {
        $this->createFolder('Repositories', Str::studly($model));

        $path = app_path('Repositories/' . Str::studly($model) . '/' . Str::studly($model) . 'Repository.php');
        $this->createFile($path, $this->buildRepositoryStub($namespace, $model));
    }

    protected function createFolder(string $folderName, string $model): void
    {
        $path = app_path($folderName . '/' . Str::studly($model));

        if (is_dir($path)) {
            $this->info("Pattern already exists for {$model}");
            return;
        }

        mkdir($path, 0777, true);
        $this->info("Folder created: {$model}");
    }

    protected function createFile(string $path, string $content): void
    {
        if (file_exists($path)) {
            $this->error("File already exists at {$path}");
            return;
        }

        file_put_contents($path, $content);
        $this->info("File created: {$path}");
    }

    protected function buildServiceStub(string $namespace, string $model): string
    {
        $stub = file_get_contents(__DIR__ . '/stubs/service.stub');
        return str_replace(['{{ model }}', '{{ folder }}'], [Str::studly($model), $namespace], $stub);
    }

    protected function buildInterfaceStub(string $namespace, string $model): string
    {
        $stub = file_get_contents(__DIR__ . '/stubs/interface.stub');
        return str_replace(['{{ model }}', '{{ folder }}'], [Str::studly($model), $namespace], $stub);
    }

    protected function buildRepositoryStub(string $namespace, string $model): string
    {
        $stub = file_get_contents(__DIR__ . '/stubs/repository.stub');
        return str_replace(['{{ model }}', '{{ folder }}'], [Str::studly($model), $namespace], $stub);
    }

    protected function getModelNamespace(string $modelWithNamespace): array
    {
        $formattedNamespace = str_replace('/', '\\', $modelWithNamespace);

        $parts = explode('\\', $formattedNamespace);

        $model = array_pop($parts);

        $namespace = implode('\\', $parts);

        return [
            'model' => $model,
            'namespace' => $namespace
        ];
    }
}

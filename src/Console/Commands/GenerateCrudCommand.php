<?php

namespace ViltCrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateCrudCommand extends Command
{
    protected $signature = 'vilt:generate {model}';
    protected $description = 'Generiert ein komplettes CRUD für das angegebene Model';

    public function handle()
    {
        $model = $this->argument('model');
        $modelPath = app_path("Models/{$model}.php");

        if ( ! File::exists($modelPath)) {
            $this->error("Model {$model} existiert nicht.");
            return;
        }

        // Generiere files from Stubs
        $this->generateController($model);
        $this->generateRoutes($model);
        $this->generateVueComponents($model);


        $this->info("CRUD für {$model} wurde erfolgreich generiert!");
    }

    private function getStubPath($stubFile): ?string
    {
        $publishedStubPath = base_path("stubs/vilt-crud-generator/{$this->getStubTemplateName()}/{$stubFile}");
        $packageStubPath = __DIR__ . "/../../Stubs/{$this->getStubTemplateName()}/{$stubFile}";

        if (File::exists($publishedStubPath)) {
            return $publishedStubPath;
        }

        return File::exists($packageStubPath) ? $packageStubPath : null;
    }


    private function getControllerNamespace(): string
    {
        //TODO: subfolder from config
        return 'App\Http\Controllers';
    }


    private function getStubTemplateName(): string
    {
        return config('vilt-crud-generator.stub_template', 'default');
    }


    private function generateController($model): void
    {
        $stubPath = $this->getStubPath('controller.stub');

        if ( ! $stubPath) {
            $this->error("Controller-Stub nicht gefunden.");
            return;
        }

        $controllerPath = app_path("Http/Controllers/{$model}Controller.php");
        File::ensureDirectoryExists(dirname($controllerPath));

        $stub = File::get($stubPath);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ namespace }}'       => $this->getControllerNamespace(),
            '{{ model }}'           => $model,
            '{{ entity }}'          => Str::lower($model),
            '{{ controllerClass }}' => $model . 'Controller'
        ]);

        File::put($controllerPath, $stub);
        $this->info("Controller für {$model} wurde erstellt.");
    }


    private function generateRoutes($model): void
    {
        $stubPath = $this->getStubPath('route.stub');

        if ( ! $stubPath) {
            $this->error("Route-Stub nicht gefunden.");
            return;
        }

        $routeFile = base_path('routes/web.php');
        $routeStub = File::get($stubPath);
        $routeStub = str_replace(array('{{ model }}', '{{ Model }}'), array(Str::lower($model), $model), $routeStub);

        // Überprüfen, ob die Route bereits existiert
        $currentRoutes = File::exists($routeFile) ? File::get($routeFile) : '';
        if (str_contains($currentRoutes, $routeStub)) {
            $this->info("Route für {$model} existiert bereits in `web.php`, wird nicht erneut hinzugefügt.");
            return;
        }

        File::append($routeFile, "\n" . $routeStub);
        $this->info("Route für {$model} wurde zu `web.php` hinzugefügt.");
    }
    private function generateVueComponents($model): void
    {
        $this->generateVueForm($model);
        $this->generateVueIndex($model);
        $this->generateVueShow($model);
    }

    private function generateVueForm($model): void
    {
        $stubPath = $this->getStubPath('vue-form.stub');
        if (!$stubPath) {
            $this->error("Vue-Form-Stub nicht gefunden");
            return;
        }

        $vuePath = resource_path("js/Pages/{$model}/Form.vue");
        File::ensureDirectoryExists(dirname($vuePath));

        $stub = File::get($stubPath);
        $fieldsMarkup = $this->generateVueFormFields($model);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ model }}' => Str::lower($model),
            '{{ Model }}' => $model,
            '{{ layoutName }}' => $this->getLayoutName()
        ]);

        File::put($vuePath, $stub);
        $this->info("Vue-Form-Komponente für {$model} wurde erstellt.");
    }

    private function generateVueIndex($model): void
    {
        $stubPath = $this->getStubPath('vue-index.stub');
        if (!$stubPath) {
            $this->error("Vue-Index-Stub nicht gefunden");
            return;
        }

        $vuePath = resource_path("js/Pages/{$model}/Index.vue");
        File::ensureDirectoryExists(dirname($vuePath));

        $stub = File::get($stubPath);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ model }}' => Str::lower($model),
            '{{ Model }}' => $model,
            '{{ layoutName }}' => $this->getLayoutName()
        ]);

        File::put($vuePath, $stub);
        $this->info("Vue-Index-Komponente für {$model} wurde erstellt.");
    }

    private function generateVueShow($model): void
    {
        $stubPath = $this->getStubPath('vue-show.stub');
        if (!$stubPath) {
            $this->error("Vue-Show-Stub nicht gefunden");
            return;
        }

        $vuePath = resource_path("js/Pages/{$model}/Show.vue");
        File::ensureDirectoryExists(dirname($vuePath));

        $stub = File::get($stubPath);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ model }}' => Str::lower($model),
            '{{ Model }}' => $model,
            '{{ layoutName }}' => $this->getLayoutName()
        ]);

        File::put($vuePath, $stub);
        $this->info("Vue-Show-Komponente für {$model} wurde erstellt.");
    }

    private function setStubPlaceholder($stub, $replacements): string
    {
        foreach ($replacements as $key => $replacement) {
            $stub = str_replace($key, $replacement, $stub);
        }

        return $stub;
    }

    private function getLayoutName(): string
    {
        return config('vilt-crud-generator.layout_name', 'AppLayout');
    }


    private function generateVueFormFields($model): string
    {
        $modelClass = "App\\Models\\{$model}";
        if (!class_exists($modelClass)) {
            $this->error("Model-Klasse {$modelClass} nicht gefunden.");
            return '';
        }

        $fillableFields = (new $modelClass)->getFillable();
        $fieldsMarkup = '';

        foreach ($fillableFields as $field) {
            $fieldsMarkup .= $this->generateVueField($field);
        }

        return $fieldsMarkup;
    }

    private function generateVueField($field): string
    {
        $fieldType = 'text';
        if (Str::contains($field, ['date'])) {
            $fieldType = 'date';
        } elseif (Str::contains($field, ['email'])) {
            $fieldType = 'email';
        } elseif (Str::contains($field, ['password'])) {
            $fieldType = 'password';
        } elseif (Str::contains($field, ['description', 'content', 'text'])) {
            return "<textarea v-model=\"form.{$field}\" placeholder=\"Enter {$field}\"></textarea>\n";
        }

        return "<input type=\"{$fieldType}\" v-model=\"form.{$field}\" placeholder=\"Enter {$field}\" />\n";
    }

}

<?php

namespace Rolukja\ViltCrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCrudCommand extends Command
{
    protected $signature = 'vilt:generate {model}';
    protected $description = 'Generiert ein komplettes CRUD für das angegebene Model';


    protected array $ignoreFields = ['id', 'created_at', 'updated_at'];


    protected array $dbTableSchema = [];


    public function handle(): void
    {


        $model = $this->argument('model');
        $modelPath = app_path("Models/{$model}.php");

        if ( ! File::exists($modelPath)) {
            $this->error("Model {$model} existiert nicht.");
            return;
        }

        $this->dbTableSchema = $this->getFillableFieldsWithTypes($this->argument('model'));

        if (empty($this->dbTableSchema)) {
            $this->error("Table Schema for {$model} not found.");
            return;
        }

        $this->runCommands($model);

    }


    private function runCommands($modelName): void
    {

        try {
            $this->generateController($modelName);
            $this->generateRoutes($modelName);
            $this->generateVueComponents($modelName);
            $this->info("CRUD für {$modelName} wurde erfolgreich erstellt.");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

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
            '{{ Model }}'           => $model,
            '{{ validRules }}'      => $this->getValidationRulesFromDbTableSchema(),
            '{{ model }}'           => Str::lower($model),
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

        $routeStub = $this->setStubPlaceholder($routeStub, [
            '{{ model }}'      => Str::lower($model),
            '{{ Model }}'      => $model,
            '{{ Controller }}' => "App\Http\Controllers\\{$model}Controller",
        ]);

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
        if ( ! $stubPath) {
            $this->error("Vue-Form-Stub nicht gefunden");
            return;
        }

        $vuePath = resource_path("js/Pages/{$model}/Form.vue");
        File::ensureDirectoryExists(dirname($vuePath));

        $stub = File::get($stubPath);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ model }}'      => Str::lower($model),
            '{{ Model }}'      => $model,
            '{{ layoutName }}' => $this->getLayoutName(),
            '{{ fields }}'     => $this->generateVueFormFields($model),
        ]);

        File::put($vuePath, $stub);
        $this->info("Vue-Form-Komponente für {$model} wurde erstellt.");
    }

    private function generateVueIndex($model): void
    {
        $stubPath = $this->getStubPath('vue-index.stub');
        if ( ! $stubPath) {
            $this->error("Vue-Index-Stub nicht gefunden");
            return;
        }

        $vuePath = resource_path("js/Pages/{$model}/Index.vue");
        File::ensureDirectoryExists(dirname($vuePath));

        $stub = File::get($stubPath);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ th }}'         => $this->getHtmlTableHeadFromDbTableSchema(),
            '{{ td }}'         => $this->getHtmlTableDataFromDbTableSchema(),
            '{{ model }}'      => Str::lower($model),
            '{{ Model }}'      => $model,
            '{{ layoutName }}' => $this->getLayoutName()
        ]);

        File::put($vuePath, $stub);
        $this->info("Vue-Index-Komponente für {$model} wurde erstellt.");
    }

    private function generateVueShow($model): void
    {
        $stubPath = $this->getStubPath('vue-show.stub');
        if ( ! $stubPath) {
            $this->error("Vue-Show-Stub nicht gefunden");
            return;
        }

        $vuePath = resource_path("js/Pages/{$model}/Show.vue");
        File::ensureDirectoryExists(dirname($vuePath));

        $stub = File::get($stubPath);

        $stub = $this->setStubPlaceholder($stub, [
            '{{ model }}'      => Str::lower($model),
            '{{ Model }}'      => $model,
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
        $fieldsMarkup = '';
        foreach ($this->getModelObjectByName($model) as $field) {
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

    private function getModelObjectByName($modelName): ?object
    {
        $modelClass = "App\\Models\\{$modelName}";
        if ( ! class_exists($modelClass)) {
            $this->error("Model-Klasse {$modelClass} nicht gefunden.");
            return null;
        }

        return (new $modelClass);
    }


    public function getFillableFieldsWithTypes(string $modelName): array
    {
        $model = $this->getModelObjectByName($modelName);
        $table = $model->getTable();
        $fillableFields = $model->getFillable();
        $fieldsWithTypes = [];

        // Alle Spalten aus der Datenbank abrufen
        $columns = collect(DB::select(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT 
         FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?", [$table]
        ))->unique('COLUMN_NAME'); // Doppelte Spalten verhindern

        foreach ($columns as $column) {

            if($this->ignoreFields && in_array($column->COLUMN_NAME, $this->ignoreFields, true)){
                continue;
            }

            $field = $column->COLUMN_NAME;
            $type = $column->DATA_TYPE;
            $isNullable = $column->IS_NULLABLE === 'YES';
            $isPrimaryKey = $column->COLUMN_KEY === 'PRI';
            $isForeignKey = str_ends_with($field, '_id');
            $maxLength = $column->CHARACTER_MAXIMUM_LENGTH;
            $defaultValue = $column->COLUMN_DEFAULT;

            $fieldData = [
                'field'      => $field,
                'type'       => $isForeignKey ? Str::studly(str_replace('_id', '', $field)) : $type,
                'required'   => ! $isNullable,
                'primary'    => $isPrimaryKey,
                'foreign'    => $isForeignKey,
                'max_length' => $maxLength,
                'default'    => $defaultValue,
            ];

            $fieldsWithTypes[] = $fieldData;
        }

        return $fieldsWithTypes;
    }

    private function getHtmlTableHeadFromDbTableSchema(): string
    {
        $html = '';
        foreach ($this->dbTableSchema as $field) {
            $html .= "<th class='border border-gray-300 p-2'>{$field['field']}</th>\n";
        }

        return $html;
    }

    private function getHtmlTableDataFromDbTableSchema(): string
    {

        $html = '';
        foreach ($this->dbTableSchema as $field) {
            $html .= "<td class='border border-gray-300 p-2'>{{item.".$field['field']."}}</td>\n";
        }

        return $html;
    }


    private function getValidationRulesFromDbTableSchema(): string
    {

        $rules = '';
        foreach ($this->dbTableSchema as $field) {
            if ($field['field'] === 'id' || $field['field'] === 'created_at' || $field['field'] === 'updated_at') {
                continue;
            }

            $rules .= "'{$field['field']}' => ['required'],\n";
        }

        return $rules;
    }

}

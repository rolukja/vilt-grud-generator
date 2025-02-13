<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Rolukja\ViltCrudGenerator\Providers\ViltCrudGeneratorServiceProvider;

uses(TestCase::class)->in(__DIR__);

class GenerateCrudCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ViltCrudGeneratorServiceProvider::class,
        ];
    }

    public function test_command_fails_if_model_does_not_exist(): void
    {
        $this->artisan('vilt:generate', [
            'model' => 'NonExistentModel',
        ])
            ->expectsOutput('Model NonExistentModel existiert nicht.')
            ->assertExitCode(0);
    }

    public function test_command_fails_if_table_schema_is_not_found_with_mocking(): void
    {
        // 1) 'File::exists()' so mocken, dass unsere Model-Datei angeblich vorhanden ist:
        File::shouldReceive('exists')
            ->withArgs(function ($path) {
                return str_contains($path, 'FakeModelWithoutSchema.php');
            })
            ->andReturn(true);

        // 2) DB::select(...) mocken, damit die Spalten-Abfrage leer zurückkommt:
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        // 3) Dummy-Model per eval anlegen,
        //    damit class_exists("App\Models\FakeModelWithoutSchema") = true ist.
        if (! class_exists('App\Models\FakeModelWithoutSchema')) {
            eval('
                namespace App\Models;
                class FakeModelWithoutSchema {
                    public function getTable() { return "some_non_existent_table"; }
                    public function getFillable() { return []; }
                }
            ');
        }

        // 4) Nun den Command ausführen und prüfen,
        //    ob er tatsächlich die Meldung "Table Schema for ..." bringt.
        $this->artisan('vilt:generate', [
            'model' => 'FakeModelWithoutSchema',
        ])
            ->expectsOutput('Table Schema for FakeModelWithoutSchema not found.')
            ->assertExitCode(0);
    }
}

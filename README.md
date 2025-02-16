# VILT CRUD Generator

The **VILT CRUD Generator** is a Laravel package that automatically generates CRUD structures (controllers, routes, Vue components) for your models. It leverages Inertia.js and Vue, saving you from repetitive tasks by setting up common CRUD functionalities for your application.

## Installation

1. Install the package via Composer:

   ```bash
   composer require rolukja/vilt-crud-generator
   ```

2. *(Optional)* Publish the configuration file and stubs:

   ```bash
   php artisan vendor:publish --provider="Rolukja\\ViltCrudGenerator\\Providers\\ViltCrudGeneratorServiceProvider"
   ```

3. Install NPM dependencies:

   ```bash
   npm install vue-multiselect --save
   ```


## Usage

1. Create a model, for example `App\Models\Post`.
2. Run the generator:

   ```bash
   php artisan vilt:generate Post
   ```

   The generator will analyze the model (and its database table) to automatically create:
    - A controller (e.g., `App\Http\Controllers\PostController`)
    - Routes in `routes/web.php`
    - Vue components (Index, Show, Form) in `resources/js/Pages/Post/`

## Features

- Automatically reads and uses database schema information (field types, validations).
- Generates controllers, routes, and Vue files (Form, Index, Show).
- Supports customizable stubs, allowing you to publish and adapt them to your needs.
- Compatible with Laravel 11, Inertia.js, and Vue.

## Testing

This package uses [Pest](https://pestphp.com/) for testing:

```bash
composer test
```

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).


This is a Laravel 9 php project that uses the Laravel framework to create web applications with PHP. Laravel offers an elegant and expressive syntax, as well as many useful features such as the Blade template system, the Eloquent ORM, authentication, validation, routing, etc. This project also uses Docker to facilitate the deployment and configuration of the development and production environment.

## Getting started

### Normal installation

-   [Install PHP](https://www.php.net/manual/es/install.php)
-   [Install Composer](https://getcomposer.org/download/)

Install the necessary dependencies:

```bash
composer install
```

Copy the .env.example file and rename it to .env. Edit the values according to your preferences and needs.

```bash
cp .env.example .env
```

Generate an application key:

```bash
php artisan key:generate
```

Set up the data for the database in the .env file by uncommenting/filling in the variables:

```bash
# Config data base UNIAdaptive
DB_CONNECTION=mysql
DB_HOST= # localhost
DB_PORT= # 3306
DB_DATABASE= # uniadaptative_back
DB_USERNAME= # root
DB_PASSWORD=
```

Run the database migrations:

```bash
php artisan migrate
```

Run the development server:

```bash
php artisan serve
```

Open http://127.0.0.1:8000 with your browser to see the result.

You can start editing the project by modifying the files in the `app` folder.

### Docker

You can build your own docker image tailored to your needs.

To create an image of the development server:

    docker build --no-cache -f Dockerfile -t uniadaptive-back . --build-arg APP_DEBUG=true ...

You can add additional arguments as you see fit.

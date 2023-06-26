Este es un proyecto Laravel 9 php que utiliza el framework Laravel para crear aplicaciones web con PHP. Laravel ofrece una sintaxis elegante y expresiva, así como muchas características útiles como el sistema de plantillas Blade, el ORM Eloquent, la autenticación, la validación, el enrutamiento, etc. Este proyecto también utiliza Docker para facilitar el despliegue y la configuración del entorno de desarrollo y producción.

## Empezando

### Instalación normal

- [Instala PHP](https://www.php.net/manual/es/install.php)
- [Instala Composer](https://getcomposer.org/download/)
- [Instala Laravel](https://laravel.com/docs/9.x/installation)

Instala las dependencias necesarias:

```bash
composer install
```

Copia el archivo .env.example y renómbralo a .env. Edita los valores según tus preferencias y necesidades.

```bash
cp .env.example .env
```

Genera una clave de aplicación:

```bash
php artisan key:generate
```

Ejecuta las migraciones y los seeders de la base de datos:

```bash
php artisan migrate --seed
```

Ejecuta el servidor de desarrollo:

```bash
php artisan serve --port:9000
```

Abre http://localhost:9000 con tu navegador para ver el resultado.

Puedes empezar a editar el proyecto modificando los archivos en la carpeta `app`.

### Docker

Puedes construir tu propia imagen de docker adaptada a tus necesidades.

Para crear una imagen del servidor de desarrollo:

    docker build --no-cache -f Dockerfile -t uniadaptive-back . --build-arg APP_DEBUG=true ...

Puedes añadir argumentos adicionales según te convenga.

## Aprende más

Para aprender más sobre Laravel 9 php, Docker y otras tecnologías utilizadas en este proyecto, consulta los siguientes recursos:

- [Documentación de Laravel](https://laravel.com/docs/9.x) - aprende sobre las características y la API de Laravel.
- [Documentación de PHP](https://www.php.net/manual/es/index.php) - aprende sobre las características y la API de PHP.
- [Documentación de Docker](https://docs.docker.com/) - aprende sobre las características y el uso de Docker.

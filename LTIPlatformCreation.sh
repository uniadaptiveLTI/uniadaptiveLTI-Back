#!/bin/bash

alias php=`which php`
# Check if PHP is installed
#if ! command -v php &> /dev/null
#then
#    echo "PHP no está instalado. Por favor instalalo y prueba otra vez."
#    exit 1
#fi

echo "Iniciando la creación de plataforma... NOTA: Requiere conexión con la base de datos y acceso al comando PHP"

php artisan migrate --force &&

# Moodle example

# php artisan lti:add_platform_1.3 moodle --client_id=CLIENT_ID --deployment_id=DEPLOYMENT_ID <<!
# PLATFORM_ID
# !

# Sakai example

# php artisan lti:add_platform_1.3 custom --client_id=CLIENT_ID --deployment_id=DEPLOYMENT_ID <<!
# PLATFORM_ID
# JSON_Web_Key_Set_URL
# authentication_URL
# access_token_URL
# !


# Add platforms below this point, remember to add "&&" if needed

php artisan lti:add_platform_1.3 moodle --client_id=CLIENT_ID --deployment_id=DEPLOYMENT_ID <<!
PLATFORM_ID
!

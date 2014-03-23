* Empezando: ejecutar la aplicacion de documentacion

Vas a necesitar:
- Un servidor web configurado con PHP


Crear un MODULO:
/path/myApp/

(De aqui en adelante, todas las rutas se consideran delativas a la CARPETA ROOT del modulo ("/path/myApp"))

Crear carpeta publica:
public

Crear archivo:
public/index.php

<?php
//Inicializamos la aplicacion incluyendo el LOADER del Phidias Framework:
include '/path/to/framework/loader.php';

//Usamos la clase "Environment" de Phidias Framework
use \Phidias\Environment;

//Inicializamos el entorno
Environment::start();


* Configurar un servidor web para que sirva la carpeta publica del modulo (/path/myApp/)

EJ: http://localhost/myApp/

Al ejecutar esta peticion vemos "WELCOME TO PHIDIAS"

Ahora, incluyamos el modulo de documentacion de Phidias:

public/index.php

<?php
include '/path/to/framework/loader.php';

use \Phidias\Environment;

//Incluir el modulo "documentation:"
Environment::module('/path/to/framework/documentation');

Environment::start();

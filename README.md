# Prerequisites:

- PHP 5.3
- Web server with URL rewriting capabilities (e.g. Apache with mod-rewrite)




# Download Phidias Framework

Checkout the repository into [path to framework]/
```
git clone https://github.com/phidiasdev/framework.git
```




# Create a module

> Available to copy [path to framework]/modules/sampleModule

Default directory structure:

```
sampleModule/
	configuration/
	documentation/
	initialize/
	languages/
	libraries/
	public/
	specification/
	views/
```



# Declare a Controller

file: sampleModule/libraries/Phidias/Example/myController.php

```php
<?php
namespace Phidias\Example;

class myController
{
	public function hello()
	{
		return "Hello";
	}
}
```




# Declare a Resource

file: sampleModule/configuration/routes.php

```php
<?php
use Phidias\Resource\Route;

Route::forRequest('GET example/hello')
	   ->useController(array('Phidias\Example\myController', 'hello'));
```


# Setup Web environment

Make sampleModule/public available in a public url.  (http://[localhost/myApp])

> **Note on redirecting URLs**: Configure your web server to rewrite: http://[localhost/myApp]/**example/hello**  -- into -->  http://[localhost/myApp]/index.php?_url=**example/hello**

file: sampleModule/public/index.php

```php
<?php
include '[path to framework]/loader.php';

use Phidias\Environment;

Environment::start();
```


# Run 

Browse to http://[localhost/myApp]/**example/hello**
> Which should redirect to http://[localhost/myApp]/index.php?_url=**example/hello**


Output:
```html
<h1>String</h1>
Hello
```

> Run http://[localhost/myApp]/example/hello?**__debug** for details about the resource's execution

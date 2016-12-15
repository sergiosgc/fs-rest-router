# composer-fs-rest-router
Filesystem based request router for HTTP verb-aware applications (RESTful)

## Objective

The usual PHP request routing pattern is that the script filename is obtained from the request URI, possibly after appending `index.php`. This router follows the same logic, with a step up the RESTful path. It uses the request HTTP verb (`GET`, `POST`, `PUT`, ...) to find out the correct script in the filesystem path that will handle the request.

Let's assume an application stored under the document root `/srv/www/myapp` receiving three requests:

1. A GET to `/customer/`
2. A POST to `/customer/`
3. A PUT to `/customer/10`

Under typical PHP routing, the first two requests will be handled by `/srv/www/myapp/customer/index.php` and the third one would require request rewriting at the webserver layer

Using this router, the requests will be handled by:

1. `/srv/www/myapp/customer/get.php`
2. `/srv/www/myapp/customer/post.php`
3. `/srv/www/myapp/customer/put.php` (extraction of the customer id is possible and covered in this readme)

This eases development of RESTful apps, while leveraging the application directory structure to map out the URL space. 

## Installation

The easiest way to install and use is via Composer. On your composer.json:

```
# composer.json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/sergiosgc/composer-fs-rest-router.git"
        }
    ],
    "require": {
        "sergiosgc/rest-router": "dev-master"
    }
}
```

Then issue either `composer update` or `composer install`.

Now, on your document root, create a catch-all script named index.php:
```
<?php
require_once('vendor/autoload.php');
(new \sergiosgc\router\Rest())->route();
```

And configure your webserver to route all requests for the vhost onto this index.php. For example, on nginx, the vhost file would be similar to:
```
server {
  listen 80;

  root /srv/www/myapp;
  index /index.php;

  location / {
    try_files $uri /index.php?$args;
  }

  location ~ \.php$ {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:/run/php/php7.0-fpm.sock;
    include fastcgi_params;
  }
}
```

## Basic Usage

Just create directories for your RESTful objects. For example, to handle requests for `/customer/`, create a directory `/srv/www/myapp/customer/`. Then, create one script file for each method you wish to handle:

* `post.php` for the POST method (create)
* `get.php` for GET/HEAD methods (read)
* `head.php` for the HEAD method
* `put.php` for the PUT method (update)
* `delete.php` for the DELETE method (delete)

If `head.php` is not present, the router will try to fallback to `get.php`. Any method is accepted; the filename should be the lowercased method name with `.php` appended.

## URL subtrees

A script will automatically handle all requests from that URL tree node down. For example, a script `/srv/www/myapp/customer/order/get.php` will handle both the `/customer/order/` and the `/customer/order/return/` URLs. Namely, it will handle the typical beautified URI `/customer/order/10/` for a RESTful collection plus collection item ID. 

The most specific script in the path will be used. For example, if these two scripts are present: `/srv/www/myapp/customer/order/get.php` and `/srv/www/myapp/customer/get.php`, the URL `/customer/order/return/` will be handled by `/srv/www/myapp/customer/order/get.php`.

## Argument extraction from the URL

The router will set these three variables:
* `$_SERVER['ROUTER_PATHBOUND_REQUEST_URI']`: The part of the URL that matches filesystem directories
* `$_SERVER['ROUTER_UNBOUND_REQUEST_URI']`: The part of the URL remaining after removing the path-bound request URI
* `$_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']`: The script filename obtained from `$_SERVER['ROUTER_PATHBOUND_REQUEST_URI']`

When using beautified URLs, you may extract extra data from the unbound part of the request URL. On the example above, a PUT to `/customer/10` handled by `/srv/www/myapp/customer/put.php` will have `10` the value of `$_SERVER['ROUTER_UNBOUND_REQUEST_URI']`.

For more complex URLs, you may use regular expressions to extract the parameters. On the same dir as the verb handling php script, drop a regular expression with named groups. Let's imagine the URL `/customer/10/20` is interpreted as customers from minid 10 to maxid 20. Alongside `/srv/www/myapp/customer/get.php` create `/srv/www/myapp/customer/get.regex` with this content:
```
_^(?<minid>[0-9]+)/(?<minid>[0-9]+)$_
```

The request router will use this regex to:
1. Match the unbound request uri and extract arguments minid and maxid. These will populate `$_REQUEST`
2. Validate that the unbound URI is valid (throw a 404 otherwise)

PlumePHP - 羽量级的单文件php开发框架

# What is PlumePHP

PlumePHP is a fast, simple, extensible, single file framework for PHP.
PlumePHP enables you to quickly and easily build RESTful web applications.
========

### 简介

PlumePHP是一个单入口，单文件PHP框架，适用于简单系统的快速开发，提供了简单的路由方式，抛弃了坑爹的PHP模板，采用原生PHP语法来渲染页面。如果您正在开发一个简单的功能，而又不想使用Yii，CodeIgniter，ThinkPHP等框架，则可以试用一下该框架。

区别于其他Web框架，该框架是一个绝对的单文件框架（整个框架只有一个index.php文件，与PHP入口文件index.php相同），不需要额外的引用或配置。

使用方法也及其简单，只需下载框架文件index.php到项目根目录，然后通过浏览器输入项目访问地址，即可自动生成框架目录结构（项目目录需要有写入权限）。

### 特点

* 加载简单，引入只需一个文件
- 日志机制
- 支持HTTP（网页模式）和cli（脚本模式）两种模式
- 事件驱动
- 数据库操作简便
- 助手类引入便捷
- 文件处理封装
- 时间处理封装
- Socket请求封装，支持代理模式
- Session支持cookie、file、db多种方式
- 支持委托代理机制
- 安全过滤器机制
- 其他

## 入口文件：index.php

```php
// 加载框架文件
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// api首页展示
PlumePHP::route('GET /api', function() {
    header('Content-Type: text/html;charset=utf-8');
    echo json_encode(['code'=>0, 'data'=>'api', 'msg'=>'success'], JSON_UNESCAPED_UNICODE);
});

// 通用的路由逻辑
PlumePHP::route('*', function() {
    PlumePHP::app()->run();
});

// 启动
PlumePHP::start();
```

## 控制台cmd入口文件：plume
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (PHP_SAPI != 'cli') {
    echo 'This script run only from the command line'.PHP_EOL;
    exit(255);
}

if (PHP_SAPI == 'cli-server' && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// 加载框架文件
include __DIR__ . DIRECTORY_SEPARATOR . 'PlumePHP.php';

// 启动应用程序
try {
    PlumePHP::app()->run();
} catch (Exception $e) {
    // 全局异常处理
    echo $e->getMessage(), PHP_EOL;
    exit(255);
}
```

# What is PlumePHP?

PlumePHP is a fast, simple, extensible framework for PHP. PlumePHP enables you to 
quickly and easily build RESTful web applications.

```php
require 'PlumePHP/PlumePHP.php';

PlumePHP::route('/', function(){
    echo 'hello world!';
});

PlumePHP::start();
```

[Learn more](http://PlumePHPphp.com/learn)

# Requirements

PlumePHP requires `PHP 5.3` or greater.

# License

PlumePHP is released under the [MIT](http://PlumePHPphp.com/license) license.

# Installation

1\. Download the files.

If you're using [Composer](https://getcomposer.org/), you can run the following command:

```
composer require mikecao/PlumePHP
```

OR you can [download](https://github.com/mikecao/PlumePHP/archive/master.zip) them directly 
and extract them to your web directory.

2\. Configure your webserver.

For *Apache*, edit your `.htaccess` file with the following:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Note**: If you need to use PlumePHP in a subdirectory add the line `RewriteBase /subdir/` just after `RewriteEngine On`.

For *Nginx*, add the following to your server declaration:

```
server {
    location / {
        try_files $uri $uri/ /index.php;
    }
}
```
3\. Create your `index.php` file.

First include the framework.

```php
require 'PlumePHP/PlumePHP.php';
```

If you're using Composer, run the autoloader instead.

```php
require 'vendor/autoload.php';
```

Then define a route and assign a function to handle the request.

```php
PlumePHP::route('/', function(){
    echo 'hello world!';
});
```

Finally, start the framework.

```php
PlumePHP::start();
```

# Routing

Routing in PlumePHP is done by matching a URL pattern with a callback function.

```php
PlumePHP::route('/', function(){
    echo 'hello world!';
});
```

The callback can be any object that is callable. So you can use a regular function:

```php
function hello(){
    echo 'hello world!';
}

PlumePHP::route('/', 'hello');
```

Or a class method:

```php
class Greeting {
    public static function hello() {
        echo 'hello world!';
    }
}

PlumePHP::route('/', array('Greeting', 'hello'));
```

Or an object method:

```php
class Greeting
{
    public function __construct() {
        $this->name = 'John Doe';
    }

    public function hello() {
        echo "Hello, {$this->name}!";
    }
}

$greeting = new Greeting();

PlumePHP::route('/', array($greeting, 'hello')); 
```

Routes are matched in the order they are defined. The first route to match a
request will be invoked.

## Method Routing

By default, route patterns are matched against all request methods. You can respond
to specific methods by placing an identifier before the URL.

```php
PlumePHP::route('GET /', function(){
    echo 'I received a GET request.';
});

PlumePHP::route('POST /', function(){
    echo 'I received a POST request.';
});
```

You can also map multiple methods to a single callback by using a `|` delimiter:

```php
PlumePHP::route('GET|POST /', function(){
    echo 'I received either a GET or a POST request.';
});
```

## Regular Expressions

You can use regular expressions in your routes:

```php
PlumePHP::route('/user/[0-9]+', function(){
    // This will match /user/1234
});
```

## Named Parameters

You can specify named parameters in your routes which will be passed along to
your callback function.

```php
PlumePHP::route('/@name/@id', function($name, $id){
    echo "hello, $name ($id)!";
});
```

You can also include regular expressions with your named parameters by using
the `:` delimiter:

```php
PlumePHP::route('/@name/@id:[0-9]{3}', function($name, $id){
    // This will match /bob/123
    // But will not match /bob/12345
});
```

## Optional Parameters

You can specify named parameters that are optional for matching by wrapping
segments in parentheses.

```php
PlumePHP::route('/blog(/@year(/@month(/@day)))', function($year, $month, $day){
    // This will match the following URLS:
    // /blog/2012/12/10
    // /blog/2012/12
    // /blog/2012
    // /blog
});
```

Any optional parameters that are not matched will be passed in as NULL.

## Wildcards

Matching is only done on individual URL segments. If you want to match multiple
segments you can use the `*` wildcard.

```php
PlumePHP::route('/blog/*', function(){
    // This will match /blog/2000/02/01
});
```

To route all requests to a single callback, you can do:

```php
PlumePHP::route('*', function(){
    // Do something
});
```

## Passing

You can pass execution on to the next matching route by returning `true` from
your callback function.

```php
PlumePHP::route('/user/@name', function($name){
    // Check some condition
    if ($name != "Bob") {
        // Continue to next route
        return true;
    }
});

PlumePHP::route('/user/*', function(){
    // This will get called
});
```

## Route Info

If you want to inspect the matching route information, you can request for the route
object to be passed to your callback by passing in `true` as the third parameter in
the route method. The route object will always be the last parameter passed to your
callback function.

```php
PlumePHP::route('/', function($route){
    // Array of HTTP methods matched against
    $route->methods;

    // Array of named parameters
    $route->params;

    // Matching regular expression
    $route->regex;

    // Contains the contents of any '*' used in the URL pattern
    $route->splat;
}, true);
```

# Extending

PlumePHP is designed to be an extensible framework. The framework comes with a set
of default methods and components, but it allows you to map your own methods,
register your own classes, or even override existing classes and methods.

## Mapping Methods

To map your own custom method, you use the `map` function:

```php
// Map your method
PlumePHP::map('hello', function($name){
    echo "hello $name!";
});

// Call your custom method
PlumePHP::hello('Bob');
```

## Registering Classes

To register your own class, you use the `register` function:

```php
// Register your class
PlumePHP::register('user', 'User');

// Get an instance of your class
$user = PlumePHP::user();
```

The register method also allows you to pass along parameters to your class
constructor. So when you load your custom class, it will come pre-initialized.
You can define the constructor parameters by passing in an additional array.
Here's an example of loading a database connection:

```php
// Register class with constructor parameters
PlumePHP::register('db', 'PDO', array('mysql:host=localhost;dbname=test','user','pass'));

// Get an instance of your class
// This will create an object with the defined parameters
//
//     new PDO('mysql:host=localhost;dbname=test','user','pass');
//
$db = PlumePHP::db();
```

If you pass in an additional callback parameter, it will be executed immediately
after class construction. This allows you to perform any set up procedures for your
new object. The callback function takes one parameter, an instance of the new object.

```php
// The callback will be passed the object that was constructed
PlumePHP::register('db', 'PDO', array('mysql:host=localhost;dbname=test','user','pass'), function($db){
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
});
```

By default, every time you load your class you will get a shared instance.
To get a new instance of a class, simply pass in `false` as a parameter:

```php
// Shared instance of the class
$shared = PlumePHP::db();

// New instance of the class
$new = PlumePHP::db(false);
```

Keep in mind that mapped methods have precedence over registered classes. If you
declare both using the same name, only the mapped method will be invoked.

# Overriding

PlumePHP allows you to override its default functionality to suit your own needs,
without having to modify any code.

For example, when PlumePHP cannot match a URL to a route, it invokes the `notFound`
method which sends a generic `HTTP 404` response. You can override this behavior
by using the `map` method:

```php
PlumePHP::map('notFound', function(){
    // Display custom 404 page
    include 'errors/404.html';
});
```

PlumePHP also allows you to replace core components of the framework.
For example you can replace the default Router class with your own custom class:

```php
// Register your custom class
PlumePHP::register('router', 'MyRouter');

// When PlumePHP loads the Router instance, it will load your class
$myrouter = PlumePHP::router();
```

Framework methods like `map` and `register` however cannot be overridden. You will
get an error if you try to do so.

# Filtering

PlumePHP allows you to filter methods before and after they are called. There are no
predefined hooks you need to memorize. You can filter any of the default framework
methods as well as any custom methods that you've mapped.

A filter function looks like this:

```php
function(&$params, &$output) {
    // Filter code
}
```

Using the passed in variables you can manipulate the input parameters and/or the output.

You can have a filter run before a method by doing:

```php
PlumePHP::before('start', function(&$params, &$output){
    // Do something
});
```

You can have a filter run after a method by doing:

```php
PlumePHP::after('start', function(&$params, &$output){
    // Do something
});
```

You can add as many filters as you want to any method. They will be called in the
order that they are declared.

Here's an example of the filtering process:

```php
// Map a custom method
PlumePHP::map('hello', function($name){
    return "Hello, $name!";
});

// Add a before filter
PlumePHP::before('hello', function(&$params, &$output){
    // Manipulate the parameter
    $params[0] = 'Fred';
});

// Add an after filter
PlumePHP::after('hello', function(&$params, &$output){
    // Manipulate the output
    $output .= " Have a nice day!";
});

// Invoke the custom method
echo PlumePHP::hello('Bob');
```

This should display:

    Hello Fred! Have a nice day!

If you have defined multiple filters, you can break the chain by returning `false`
in any of your filter functions:

```php
PlumePHP::before('start', function(&$params, &$output){
    echo 'one';
});

PlumePHP::before('start', function(&$params, &$output){
    echo 'two';

    // This will end the chain
    return false;
});

// This will not get called
PlumePHP::before('start', function(&$params, &$output){
    echo 'three';
});
```

Note, core methods such as `map` and `register` cannot be filtered because they
are called directly and not invoked dynamically.

# Variables

PlumePHP allows you to save variables so that they can be used anywhere in your application.

```php
// Save your variable
PlumePHP::set('id', 123);

// Elsewhere in your application
$id = PlumePHP::get('id');
```
To see if a variable has been set you can do:

```php
if (PlumePHP::has('id')) {
     // Do something
}
```

You can clear a variable by doing:

```php
// Clears the id variable
PlumePHP::clear('id');

// Clears all variables
PlumePHP::clear();
```

PlumePHP also uses variables for configuration purposes.

```php
PlumePHP::set('PlumePHP.log_errors', true);
```

# Views

PlumePHP provides some basic templating functionality by default. To display a view
template call the `render` method with the name of the template file and optional
template data:

```php
PlumePHP::render('hello.php', array('name' => 'Bob'));
```

The template data you pass in is automatically injected into the template and can
be reference like a local variable. Template files are simply PHP files. If the
content of the `hello.php` template file is:

```php
Hello, '<?php echo $name; ?>'!
```

The output would be:

    Hello, Bob!

You can also manually set view variables by using the set method:

```php
PlumePHP::view()->set('name', 'Bob');
```

The variable `name` is now available across all your views. So you can simply do:

```php
PlumePHP::render('hello');
```

Note that when specifying the name of the template in the render method, you can
leave out the `.php` extension.

By default PlumePHP will look for a `views` directory for template files. You can
set an alternate path for your templates by setting the following config:

```php
PlumePHP::set('PlumePHP.views.path', '/path/to/views');
```

## Layouts

It is common for websites to have a single layout template file with interchanging
content. To render content to be used in a layout, you can pass in an optional
parameter to the `render` method.

```php
PlumePHP::render('header', array('heading' => 'Hello'), 'header_content');
PlumePHP::render('body', array('body' => 'World'), 'body_content');
```

Your view will then have saved variables called `header_content` and `body_content`.
You can then render your layout by doing:

```php
PlumePHP::render('layout', array('title' => 'Home Page'));
```

If the template files looks like this:

`header.php`:

```php
<h1><?php echo $heading; ?></h1>
```

`body.php`:

```php
<div><?php echo $body; ?></div>
```

`layout.php`:

```php
<html>
<head>
<title><?php echo $title; ?></title>
</head>
<body>
<?php echo $header_content; ?>
<?php echo $body_content; ?>
</body>
</html>
```

The output would be:
```html
<html>
<head>
<title>Home Page</title>
</head>
<body>
<h1>Hello</h1>
<div>World</div>
</body>
</html>
```

## Custom Views

PlumePHP allows you to swap out the default view engine simply by registering your
own view class. Here's how you would use the [Smarty](http://www.smarty.net/)
template engine for your views:

```php
// Load Smarty library
require './Smarty/libs/Smarty.class.php';

// Register Smarty as the view class
// Also pass a callback function to configure Smarty on load
PlumePHP::register('view', 'Smarty', array(), function($smarty){
    $smarty->template_dir = './templates/';
    $smarty->compile_dir = './templates_c/';
    $smarty->config_dir = './config/';
    $smarty->cache_dir = './cache/';
});

// Assign template data
PlumePHP::view()->assign('name', 'Bob');

// Display the template
PlumePHP::view()->display('hello.tpl');
```

For completeness, you should also override PlumePHP's default render method:

```php
PlumePHP::map('render', function($template, $data){
    PlumePHP::view()->assign($data);
    PlumePHP::view()->display($template);
});
```
# Error Handling

## Errors and Exceptions

All errors and exceptions are caught by PlumePHP and passed to the `error` method.
The default behavior is to send a generic `HTTP 500 Internal Server Error`
response with some error information.

You can override this behavior for your own needs:

```php
PlumePHP::map('error', function(Exception $ex){
    // Handle error
    echo $ex->getTraceAsString();
});
```

By default errors are not logged to the web server. You can enable this by
changing the config:

```php
PlumePHP::set('PlumePHP.log_errors', true);
```

## Not Found

When a URL can't be found, PlumePHP calls the `notFound` method. The default
behavior is to send an `HTTP 404 Not Found` response with a simple message.

You can override this behavior for your own needs:

```php
PlumePHP::map('notFound', function(){
    // Handle not found
});
```

# Redirects

You can redirect the current request by using the `redirect` method and passing
in a new URL:

```php
PlumePHP::redirect('/new/location');
```

By default PlumePHP sends a HTTP 303 status code. You can optionally set a
custom code:

```php
PlumePHP::redirect('/new/location', 401);
```

# Requests

PlumePHP encapsulates the HTTP request into a single object, which can be
accessed by doing:

```php
$request = PlumePHP::request();
```

The request object provides the following properties:

```
url - The URL being requested
base - The parent subdirectory of the URL
method - The request method (GET, POST, PUT, DELETE)
referrer - The referrer URL
ip - IP address of the client
ajax - Whether the request is an AJAX request
scheme - The server protocol (http, https)
user_agent - Browser information
type - The content type
length - The content length
query - Query string parameters
data - Post data or JSON data
cookies - Cookie data
files - Uploaded files
secure - Whether the connection is secure
accept - HTTP accept parameters
proxy_ip - Proxy IP address of the client
```

You can access the `query`, `data`, `cookies`, and `files` properties
as arrays or objects.

So, to get a query string parameter, you can do:

```php
$id = PlumePHP::request()->query['id'];
```

Or you can do:

```php
$id = PlumePHP::request()->query->id;
```

## RAW Request Body

To get the raw HTTP request body, for example when dealing with PUT requests, you can do:

```php
$body = PlumePHP::request()->getBody();
```

## JSON Input

If you send a request with the type `application/json` and the data `{"id": 123}` it will be available
from the `data` property:

```php
$id = PlumePHP::request()->data->id;
```

# HTTP Caching

PlumePHP provides built-in support for HTTP level caching. If the caching condition
is met, PlumePHP will return an HTTP `304 Not Modified` response. The next time the
client requests the same resource, they will be prompted to use their locally
cached version.

## Last-Modified

You can use the `lastModified` method and pass in a UNIX timestamp to set the date
and time a page was last modified. The client will continue to use their cache until
the last modified value is changed.

```php
PlumePHP::route('/news', function(){
    PlumePHP::lastModified(1234567890);
    echo 'This content will be cached.';
});
```

## ETag

`ETag` caching is similar to `Last-Modified`, except you can specify any id you
want for the resource:

```php
PlumePHP::route('/news', function(){
    PlumePHP::etag('my-unique-id');
    echo 'This content will be cached.';
});
```

Keep in mind that calling either `lastModified` or `etag` will both set and check the
cache value. If the cache value is the same between requests, PlumePHP will immediately
send an `HTTP 304` response and stop processing.

# Stopping

You can stop the framework at any point by calling the `halt` method:

```php
PlumePHP::halt();
```

You can also specify an optional `HTTP` status code and message:

```php
PlumePHP::halt(200, 'Be right back...');
```

Calling `halt` will discard any response content up to that point. If you want to stop
the framework and output the current response, use the `stop` method:

```php
PlumePHP::stop();
```

# JSON

PlumePHP provides support for sending JSON and JSONP responses. To send a JSON response you
pass some data to be JSON encoded:

```php
PlumePHP::json(array('id' => 123));
```

For JSONP requests you, can optionally pass in the query parameter name you are
using to define your callback function:

```php
PlumePHP::jsonp(array('id' => 123), 'q');
```

So, when making a GET request using `?q=my_func`, you should receive the output:

```
my_func({"id":123});
```

If you don't pass in a query parameter name it will default to `jsonp`.


# Configuration

You can customize certain behaviors of PlumePHP by setting configuration values
through the `set` method.

```php
PlumePHP::set('PlumePHP.log_errors', true);
```

The following is a list of all the available configuration settings:

    PlumePHP.base_url - Override the base url of the request. (default: null)
    PlumePHP.case_sensitive - Case sensitive matching for URLs. (default: false)
    PlumePHP.handle_errors - Allow PlumePHP to handle all errors internally. (default: true)
    PlumePHP.log_errors - Log errors to the web server's error log file. (default: false)
    PlumePHP.views.path - Directory containing view template files. (default: ./views)
    PlumePHP.views.extension - View template file extension. (default: .php)

# Framework Methods

PlumePHP is designed to be easy to use and understand. The following is the complete
set of methods for the framework. It consists of core methods, which are regular
static methods, and extensible methods, which are mapped methods that can be filtered
or overridden.

## Core Methods

```php
PlumePHP::map($name, $callback) // Creates a custom framework method.
PlumePHP::register($name, $class, [$params], [$callback]) // Registers a class to a framework method.
PlumePHP::before($name, $callback) // Adds a filter before a framework method.
PlumePHP::after($name, $callback) // Adds a filter after a framework method.
PlumePHP::path($path) // Adds a path for autoloading classes.
PlumePHP::get($key) // Gets a variable.
PlumePHP::set($key, $value) // Sets a variable.
PlumePHP::has($key) // Checks if a variable is set.
PlumePHP::clear([$key]) // Clears a variable.
PlumePHP::init() // Initializes the framework to its default settings.
PlumePHP::app() // Gets the application object instance
```

## Extensible Methods

```php
PlumePHP::start() // Starts the framework.
PlumePHP::stop() // Stops the framework and sends a response.
PlumePHP::halt([$code], [$message]) // Stop the framework with an optional status code and message.
PlumePHP::route($pattern, $callback) // Maps a URL pattern to a callback.
PlumePHP::redirect($url, [$code]) // Redirects to another URL.
PlumePHP::render($file, [$data], [$key]) // Renders a template file.
PlumePHP::error($exception) // Sends an HTTP 500 response.
PlumePHP::notFound() // Sends an HTTP 404 response.
PlumePHP::etag($id, [$type]) // Performs ETag HTTP caching.
PlumePHP::lastModified($time) // Performs last modified HTTP caching.
PlumePHP::json($data, [$code], [$encode], [$charset], [$option]) // Sends a JSON response.
PlumePHP::jsonp($data, [$param], [$code], [$encode], [$charset], [$option]) // Sends a JSONP response.
```

Any custom methods added with `map` and `register` can also be filtered.


# Framework Instance

Instead of running PlumePHP as a global static class, you can optionally run it
as an object instance.

```php
require 'PlumePHP/autoload.php';

use PlumeEngine;

$app = new PlumeEngine();

$app->route('/', function(){
    echo 'hello world!';
});

$app->start();
```

So instead of calling the static method, you would call the instance method with
the same name on the Engine object.

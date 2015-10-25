# php-integrator-base

PHP Integrator analyzes PHP projects and exposes a service that other packages can use to provide additional functionality, such as autocompletion, code navigation and tooltips. In itself it does
not provide any user-visible functionality. This is instead covered by various other packages which
'plug in' to the service:
  * [php-integrator-autocomplete-plus](https://github.com/Gert-dev/php-integrator-autocomplete-plus) - Provides intelligent PHP autocompletion in combination with autocomplete-plus.
  * [php-integrator-navigation](https://github.com/Gert-dev/php-integrator-navigation) - Provides code navigation and go to functionality.
  * [php-integrator-tooltips](https://github.com/Gert-dev/php-integrator-tooltips) - Shows tooltips with documentation for your code.
  * [php-integrator-annotations](https://github.com/Gert-dev/php-integrator-annotations) - Shows annotations in your code, such as for overridden methods and interface implementations.

The source code was originally based on the php-autocomplete-plus code base and provides various
improvements as well as separation of the various components into separate packages.

## What do I need to do to make it work?
Currently the following is required in order to get the package up and running:
  * You must use [Composer](https://getcomposer.org/) for dependency management.
  * You must follow the PSR standards (for the names of classes, methods, namespacing, etc.).
  * You must write proper docblocks for your methods. There currently is no standard around this, but we try to follow the draft PSR-5 standard (which, in turn, is mostly inspired by phpDocumentor's implementation). Minimum requirements for proper autocompletion:
    * `@return` statements for functions and methods.
    * `@param` statements for functions and methods.
    * `@var` statements for properties in classes.
    * (Type hints in functions and methods will also be checked.)
  * You can also use comment-style type hints to override automatically deduced types or specify the type if the type can't be deduced automatically with...
    * ... IntellJ-style variable annotations `/** @var MyType $var */` as well as `/** @var $var MyType */`.
    * ... shortcut variable annotations (must appear right above the respective variable) `/** @var MyType */`.

Some features may or may not work outside these restrictions. For example, Composer is primarily used for its classmap and autoloading, so it may be possible to get everything working with another autoloading script and class map file. Reflection is used to fetch information about classes.

Don't forget to open the settings page after installing to set up the package!

## I'm A Package Developer - How Do I Use This?
You can very easily start using the service by simply consuming the service in your package (see also the `package.json` and `Main.coffee` files of the packages listed above for an example). The service is a single exposed class, which is located in the [Service.coffee](https://github.com/Gert-dev/php-integrator-base/blob/master/lib/Service.coffee) file, along with docblocks explaining what they do and what they accept as parameters..

## What Does Not Work?
Most of the issue reports indicate things that are missing, but indexing inside the artificial limitations specified above should be working fairly well in general. There are also some things that won't be supported at this time because they are fairly complex to implement (usually for fairly little benefit).
These limitations may affect other packages using the provided service:

  * Classes can override a method from a direct trait they're using, even if it is not abstract. In this case the class method will take precedence. With Reflection, there currently seems to be no way to detect this. This may lead to things such as code navigation taking you to the trait method instead of the overridding class method.
  * `static` and `self` behave mostly like `$this` in **non-static** contexts, i.e. they can also access non-static members.

## Do you accept donations?
I do accept donations and am very grateful for any donation you may give, but they were not my primary intention when releasing this project as open source. As such, a link to the (PayPal) donation screen is located [here](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YKTNLZCRHMRTJ), at the bottom of the readme, hidden from initial sight and not even in the form of a fancy button ;-).

![GPLv3 Logo](http://gplv3.fsf.org/gplv3-127x51.png)

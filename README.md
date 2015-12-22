# php-integrator-base

PHP Integrator analyzes PHP projects and exposes a service that other packages can use to provide additional functionality, such as autocompletion, code navigation and tooltips. In itself it does
not provide any user-visible functionality. This is instead covered by various other packages which
'plug in' to the service:
  * **[php-integrator-autocomplete-plus](https://github.com/Gert-dev/php-integrator-autocomplete-plus)** - Provides intelligent PHP autocompletion in combination with autocomplete-plus.
  * **[php-integrator-navigation](https://github.com/Gert-dev/php-integrator-navigation)** - Provides code navigation and go to functionality.
  * **[php-integrator-tooltips](https://github.com/Gert-dev/php-integrator-tooltips)** - Shows tooltips with documentation for your code.
  * **[php-integrator-annotations](https://github.com/Gert-dev/php-integrator-annotations)** - Shows annotations in your code, such as for overridden methods and interface implementations.

The source code was originally based on the php-autocomplete-plus code base and provides various
improvements as well as separation of the various components into separate packages.

## What do I need to do to make it work?
Currently the following is required in order to get the package up and running:
  * **PHP** - To run this package properly, you need at least PHP 5.4. The code you're actually writing can be anything ranging from PHP 5.2 up to (and including) PHP 7.0.
  * **PSR-compliant code** - Write code that follows the PSR standards, especially regarding namespacing.
  * **Docblocks** - You must write proper docblocks that follow the draft PSR-5 standard (mostly inspired by phpDocumentor's implementation):
    * `@var` statements for properties.
    * `@param` statements for functions and methods.
    * `@return` statements for functions and methods.
    * You can also use inline comment-style type hints to override automatically deduced types or specify the type if the type can't be deduced automatically with...
      * ... IntellJ-style variable annotations `/** @var MyType $var */` as well as `/** @var $var MyType */`.

Things such as type hints will also be checked. Some features may or may not work outside these restrictions. **Don't forget to open the settings page after installing to set up the package!**

## I'm a package developer - How do I use this?
You can very easily start using the service by simply consuming the service in your package (see also the `package.json` and `Main.coffee` files of the packages listed above for an example). The service is a single exposed class, which is located in the [Service.coffee](https://github.com/Gert-dev/php-integrator-base/blob/master/lib/Service.coffee) file, along with docblocks explaining what they do and what they accept as parameters..

## What does not work?
Most of the issue reports indicate things that are missing, but indexing inside the artificial limitations specified above should be working fairly well in general. There are also some things that won't be supported at this time because they are fairly complex to implement (usually for fairly little benefit).
These limitations may affect other packages using the provided service:

* `static` and `self` behave mostly like `$this` in **non-static** contexts, i.e. they can also access non-static members.
* Classes can override a method from a direct trait they're using, even if it is not abstract. In this case the class method will take precedence. With Reflection, there currently seems to be no way to detect this. This may lead to things such as code navigation taking you to the trait method instead of the overridding class method.
  * However, in the new php-parser-based indexer, there may be a way to fix this.

## Regarding donations
I do accept donations and am very grateful for any donation you may give, but they were not my primary intention when releasing this project as open source. As such, a link to the (PayPal) donation screen is located [here](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YKTNLZCRHMRTJ), at the bottom of the readme, hidden from initial sight and not even in the form of a fancy button ;-).

![GPLv3 Logo](http://gplv3.fsf.org/gplv3-127x51.png)

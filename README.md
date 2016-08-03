# php-integrator-base
<p align="right">
:coffee:
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YKTNLZCRHMRTJ">Send me some coffee beans</a>
</p>

PHP Integrator analyzes PHP projects and exposes a service that other packages can use to provide additional functionality, such as autocompletion, code navigation and tooltips. In itself it does
not provide any user-visible functionality. This is instead covered by various other packages which
'plug in' to the service:
  * **[php-integrator-autocomplete-plus](https://github.com/Gert-dev/php-integrator-autocomplete-plus)** - Provides intelligent PHP autocompletion in combination with autocomplete-plus.
  * **[php-integrator-navigation](https://github.com/Gert-dev/php-integrator-navigation)** - Provides code navigation and go to functionality.
  * **[php-integrator-tooltips](https://github.com/Gert-dev/php-integrator-tooltips)** - Shows tooltips with documentation.
  * **[php-integrator-annotations](https://github.com/Gert-dev/php-integrator-annotations)** - Shows annotations, such as for overridden methods and interface implementations.
  * **[php-integrator-call-tips](https://github.com/Gert-dev/php-integrator-call-tips)** - Shows call tips containing parameters in your code. (Complements the autocompletion package.)
  * **[php-integrator-refactoring](https://github.com/Gert-dev/php-integrator-refactoring)** - Provides basic refactoring capabilities.
  * **[php-integrator-linter](https://github.com/Gert-dev/php-integrator-linter)** - Shows indexing errors and problems with your code.

The following package also exists is currently looking for a new maintainer (see also its README):
  * **[php-integrator-symbol-viewer](https://github.com/tocjent/php-integrator-symbol-viewer)** - Provides a side panel listing class symbols with search and filter features.

The source code was originally based on the php-autocomplete-plus code base, but has significantly diverged from it since then.

## What do I need to do to make it work?
Currently the following is required in order to get the package up and running:
  * **PHP** - To run this package properly, you need at least PHP 5.5. The code you're actually writing can be anything ranging from PHP 5.2 up to (and including) PHP 7.0.
    * **php-sqlite** and SQLite >= 3.7.11 - Required as back end for the indexing database.
  * **PSR-compliant code** - Write code that follows the PSR standards, especially regarding namespacing.
  * **Documentation** and **type hinting** - Write proper docblocks that follow the draft PSR-5 standard (inspired by phpDocumentor's implementation) or use type hinting as much as possible:
    * Docblocks with a `@var` tag for properties.
    * Docblocks with `@param` tags for functions and methods. Parameter type hints will also work.
    * Docblocks with a `@return` tag for functions and methods. Return types in PHP 7 will also work.
    * IntellJ-style variable annotations `/** @var MyType $var */` as well as `/** @var $var MyType */` to override automatically deduced types or specify types in cases where it can't be automatically deduced.

Note that folders that aren't readable (no permission) will be silently ignored!

Some features may or may not work outside these restrictions. **Don't forget to open the settings page after installing to set up the package!**

## Performance tips
* Use PHP 7 for the indexer.
* Your temp folder is used for caching database access. Mounting it in memory (tmpfs) or using a RAMdisk may significantly improve performance.

## I'm a package developer - How do I use this?
You can very easily start using the service by simply consuming the service in your package (see also the `package.json` and `Main.coffee` files of the packages listed above for an example). The service is a single exposed class, which is located in the [Service.coffee](https://github.com/Gert-dev/php-integrator-base/blob/master/lib/Service.coffee) file, along with docblocks explaining what they do and what they accept as parameters.

As the service allows fetching information about the code base, other packages can do all kinds of interesting things with it that brings Atom closer to an IDE for PHP, yet completely open-source. Here are some idea's of things that could be done with the service (besides what the existing packages listed above already do):
* [A project-wide symbol viewer that supports fuzzy matching on symbols (much like Atom's fuzzy file matcher)](https://github.com/Gert-dev/php-integrator-navigation/issues/23).
* A package listing all the classes implementing an interface, using a trait, or extending another class.
* A package performing a semantic lint on an entire project, showing various problems with each file in a navigatable list.
* An (UML?) class diagram builder that creates a visual representation of the relations between all classes in a code base (i.e. their implemented interfaces, base classes and traits).
* Most of the existing packages could use new improvements, contributions are most welcome.

### Notes
* Offsets passed to the service should be character offsets. The PHP side can actually work with both, but Atom's `TextBuffer` works primarily with character offsets.
* Offsets returned by the service are byte offsets. This is because the PHP parsing back end works with these and to cut down on code that needs to be Unicode-aware. The service provides the `getCharacterOffsetFromByteOffset` method to allow you to deal with conversion if necessary.

## Can I use this for other editors?
Well, yes and no: the packages themselves are dependent on Atom, of course, but the PHP side is not dependent on Atom at all. In theory it is possible to just extract out the PHP source and build a plugin or extension for another editor around it.

![GPLv3 Logo](http://gplv3.fsf.org/gplv3-127x51.png)

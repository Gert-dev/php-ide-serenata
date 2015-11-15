## 0.3.0
### Features and enhancements
* Performance in general should be improved as the same parsing operations are now cached as often as possible.
* If statements that have (only) an 'instanceof' of a variable in them will now be used to deduce the type of a variable. (More complex if statements will, at least for now, not be picked up.) For example (when using php-integrator-autocomplete-plus):

```php
if ($foo instanceof Foo) {
    $foo-> // Autocompletion for Foo will be shown.
}
```

* Closures will now receive autocompletion, for example (when using php-integrator-autocomplete-plus):

```php
$foo = function () {

};

$foo-> // Autocompletion for Closure, listing bind and bindTo.
```

* Added support for parsing magic properties for classes, which will now also be returned (property-read and property-write are also returned):

```php
/**
 * @property Foo $someProperty A description.
 */
class MyClass
{

}
```

### Bugs fixed
* Docblock parameters weren't being analyzed for deducing the type of local variables when in a global function.
* Types of variables that had their assigned values spread over multiple lines will now correctly have their type deduced.
* Only the relevant scopes will now be searched for the type of variables, previously all code was examined, even code outside the current scope.
* Support for the short annotation style, `/** @var FooClass */`, was dropped. The reason for this is that it's not supported by any IDE and is very specific to this package. It's also completely inflexible because it needs to be directly above the last assignment or other type deduction (such as a catch block) for it to be picked up incorrectly. The other annotation styles have none of these restrictions and also work in IDE's such as PHPStorm.

### Changes for developers
* Changes to the service
  * The `getDocParams` method has been removed. It was obsolete as the same information is already returned by `getClassInfo`. Also more caches can be reused by using `getClassInfo`.
  * Data returned about methods, constants, functions and structures will no longer have an 'args' property containing information such as descriptions. Instead these were moved up one level (in other words you can just replace the `.args.property` with just `.property` everywhere). It wasn't clear what exactly belonged in `args` and what didn't, hence its entire removal.

## 0.2.0
### Features and enhancements
* There was no convenient visual indicator of when indexing failed, a label is now shown in the status bar if that is indeed the case.
* When the initial PHP process that indexes the entire project fails or is killed, it will now be picked up and displayed as an error.
* The list of variables returned will now try to skip scopes that don't apply. For example, you will now only see variables that are relevant to your closure when inside one.
* It is now possible to specify a list of additional scripts to load, which allows you to add things such as bootstrap scripts or scripts with global helper functions, which will then be made available to other packages (such as autocompletion).
* The return type of your global functions will now be correctly analyzed, the following will now work:

```php
/**
 * @return \DateTime
 */
function foo()
{

}

foo()-> // Autocompletion for DateTime.
```

### Bugs fixed
* Fixed the 'className.split is not a function' error popping up sometimes.
* Fixed type hints from function parameters not being correctly deduced in some cases.
* Return values such as `\DateTime` (with a leading slash) were not always being found.
* Numbers and underscores were not permitted in class names in several locations (they are now).
* The PHP FileParser will no longer trip over class docblocks containing the pattern `class MyClass`.
* Classes from this package are now no longer included in the class list and will no longer be indexed.
* Fix several issues with autocompletion of `(new Foo())->` in corner cases such as inside arrays and function calls.
* Global class names in combination with the 'new' operator such as `new \My\Class` were not properly having their type deduced (and e.g. getting no autocompletion as a result).
* Fixed an issue where the package would attempt to index the project on shutdown. This could result in a message being displayed at shutdown about the Atom window not being responsive.

### Changes for developers
* New service methods:
  * `getClassMethod` - Retrieves information about a specific class method.
  * `getClassProperty` - Retrieves information about a specific class property.
  * `getClassConstant` - Retrieves information about a specific class constant.
* Changes to the service:
  * Previously, `getCalledClass` always ignored the last element in a call stack, causing `$this->foo->b` to return the type of `foo` instead of `b`. Because this behavior is unexpected and undocumented, this no longer happens. To maintain this 'feature', a new parameter `ignoreLastElement` has been added that can be set to true to restore this behavior (i.e. it will return the type of `foo`). Setting it to false will return the type of `b` instead.
  * `getGlobalFunctions` will now also return user-defined global functions (i.e. non-internal global functions).
  * `getGlobalConstants` will now also return information about if a constant is built-in or not (as opposed to user defined).
  * `getAvailableVariables` now returns an object with variable names mapped to information such as their type (if found).
  * `getClassMemberAt` will now return the correct member if a structure has a property and a method with the same name.
  * `getCalledClass` is now called `getResultingTypeAt` to better indicate what it does and that it needs a buffer position.
  * Class constants will now contain information about their declaring class and declaring structure, just like other members.
  * Several methods such as `getClassInfo` now take an additional parameter to make them execute asynchronously (a promise will be returned instead of the actual results).

## 0.1.0
* Initial release.

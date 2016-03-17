## 0.7.2
### Bugs fixed
* Fixed minor error where the service version was bumped incorrectly.

## 0.7.1
### Bugs fixed
* Fixed semantic linting not marking use statements in docblocks as used when their types were used inside a type union (e.g. `string|DateTime`).
* Fixed semantic linting not checking if class names in docblocks existed when they occurred inside a type union (e.g. `string|DateTime`).
* Fixed semantic linting not validating class names that were used inside function calls or in class constant fetching.
* Fixed semantic linting marking use statements as unused whilst they were being used inside function calls or in class constant fetching.

### Changes for developers
* Changes to the service
  * Methods will now contain information about whether they are abstract or not.
  * Methods will now contain information about whether the method they override was abstract or not.

## 0.7.0
### Features and enhancements
* The SQLite database will now use the WAL journal mode, which offers performance benefits. (See also [the SQLite documentation](https://www.sqlite.org/draft/wal.html) for those interested.)
* Additional checks have been added to ensure the indexing database doesn't go into an invalid state.
* The use of `{@inheritDoc}` to extend long descriptions will now also work for child classes and extending interfaces.
* Indexing will now respond less aggressively to changes in the buffer. Instead of spawning an indexing process unconditionally each time a file stops changing (after a couple hundred milliseconds by default), an indexing process will now only be spawned as soon as the previous one finishes. In essence, if an indexing process is already running, the indexer holds on to the changes and issues a reindex as soon as the previous one finishes. If a file takes very long to index and at this point you make multiple changes to the same file, only the last version will be reindexed (i.e. reindexing actions are not actually queued and the indexer does not need to catch up on a possibly long list of changes).

### Bugs fixed
* Fixed some parameters of magic methods not showing up at all.
* Fixed event handlers not being disposed when the package was deactivated.
* Fixed parameter names for magic methods containing a comma in some cases.
* Fixed parameters for magic methods being incorrectly marked as being optional.
* Return types for magic properties were not being fetched at all.
* Fixed problems with paths containing spaces on anything non-Windows (Linux, Mac OS X, ...).
* Fixed the indexing progress bar disappearing sometimes if you edited a file while it was busy.
* Fixed the `Warning: is_dir(): Unable to find the wrapper "atom"` error showing up in rare cases.
* Fixed call stacks of simple expressions interpolated inside strings not being correctly retrieved.
* Return types for magic methods were not being resolved relative to use statements or the namespace.
* Parameter names for magic methods no longer contain a dollar sign (consistent with everything else).
* Always set the timezone to UTC. (I changed my mind about this, due to reasons listed in [PR #129](https://github.com/Gert-dev/php-integrator-base/pull/129).) (thanks to [@tillkruss](https://github.com/tillkruss)).
* Fixed issues with the indexer not correctly dealing with class names starting with a lower case letter.
* Some non-standard behavior has been removed that may or may not be noticable to users:
  * Constants in traits will no longer be picked up (PHP error).
  * Properties in traits will no longer be scanned for aliases (PHP error).
  * Properties inside interfaces will not be "inherited" anymore (PHP error).

### Changes for developers
* Changes to the service
  * Magic properties and methods will no longer return true for `hasDocblock`.
  * `getAvailableVariables` received an extra (optional) `async` parameter.
  * `determineCurrentClassName` received an extra (optional) `async` parameter.
  * Magic properties and methods now return the class start line as their end line.
  * A new method `semanticLint` is now available that can semantically lint files and return various issues with it.
  * A new method `getAvailableVariablesByOffset` has been added that allows fetching available variables via an offset in a file. This method is implemented in PHP, supports asynchronous operation and the existing `getAvailableVariables` has been rewritten to use this method.

## 0.6.10
### Bugs fixed
* Fixed the type resolver (--resolve-type) returning types prefixed with a leading slash when a file had no namespace, which is unnecessary and confusing as this slash isn't prepended anywhere else.

## 0.6.9
### Bugs fixed
* Fixed built-in functions not being marked as being built-in.
* Fixed the "Oops, something went wrong!" message when opening a comment that encompassed the rest of the file.

## 0.6.8
### Bugs fixed
* Fixed no internal namespace record being generated when there were no use statements nor a namespace in a file (resulting in notifications being shown relating to no namespace being found).

## 0.6.7
### Bugs fixed
* Fixed incorrect array key being used (`Undefined index: end_line`).
* Notices and warnings from the PHP side will now be shown in the error notification.
* Error reporting is now enabled explicitly in case you have it disabled on a global scale so you can see what is going wrong in the Atom error notification (without having to manually debug it).

## 0.6.6
### Bugs fixed
* Fixed compatibility with PHP < 5.6 by avoiding use of ARRAY_FILTER_USE_KEY.

## 0.6.5
### Bugs fixed
* Reintroduced the xdebug max nesting level, but this time set it to a ridiculously large number for compatibility.

## 0.6.4
### Bugs fixed
* Fixed a minor logic error.

## 0.6.3
### Bugs fixed
* Don't treat invalid JSON output as a JavaScript error, as it's usually not a bug in our package. Just display an error to the user instead.

## 0.6.2
### Bugs fixed
* The "Indexing failed!" message has disappeared. If you wish to know when the indexer is having problems, consider using the new linter package (see also the README).

## 0.6.1
### Bugs fixed
* Removed the dependency on fuzzaldrin.
* Fixed the type of new instances, wrapped in parentheses and spread over several lines, not being properly recognized:

```php
// Works:
(new \IteratorIterator)->

// Fails (fixed):
(new \IteratorIterator(

))->
```

## 0.6.0
### Features and enhancements
* xdebug will now be disabled in the indexer if it is present, it will only slow down the indexer.
* Support for type inference when using the `clone` keyword, for example:

```php
$a = new \DateTime();
$b = clone $a;
$b-> // Autocompletion for DateTime.
```

* The draft PSR-5's `@inheritDoc` is now supported to indicate documentation was forgotten. Also, note the difference with the non-standard ("incorrect"), yet commonly used, curly brace syntax used by Symfony 2 and other frameworks (which is also supported).

```php
/**
 * @inheritDoc
 */
public function explicitInheritanceAccordingToDraftPsr5()
{
    ...
}

/**
 * {@inheritDoc}
 */
public function incorrectInheritanceButCommonlyUsed()
{
    ...
}
```

### Bugs fixed
* Fixed autocompletion not working after the concatenation operator.
* Fixed author tags that contained mail addresses (such as used in Symfony) being taken up in extended descriptions.
* Fixed an incorrect parameter index being returned in cases where you had array access via keys in function or method invocations.
* Fixed a database constraint error when indexing parameters, which can happen if you have certain PHP extensions enabled (I'm looking at you, ssh2).
* Resolving types is now more correct, taking multiple namespaces per file into account as well as only utilizing use statements that actually apply for a specific line.

### Changes for developers
* Changes to the service
  * A new call `getClassListForFile` takes a file path to filter the class list by.
  * `getClassSelectorFromEvent` will no longer return null for built-in classes (you can still easily check this yourself).
  * Next to `startLine` information, `endLine` information will now also be returned.
  * Fetching class information will now also return information about direct and indirect implemented interfaces and used traits via the properties `parents`, `directParents`, `interfaces`, `directInterfaces`, `traits` and `directTraits`.
  * Fetching class information will now also return information about direct children, direct implementors (if it's an interface) and direct users (if it's a trait).
  * `determineFullClassName` was split up into two methods (separation of concerns):
    * `determineCurrentClassName` - Takes an editor and a buffer position and returns the FQCN of the class the location is in. The buffer position is now required as a file can contain multiple classes, which were not retrievable before.
    * `resolveType` (with accompanying convenience method `resolveTypeAt`) - Takes an editor, a line, and a type name and resolves that type to its FQCN based on local use statements and namespace rules.

## 0.5.4
### Features and enhancements
* Better error reporting. Exceptions thrown by the PHP side will now be displayed in an Atom error.

### Bugs fixed
* Fixed `getInvocationInfoAt` not giving correct results when inside an argument that was wrapped in parentheses.
* Fixed `getInvocationInfoAt` not giving a correct argument index when inside an array parameter with square brackets.

## 0.5.3
### Bugs fixed
* Fixed expressions inside ternary operators not correctly being fetched.

## 0.5.2
### Bugs fixed
* Fixed an error being thrown when writing code in a PHP file that hadn't been saved yet.

## 0.5.1
### Bugs fixed
* Fixed classes never being marked as being abstract.

## 0.5.0
### Features and enhancements
* Minor performance improvements when resolving local class names from docblocks.
* Indexing will now happen continuously (onDidStopChanging of the text buffer) instead of only on save.
* Descriptions from base classes or base interfaces will now be inherited if no description is present for a child class.
* Type inference has been added for arrays, e.g.:

```php
/** @var Foo[] $list */
foreach ($list as $item) {
    $item-> // Autocompletion will be provided for 'Foo'.
}
```

### Bugs fixed
* Fixed docblocks with an empty throws tag causing the indexer to fail (Filter/Encrypt/Mcrypt.php in Zend 1 has one of these, apparently).
* Fixed types not being properly inferred with the new keyword in combination with keywords such as `self`, `static` and `parent`, e.g. `$foo = new static();`.
* Fixed issues with retrieving types in call stacks including static access, such as `self::$property->foo`.
* Fixed built-in methods and functions having class types as parameters not being properly indexed. Instead, a PHP object would be returned in the parameter list. (Only applies to PHP 7.)
* Fixed paths with spaces not indexing properly on Windows. (thanks to [@dipyalov](https://github.com/dipyalov))
* Fixed variables being assigned to an expression on themselves sometimes not having their correct type deduced, for example:

```php
$foo = new Foo();
$foo = $foo-> // The cursor is here.

moreCode();

// The type would now be based on "$foo = $foo->moreCode()" instead of on "$foo = new Foo()".
```

### Changes for developers
* Changes to the service
  * Popovers will, by default, no longer catch pointer events (making them click-through).
  * You can now listen to indexing succeeding or failing using `onDidFinishIndexing` and `onDidFailIndexing`. (thanks to [@tocjent](https://github.com/tocjent))
  * The type for parameters of methods will now return the type as it was found in the file. If you wish to access the full resolved class type, you can use the new `fullType` property instead.
  * A new method `getInvocationInfoAt` is now available that allows fetching information about the function or method being invoked at the specified cursor position in an editor.

## 0.4.9
### Bugs fixed
* Fixed the type of function or method parameters that were not documented incorrectly being set to the type of `$this`.

## 0.4.8
### Bugs fixed
* The command names in the command palette will now be separated by spaces.
* The indexer will no longer show warnings when `file_get_contents` fails. This will be caught and a warning will be displayed in verbose mode instead, ensuring that it will no longer cause indexing to fail in its entirety.
* Fixed interfaces extending multiple other interfaces only having the first parent interface examined. This resulted in some class members not being listed if you implemented such an interface and could also result in files being scanned in the incorrect order (i.e. a child interface before a parent interface). This also resulted in a minor performance increase when fetching class information regarding inheritance as less queries are performed.

## 0.4.7
### Bugs fixed
* Bump the database version.

## 0.4.6
### Bugs fixed
* Fixed types sometimes showing up as [Object object] because the indexer was incorrectly saving an object instead of a string type.
* Fixed built-in functions not having an empty array serialized to the throws_serialized field, resulting in warnings when iterating over them, which in turn caused problems on the CoffeeScript side.

## 0.4.5
### Bugs fixed
* Fixed magic properties ending up in the index with a dollar sign in their name.
* Fixed built-in class constants being indexed by value instead of name. This also resulted in a constraint error in the database. (thanks to [@tocjent](https://github.com/tocjent))

## 0.4.4
### Bugs fixed
* Do not try to index structural elements that do not have a namespaced name, which can happen for anonymous PHP 7 classes.

## 0.4.3
### Bugs fixed
* Disable the xdebug nesting limit by setting it to -1, which was posing problems when resolving names.

## 0.4.2
### Bugs fixed
* Fixed the `indexes` folder not automatically being created.

## 0.4.1
### Bugs fixed
* Removed the 'old version' note from the README.

## 0.4.0
### Features and enhancements
* The PHP side now no longer uses Reflection, but a php-parser-based incremental indexer. This has the following advantages:
  * Composer is no longer required.
  * Autoloading is no longer required.
  * Multiple structural elements (classes) in a file will correctly end up in the index.
  * Parsing is inherently more correct, no more misbehaving corner cases or regex-based parsing.
  * Incremental indexing, no more composer classmap dumping on startup and a long indexing process.
  * Paves the road for the future more detailed parsing, analyzing and better performance. (As a side-effect, the PHP side can also be used for other editors than Atom.)
  * The package still requires PHP >= 5.4, but the code you're writing can be anything that php-parser 2 supports, which is currently PHP >= 5.2 up to (and including) PHP 7. Note that the CoffeeScript side has not changed and also does some regex-based parsing, which may still lack some functionality (especially with relation to PHP 7).

* Made it possible to make magic members static. This is not documented in phpDocumentor's documentation, but useful and also used by PHPStorm:

```php
/**
 * @method static void foo()
 * @property static Foo $test
 */
class Foo
{
    ...
}
```

* Added menu items for some commands.
* The progress bar will now actually show progress, which is useful for large projects.
* There is now a command available in the command palette to reindex your project (thanks to [@wcatron](https://github.com/wcatron)).
* Similarly, there is now also a command available to forcibly reindex the project (i.e. remove the entire database first).

### Bugs fixed
* A progress bar will no longer be shown for windows opened without a project.
* Methods returning `static` were not properly resolving to the current class if they were not overridden (they were resolving to the closest parent class that defined them).
* Project and file paths that contain spaces should no longer pose a problem (thanks to [@wcatron](https://github.com/wcatron)).

### Changes for developers
* Changes to the service
  * Constants and class properties will now retrieve their start line as well. These items not being available was previously manually worked around in the php-integrator-navigation package. This manual workaround is now present in the base package as CoffeeScript should not have to bend itself backwards to get this information because PHP Reflection didn't offer it.
  * `declaringStructure` will now also contain a `startLineMember` property that indicates the start line of the member in the structural element.
  * Retrieving types for the list of local variables has been disabled for now as the performance impact is too large in longer functions. It may be reactivated in a future release.
  * Constructor information is no longer retrieved when fetching the class list as the performance hit is too large. In fact, this was also the case before the new indexer, but then a cache file was used, which was never properly updated with new classes and was the result of the very long indexing process at start-up.
  * `deprecated` was renamed to `isDeprecated` for consistency.
  * `wasFound` was removed when fetching class (structural element) information as a failure status is now returned instead.
  * `class` was removed when fetching class (structural element) information as this information is already available in the `name` property.
  * `isMethod` and `isProperty` were removed as they are no longer necessary since members are now in separate associative arrays.
  * Added the more specific convenience methods `getClassMethodAt`, `getClassPropertyAt` and `getClassConstantAt`.
  * `isTrait`, `isInterface` and `isClass` were replaced with a single string `type` property.
  * Functions and methods no longer return a separate `parameters` and `optionals` property. The two have now been merged and provide more information (such as whether the parameters are a reference, variadic, optional, ...).

## 0.3.1
### Bugs fixed
* Fixed methods returning `static`, `self` or `$this` not properly having their full type deduced.
* Fixed inline type override annotations not being able to contain descriptions (e.g. `/** @var Class $foo Some description. */`).

## 0.3.0
### Features and enhancements
* Performance in general should be improved as the same parsing operations are now cached as often as possible.
* Types of variables that are assigned to basic type expressions will now be deduced properly instead of just having the expression as type:

```php
$var = 5;    // $var = int
$var = 5.0;  // $var = float
$var = true; // $var = bool
$var = "";   // $var = string
$var = [];   // $var = array
```

* If statements that have (only) an 'instanceof' of a variable in them will now be used to deduce the type of a variable. (More complex if statements will, at least for now, not be picked up.) For example (when using php-integrator-autocomplete-plus):

```php
if ($foo instanceof Foo) {
    $foo-> // Autocompletion for Foo will be shown.
}
```

* Closures will now be detected, for example (when using php-integrator-autocomplete-plus):

```php
$foo = function () {

};

$foo-> // Autocompletion for Closure, listing bind and bindTo.
```

* Added support for parsing magic properties and methods for classes, which will now also be returned (property-read and property-write are also returned):

```php
/**
 * @property Foo $someProperty A description.
 * @method magicFoo($param1, array $param2 = null) My magic method.
 */
class MyClass
{

}
```

### Bugs fixed
* The indexer will no longer try to index PHP files that don't belong to the project on save.
* Docblock parameters weren't being analyzed for deducing the type of local variables when in a global function.
* Types of variables that had their assigned values spread over multiple lines will now correctly have their type deduced.
* In rare cases, types could not be properly deduced, such as in `$a = ['b' => (new Foo())->]` (`Foo` would incorrectly not be returned as type).
* Only the relevant scopes will now be searched for the type of variables, previously all code was examined, even code outside the current scope.
* Descriptions after the `@var` tag, i.e. `@var Foo $foo My description` , will now be used as fall back when there is no short description present.
* The wrong type was sometimes shown for variables as their type was determined at the point they were found instead of the point at which they were requested.
* Functions that had no docblock were wrongly assumed to return 'void' (or 'self' in the case of constructors). This only applies to functions that do have a docblock, but no `@return` tag in the docblock.
* Support for the short annotation style, `/** @var FooClass */`, was dropped. The reason for this is that it's not supported by any IDE and is very specific to this package. It's also completely inflexible because it needs to be directly above the last assignment or other type deduction (such as a catch block) for it to be picked up incorrectly. The other annotation styles have none of these restrictions and also work in IDE's such as PHPStorm.

### Changes for developers
* Changes to the service
  * `determineFullClassName` will now return basic types as is, without prefixing them with the current namespace.
  * A new method `isBasicType` has been introduced, that will return true for basic types such as "int", "BOOL", "array", "string", ...
  * The `getDocParams` method has been removed. It was obsolete as the same information is already returned by `getClassInfo`. Also more caches can be reused by using `getClassInfo`.
  * The `autocomplete` method has been removed. It was confusing and also mostly obsolete as its functionality can already be mimicked through other methods (it was only internally used).
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

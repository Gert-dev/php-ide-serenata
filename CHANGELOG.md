## 0.2.0
* The PHP FileParser will no longer trip over class docblocks containing the pattern `class MyClass`.
* Fix several issues with autocompletion of `(new Foo())->` in corner cases such as inside arrays and function calls.
* When the initial PHP process that indexes the entire project fails or is killed, it will now be picked up and displayed as an error.
* There was no convenient visual indicator of when indexing failed, a label is now shown in the status bar if that is indeed the case.
* New service methods:
  * `getClassMethod` - Retrieves information about a specific class method.
  * `getClassProperty` - Retrieves information about a specific class property.
  * `getClassConstant` - Retrieves information about a specific class constant.
* Changes to the service:
  * Previously, `getCalledClass` always ignored the last element in a call stack, causing `$this->foo->b` to return the type of `foo` instead of `b`. Because this behavior is unexpected and undocumented, this no longer happens. To maintain this 'feature', a new parameter `ignoreLastElement` has been added that can be set to true to restore this behavior (i.e. it will return the type of `foo`). Setting it to false will return the type of `b` instead.
  * `getCalledClass` is now called `getCalledClassAt` to better indicate that it needs a buffer position.
  * `getClassMemberAt` will now return the correct member if a structure has a property and a method with the same name.
  * `getAvailableVariables` now returns an array with variables, containing the name and type of the variable (if found).
  * Several methods such as `getClassInfo` now take an additional parameter to make them execute asynchronously (a promise will be returned instead of the actual results).

## 0.1.0
* Initial release.

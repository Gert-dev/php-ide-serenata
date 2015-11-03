## 0.2.0
* The PHP FileParser will no longer trip over class docblocks containing the pattern `class MyClass`.
* Fix several issues with autocompletion of `(new Foo())->` in corner cases such as inside arrays and function calls.
* New service methods:
  * `getClassMethod` - Retrieves information about a specific class method.
  * `getClassProperty` - Retrieves information about a specific class property.
  * `getClassConstant` - Retrieves information about a specific class constant.
* Changes to the service:
  * `getClassMemberAt` will now return the correct member if a structure has a property and a method with the same name.
  * Several methods such as `getClassInfo` now take an additional parameter to make them execute asynchronously (a promise will be returned instead of the actual results).

## 0.1.0
* Initial release.

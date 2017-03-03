# php-integrator/atom-base
<p align="right">
:coffee:
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YKTNLZCRHMRTJ">Send me some coffee beans</a>
</p>

This package provides Atom integration for [PHP Integrator](https://gitlab.com/php-integrator/core) and exposes a service that other packages can use to provide additional functionality, such as autocompletion,
code navigation and tooltips. The user can then select his desired combination of functionalities from these other packages:
  * **[php-integrator-autocomplete-plus](https://github.com/php-integrator/atom-autocompletion)** - Provides intelligent PHP autocompletion in combination with autocomplete-plus.
  * **[php-integrator-navigation](https://github.com/php-integrator/atom-navigation)** - Provides code navigation and go to functionality.
  * **[php-integrator-tooltips](https://github.com/php-integrator/atom-tooltips)** - Shows tooltips with documentation.
  * **[php-integrator-annotations](https://github.com/php-integrator/atom-annotations)** - Shows annotations, such as for overridden methods and interface implementations.
  * **[php-integrator-call-tips](https://github.com/php-integrator/atom-call-tips)** - Shows call tips containing parameters in your code. (Complements the autocompletion package.)
  * **[php-integrator-refactoring](https://github.com/php-integrator/atom-refactoring)** - Provides basic refactoring capabilities.
  * **[php-integrator-linter](https://github.com/php-integrator/atom-linter)** - Shows indexing errors and problems with your code.

The following package also exists, but is currently looking for a new maintainer (see also its README):
  * **[php-integrator-symbol-viewer](https://github.com/tocjent/php-integrator-symbol-viewer)** - Provides a side panel listing class symbols with search and filter features.

Note that the heavy lifting is performed by the [PHP core](https://gitlab.com/php-integrator/core), which is automatically installed as _payload_ for this package and kept up to date automatically.

The source code was originally based on the php-autocomplete-plus code base, but has significantly diverged from it since then.

## What do I need to do to make it work?
See [the website](https://php-integrator.github.io/#what-do-i-need) as well as [the wiki](https://github.com/php-integrator/atom-base/wiki).

![GPLv3 Logo](http://gplv3.fsf.org/gplv3-127x51.png)

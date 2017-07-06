# php-integrator/atom-base
<p align="right">
:coffee:
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YKTNLZCRHMRTJ">Send me some coffee beans</a>
</p>

This package provides Atom integration for [PHP Integrator](https://gitlab.com/php-integrator/core) and exposes a service that other packages can use to provide additional functionality, such as autocompletion,
code navigation and tooltips.

This package already contains some functionality as of 3.0, including tooltips, signature help (call tips) and linting. Over time, all official add-on packages will be made redundant by reimplementation in the core. The following packages currently still need to be installed as add-on if you desire this functionality:

  * **[php-integrator-autocomplete-plus](https://github.com/php-integrator/atom-autocompletion)** - Provides intelligent PHP autocompletion in combination with autocomplete-plus.
  * **[php-integrator-navigation](https://github.com/php-integrator/atom-navigation)** - Provides code navigation and go to functionality.
  * **[php-integrator-annotations](https://github.com/php-integrator/atom-annotations)** - Shows annotations, such as for overridden methods and interface implementations.
  * **[php-integrator-refactoring](https://github.com/php-integrator/atom-refactoring)** - Provides basic refactoring capabilities.

Note that the heavy lifting is performed by the [PHP core](https://gitlab.com/php-integrator/core), which is automatically installed as _payload_ for this package and kept up to date automatically.

## What do I need to do to make it work?
See [the website](https://php-integrator.github.io/#what-do-i-need) as well as [the wiki](https://github.com/php-integrator/atom-base/wiki).

![GPLv3 Logo](http://gplv3.fsf.org/gplv3-127x51.png)

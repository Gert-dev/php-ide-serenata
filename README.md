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
See also [the website](https://php-integrator.github.io/#what-do-i-need) as well as [the wiki](https://github.com/Gert-dev/php-integrator-base/wiki).

![GPLv3 Logo](http://gplv3.fsf.org/gplv3-127x51.png)

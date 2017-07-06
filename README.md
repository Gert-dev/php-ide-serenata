# php-integrator/atom-base
<p align="right">
:coffee:
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YKTNLZCRHMRTJ">Send me some coffee beans</a>
</p>

## Release 3.0 approaching!
A new version of PHP Integrator is going to be released in the near future. This includes core 3.0 and new releases of the Atom packages to go alongside it.

Why is this important? Firstly, core 3.0 will require at least PHP 7.1 to **run**, i.e. you only need to specify a PHP 7.1 binary in the settings, but you can still write PHP >= 5.2 code. Also, in core 3.0 call-tips, tooltips and linting have been moved to the core (and merged into the base package). This means that, as soon as you upgrade to base 3.0, you will no longer need these packages. If you leave them installed, nothing bad will happen, as they will simply disable themselves.

### But I don't have PHP 7.1!
If you are not in a position to upgrade, legacy packages will be provided for Atom that will keep running the current version you are using. These will be named **php-integrator-*-legacy-php56**. Install these if you are still running PHP 5.6 or PHP 7.0.

To maintain the functionality of all the current packages:
* Install all the -legacy-php56 packages
* keep the regular php-integrator-tooltips, php-integrator-call-tips and php-integrator-linter installed

The last item is needed because, as soon as 3.0 is released, these packages (that no longer apply to 3.0) will be renamed to their -legacy-php56 counterpart as they will simply cease to exist for new releases.

### I'm ready for PHP 7.1!
Great! In this case you don't have to do anything; everything should continue working after the upgrade is released. You will also benefit from the new features and changes [in the core](https://gitlab.com/php-integrator/core/tags/3.0.0) and those specified in the changelogs of the various packages.

## Info
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

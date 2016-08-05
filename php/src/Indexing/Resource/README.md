# Generating documentation data
To generate the documentation data, first install [php-doc-parser](https://github.com/martinsik/php-doc-parser). Then
you can run (just use all the defaults for doc-parser):

```
./doc-parser
php -r "echo 'return ' . var_export(json_decode(file_get_contents('en_php_net.json'), true), true) . ';';" > documentation-data.php
```

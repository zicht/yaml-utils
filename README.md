## yaml-fix

this command will help helping upgrading to new symfony yaml by automatically fixing parse errors. It will do this by loading the yaml file with symfony/yaml:4.2 and when an ParseException is thrown it will send the file to an forked process that uses symfony/yaml:2.3 to dump the old syntax (as array) and send it back to the parent process that than can update the file with the new standard by dumping it with the symfony/yaml.


## example

to update all files in `/var/www/domains/site.nl`:

```
./bin/yaml-fix \
    --src /var/www/domains/site.nl \
    --exclude '/build|vendor|node_modules/' \
    --exclude-file '/(sass-lint|docker-compose|z2)\.yml/'
```

or to validate

```
./bin/yaml-fix \
    --dry-run \
    --src /var/www/domains/site.nl \
    --exclude '/build|vendor|node_modules/' \
    --exclude-file '/(sass-lint|docker-compose|z2)\.yml/'
```

when xdiff is install it will output something like:

```
...
error parsing file /var/www/domains/site.nl/src/Zicht/Bundle/ExampletBundle/Resources/config/routing.yml
@@ -1,3 +1,3 @@
 zicht_example:
-    resource: @ZichtExampleBundle/Controller/
-    type: annotation
\ No newline at end of file
+    resource: '@ZichtExampleBundle/Controller/'
+    type: annotation
...
```


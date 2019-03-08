## yaml-fix

this command will help helping upgrading to new symfony yaml by automatically fixing parse errors.

## example

```
./bin/yaml-utils fix \
    --src /var/www/domains/site.nl \
    --exclude '/build|vendor|node_modules/' \
    --exclude-file '/(sass-lint|docker-compose|z2)\.yml/'
```

# How to run

Assuming you have Docker engine installed:

```bash
host# make build
host# make composer-install
host# make phpstan
host# make phpunit
host# make bash
root@dockerContainer:/var/www# ./bin/console foo:hello
root@dockerContainer:/var/www# ./bin/console bar:hi
root@dockerContainer:/var/www# cat var/log/dev.log
root@dockerContainer:/var/www# XDEBUG_MODE=coverage ./bin/phpunit --coverage-text --coverage-filter=src/Bundle/ChainCommandBundle
```

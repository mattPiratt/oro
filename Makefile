.PHONY: phpunit phpstan build bash

# Build the Docker image
build:
	@docker build -t symfony-app .

phpunit:
	@docker run --rm -v $(PWD):/var/www symfony-app ./bin/phpunit

phpstan:
	@docker run --rm -v $(PWD):/var/www symfony-app ./vendor/phpstan/phpstan/phpstan analyse

bash:
	@docker run --rm -it -v $(PWD):/var/www symfony-app bash

composer-install: build
	@docker run --rm -v $(PWD):/var/www symfony-app composer install

# Default target
default: bash

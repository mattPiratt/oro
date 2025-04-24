.PHONY: phpunit phpstan build bash

# Build the Docker image
build:
	@docker build -t symfony-app .

# Run PHPUnit tests
phpunit:
	@docker run --rm -v $(PWD):/var/www symfony-app ./bin/phpunit

# Run PHPStan for static analysis
phpstan:
	@docker run --rm -v $(PWD):/var/www symfony-app ./vendor/phpstan/phpstan/phpstan analyse

# Open a bash shell in the container
bash:
	@docker run --rm -it -v $(PWD):/var/www symfony-app bash

# Install dependencies with Composer
composer-install: build
	@docker run --rm -v $(PWD):/var/www symfony-app composer install

# Default target
default: composer-install

Console Command Chaining - Symfony framework demo application
========================

# Task Description

Create a Symfony bundle that implements command chaining functionality. Other Symfony bundles in the application
may register their console commands to be members of a command chain. When a user runs the main command in a chain,
all other commands registered in this chain should be executed as well. Commands registered as chain members
can no longer be executed on their own.

Provide two other sample bundles to demonstrate the work of this application.

## Detailed Requirements

Using Symfony 5.4+ create a bundle called ChainCommandBundle.

Any other bundle installed in the same Symfony application should be able to create their console commands
(standard Symfony functionality) and register them as members of a command chain.

For example, some bundle introduces a console command, called `some:command`. Another bundle introduces
another command `another:command` and registers it as a member of command chain of `some:command`. Now, when a user
runs `php app/console some:command`, both `some:command` and `another:command` should be executed. Commands registered
as chain members can no longer be executed on their own, so `php app/console another:command` should produce an error.

For the demonstration purposes create two bundles FooBundle and BarBundle.

FooBundle should introduce the `foo:hello` console command. This command, if there were no other commands registered
in its chain, would produce the following output:

```bash
$ php app/console foo:hello
Hello from Foo!
```

BarBundle should introduce the `bar:hi` console command and register it as a member of `foo:hello`.
If this command were not registered as a member of a chain, it would produce the following output:

```bash
$ php app/console bar:hi
Hi from Bar!
```

Because `bar:hi` is registered as a member of `foo:hello` command chain, the actual output should now become:

```bash
$ php app/console foo:hello
Hello from Foo!
Hi from Bar!
```

```bash
$ php app/console bar:hi
Error: bar:hi command is a member of foo:hello command chain and cannot be executed on its own.
```

_(Extra points)_ In an ideal scenario FooBundle does not need to know anything about the chaining functionality.
Implementation done properly would allow command chaining functionality to be applied to commands introduced by any 3-rd
party bundle as well as the commands provided by the Symfony framework itself.

### Logging

Implement detailed logging for chained commands, so that after execution of `php app/console foo:hello`
the application log file will contain the following records (with actual time obviously):

```
[2016-01-29 09:08:31] foo:hello is a master command of a command chain that has registered member commands
[2016-01-29 09:08:31] bar:hi registered as a member of foo:hello command chain
[2016-01-29 09:08:31] Executing foo:hello command itself first:
[2016-01-29 09:08:32] Hello from Foo!
[2016-01-29 09:08:32] Executing foo:hello chain members:
[2016-01-29 09:08:32] Hi from Bar!
[2016-01-29 09:08:32] Execution of foo:hello chain completed.
```

### Tests

Create *unit tests* for all code in the ChainCommandBundle.
Create a *functional test* to check that command chaining functionality works.


> NB: FooBundle and BarBundle are just for the demo. You do not need to create any tests for them.

> NB: Do not forget to write *negative tests* as well.
> A positive test checks if a function behaves as expected with its expected input.
> A *negative test* checks if a function behaves as expected when something goes wrong. An expected result in this case
> could be a thrown exception, or returned boolean `false` - as defined by the developer of the tested method.

### Other Requirements

- PHP8
- Symfony 6.4+
- PSR-12
- http://symfony.com/doc/current/contributing/code/standards.html
- All classes and methods should be documented with phpdoc, containing a short description of what the intended purpose
  of this method or class is. Documenting function arguments and their types is optional when arguments types are
  defined in the code and the purpose of the argument is obvious or can be deduced from its name.

After the task is completed, provide a link to the repository on GitHub or BitBucket.

# Solution

To complete this advanced task, the following resources were used:

- https://symfony.com/doc/current/service_container/compiler_passes.html
  and https://symfony.com/doc/current/service_container/tags.html#create-a-compiler-pass
    - defining compiler pass in bundle
    - convention to format tag names
- https://symfony.com/doc/6.3/components/console/events.html#disable-commands-inside-listeners
    - exit code 113 for disabled commands
- https://symfony.com/doc/6.4/components/event_dispatcher.html#using-event-subscribers
    - listening to console commands events
- https://symfony.com/doc/current/console.html
    - using ApplicationTester class

What happens within the ChainCommandBundle :

- is providing event listener
- it listens to onConsoleCommand and onConsoleTerminate
- it uses internally ChainCommandRegistry, to know all about the relations between commands
- command execution can be accepted or disabled, as the task required
- calling "main" command, will also trigger calling "child" commands
- is registering a "ChainCommand" CompilerPass on Dependency Injection Container
- ChainCommand takes services tagged with 'chain_command.member' to register them in the ChainCommandRegistry
- uses Reflection mechanism to identify Console Commands that fits. CompilerPass is cached, thus should not affect
  performance of production env

This demo app complies with phpStan lvl 8.
Obvious parameters php-docs, and function descriptions where the function names are self-explanatory, are not provided.

This demo app uses "local" bundles, thus ChainCommandBundle tests are placed in the application tests directory.
Could be also located as part of ChainCommandBundle.

Tests provide 100% code coverage for ChainCommandBundle.

This application could/should be run as Docker container. Provided Makefile delivers `targets` that can be used to build
and run the application.

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

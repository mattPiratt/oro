<?php

declare(strict_types=1);

namespace ChainCommandBundle\Service;

use Psr\Log\LoggerInterface;


/**
 * Registry for command chains
 * Stores mappings between main commands and their member commands
 */
class ChainCommandRegistry
{
    /**
     * Stores main_command_name => [member_command_name1, member_command_name2, ...]
     * @var array <string, mixed>
     */
    private array $chains = [];

    /**
     * Stores member_command_name => main_command_name
     * @var array <string, string>
     */
    private array $memberToMainMap = [];


    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Registers a member command for a main command chain
     */
    public function addMemberCommand(string $mainCommandName, string $memberCommandName): void
    {
        if ($mainCommandName === $memberCommandName) {
            $this->logger->warning(
                'Attempted to register command {command} as a member of itself',
                ['command' => $mainCommandName]
            );
            return;
        }

        $this->chains[$mainCommandName][] = $memberCommandName;
        $this->memberToMainMap[$memberCommandName] = $mainCommandName;

        $this->logger->info(
            '{memberCommand} registered as a member of {mainCommand} command chain',
            [
                'memberCommand' => $memberCommandName,
                'mainCommand' => $mainCommandName
            ]
        );
    }

    /**
     * Checks if a given command name is registered as a member of any chain.
     */
    public function isMemberCommand(string $commandName): bool
    {
        return isset($this->memberToMainMap[$commandName]);
    }

    /**
     * Checks if a given command name is registered as a main command of a chain.
     */
    public function isMainCommand(string $commandName): bool
    {
        return isset($this->chains[$commandName]) && !empty($this->chains[$commandName]);
    }

    /**
     * Gets the names of all member commands registered for a specific main command
     *
     * @return array<int, string> An array of member command names, or an empty array if none are registered.
     */
    public function getMemberCommandNames(string $mainCommandName): array
    {
        return $this->chains[$mainCommandName] ?? [];
    }

    /**
     * Gets the name of the main command for a given member command.
     */
    public function getMainCommandNameForMember(string $memberCommandName): ?string
    {
        return $this->memberToMainMap[$memberCommandName] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Bundle\ChainCommandBundle\Service;

use ChainCommandBundle\Service\ChainCommandRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ChainCommandRegistryTest extends TestCase
{
    private ChainCommandRegistry $registry;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->registry = new ChainCommandRegistry($logger);
    }

    public function testAddMemberCommand(): void
    {
        $this->registry->addMemberCommand(
            mainCommandName: 'main:command',
            memberCommandName: 'member:command'
        );

        $this->assertTrue($this->registry->isMainCommand('main:command'));
        $this->assertTrue($this->registry->isMemberCommand('member:command'));
        $this->assertSame(
            expected: ['member:command'],
            actual: $this->registry->getMemberCommandNames('main:command')
        );
        $this->assertSame(
            expected: 'main:command',
            actual: $this->registry->getMainCommandNameForMember('member:command')
        );
    }

    public function testAddMultipleMemberCommands(): void
    {
        $this->registry->addMemberCommand('main:command', 'member1:command');
        $this->registry->addMemberCommand('main:command', 'member2:command');
        $this->registry->addMemberCommand('main:command', 'member3:command');

        $this->assertCount(
            expectedCount: 3,
            haystack: $this->registry->getMemberCommandNames('main:command')
        );
        $this->assertContains(
            needle: 'member1:command',
            haystack: $this->registry->getMemberCommandNames('main:command')
        );
        $this->assertContains(
            needle: 'member2:command',
            haystack: $this->registry->getMemberCommandNames('main:command')
        );
        $this->assertContains(
            needle: 'member3:command',
            haystack: $this->registry->getMemberCommandNames('main:command')
        );
    }

    public function testAddCommandToMultipleChains(): void
    {
        $this->registry->addMemberCommand('main1:command', 'member:command');
        $this->registry->addMemberCommand('main2:command', 'member:command');

        // the registry should only associate it with the last chain
        // (This is an expected limitation of the current implementation)
        $this->assertSame(
            expected: 'main2:command',
            actual: $this->registry->getMainCommandNameForMember('member:command')
        );
    }

    public function testIsMainCommandWithNoMembers(): void
    {
        $result = $this->registry->isMainCommand('non:existent');

        $this->assertFalse($result);
    }

    public function testIsMemberCommandWithNonMember(): void
    {
        $result = $this->registry->isMemberCommand('non:existent');

        $this->assertFalse($result);
    }

    public function testGetMemberCommandNamesWithNonExistentMain(): void
    {
        $result = $this->registry->getMemberCommandNames('non:existent');

        $this->assertEmpty($result);
    }

    public function testGetMainCommandNameForNonExistentMember(): void
    {
        $result = $this->registry->getMainCommandNameForMember('non:existent');

        $this->assertNull($result);
    }

    public function testAddSelfAsChainMember(): void
    {
        $this->registry->addMemberCommand('command:name', 'command:name');

        // the registry should ignore this request
        $this->assertFalse($this->registry->isMemberCommand('command:name'));
        $this->assertFalse($this->registry->isMainCommand('command:name'));
    }
}

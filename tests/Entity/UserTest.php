<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testTheAutomaticApiTokenSettingWhenAnUserIsCreated(): void
    {
        $user = new User();
        $this->assertNotNull($user->getApiToken());
    }

    public function testThanAnUserHasAtLeastOneRoleUser(): void
    {
        $user = new User();
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testAnException(): void
    {
        $this->expectException(\TypeError::class);

        $user = new User();
        $user->setPseudo([10]);
    }

    public function providePseudo(): \Generator
    {
        yield ['Thomas'];
        yield ['Eric'];
        yield ['Marie'];
    }

    /** @dataProvider providePseudo */
    public function testPseudoSetter(string $name): void
    {
        $user = new User();
        $user->setPseudo($name);

        $this->assertSame($name, $user->getPseudo());
    }
}

<?php

namespace App\Tests\Service;

use App\Entity\Candidate;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    public function testValidUser(): void
    {
        $user = $this->createValidUser();
        $manager = new UserManager();

        $this->assertTrue($manager->validate($user));
    }

    public function testUserWithoutEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is required.');

        $user = $this->createValidUser();
        $user->setEmail('');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is invalid.');

        $user = $this->createValidUser();
        $user->setEmail('candidate_email');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithoutFullName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name is required.');

        $user = $this->createValidUser();
        $user->setFirstName('');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithoutLastName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name is required.');

        $user = $this->createValidUser();
        $user->setLastName('');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithInvalidPhone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number is invalid.');

        $user = $this->createValidUser();
        $user->setPhone('phone-number');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithWeakPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least 8 characters.');

        $user = $this->createValidUser();
        $user->setPlainPassword('abc12');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserPasswordWithoutNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain at least one letter and one number.');

        $user = $this->createValidUser();
        $user->setPlainPassword('PasswordOnly');

        $manager = new UserManager();
        $manager->validate($user);
    }

    private function createValidUser(): Candidate
    {
        $user = new Candidate();
        $user->setEmail('candidate@example.com');
        $user->setFirstName('Aziz');
        $user->setLastName('Gharbi');
        $user->setPhone('+216 55123456');
        $user->setPlainPassword('Password123');

        return $user;
    }
}

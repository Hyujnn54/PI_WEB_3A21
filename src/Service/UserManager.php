<?php

namespace App\Service;

use App\Entity\Users;

class UserManager
{
    public function validate(Users $user): bool
    {
        $email = trim((string) $user->getEmail());
        if ($email === '') {
            throw new \InvalidArgumentException('Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email is invalid.');
        }

        if (trim((string) $user->getFirstName()) === '') {
            throw new \InvalidArgumentException('First name is required.');
        }

        if (trim((string) $user->getLastName()) === '') {
            throw new \InvalidArgumentException('Last name is required.');
        }

        $phone = trim((string) $user->getPhone());
        if ($phone === '') {
            throw new \InvalidArgumentException('Phone number is required.');
        }

        if (!preg_match('/^\+?[0-9 ]{8,15}$/', $phone)) {
            throw new \InvalidArgumentException('Phone number is invalid.');
        }

        $password = (string) $user->getPlainPassword();
        if ($password === '') {
            throw new \InvalidArgumentException('Password is required.');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must contain at least 8 characters.');
        }

        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).+$/', $password)) {
            throw new \InvalidArgumentException('Password must contain at least one letter and one number.');
        }

        return true;
    }
}

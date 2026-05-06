# User Test Unit

## Objectif

Créer un test unitaire pour le module User afin de vérifier les règles métier liées à la création et la validation d'un utilisateur.

Ce travail suit la logique du workshop Symfony :

1. Choisir une entité du projet.
2. Identifier les règles métier.
3. Créer un service métier.
4. Générer/créer un test unitaire avec PHPUnit.
5. Exécuter les tests et vérifier le résultat.

## Création du test

Commande indiquée dans le workshop :

```bash
php bin/console make:test
```

Choix à faire :

```text
TestCase
```

Nom de la classe de test :

```text
UserManagerTest
```

Structure utilisée dans le projet :

```text
src/
└── Service/
    └── UserManager.php

tests/
└── Service/
    └── UserManagerTest.php
```

## Service métier créé

Fichier créé :

```text
src/Service/UserManager.php
```

Le service contient une méthode :

```php
public function validate(Users $user): bool
```

Cette méthode retourne `true` si l'utilisateur est valide. Si une règle métier n'est pas respectée, elle lance une exception :

```php
\InvalidArgumentException
```

## Règles métier testées

Les règles métier choisies pour le module User sont :

1. L'email est obligatoire.
2. L'email doit être valide.
3. Le prénom est obligatoire.
4. Le nom est obligatoire.
5. Le numéro de téléphone doit être valide.
6. Le mot de passe doit contenir au moins 8 caractères.
7. Le mot de passe doit contenir au moins une lettre et un chiffre.

## Tests unitaires créés

Fichier créé :

```text
tests/Service/UserManagerTest.php
```

Tests implémentés :

```text
testValidUser
testUserWithoutEmail
testUserWithInvalidEmail
testUserWithoutFullName
testUserWithoutLastName
testUserWithInvalidPhone
testUserWithWeakPassword
testUserPasswordWithoutNumber
```

Le test `testValidUser` vérifie qu'un utilisateur correct est accepté.

Les autres tests vérifient que les données invalides sont refusées avec `InvalidArgumentException`.

## Exécution des tests

Commande utilisée pour tester uniquement le module User :

```bash
php bin/phpunit tests/Service/UserManagerTest.php --testdox
```

Résultat obtenu :

```text
User Manager (App\Tests\Service\UserManager)
 ✔ Valid user
 ✔ User without email
 ✔ User with invalid email
 ✔ User without full name
 ✔ User without last name
 ✔ User with invalid phone
 ✔ User with weak password
 ✔ User password without number

OK (8 tests, 15 assertions)
```

Commande utilisée pour exécuter toute la suite de tests :

```bash
php bin/phpunit
```

Résultat obtenu :

```text
OK (8 tests, 15 assertions)
```

## Conclusion

Les tests unitaires du module User valident correctement les règles métier définies.

Le module User est donc sécurisé au niveau de la validation de base avant la livraison.

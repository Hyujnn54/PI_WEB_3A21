# Event Test Unit

## Objectif

Créer un test unitaire pour le module Event afin de vérifier les règles métier liées à la création et la validation d'un événement de recrutement.

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
EventManagerTest
```

Structure utilisée dans le projet :

```text
src/
└── Service/
    └── EventManager.php

tests/
└── Service/
    └── EventManagerTest.php
```

## Service métier créé

Fichier créé :

```text
src/Service/EventManager.php
```

Le service contient une méthode :

```php
public function validate(Recruitment_event $event): bool
```

Cette méthode retourne `true` si l'événement est valide. Si une règle métier n'est pas respectée, elle lance une exception :

```php
\InvalidArgumentException
```

## Règles métier testées

Les règles métier choisies pour le module Event sont :

1. Un recruteur est obligatoire.
2. Le titre de l'événement est obligatoire.
3. Le titre doit contenir au moins 3 caractères.
4. Le titre ne doit pas dépasser 255 caractères.
5. La description est obligatoire.
6. La description doit contenir au moins 10 caractères.
7. Le type d'événement est obligatoire.
8. Le type d'événement doit être valide.
9. La localisation est obligatoire.
10. La localisation doit contenir au moins 2 caractères.
11. La date de l'événement est obligatoire.
12. La date de l'événement doit être dans le futur.
13. La capacité doit être au minimum 1.
14. La capacité ne doit pas dépasser 1000.
15. Le lien de réunion doit être une URL valide s'il est renseigné.

Valeurs métier utilisées pour le type d'événement :

```text
Workshop, Hiring Day, Webinar
```

## Tests unitaires créés

Fichier créé :

```text
tests/Service/EventManagerTest.php
```

Tests implémentés :

```text
testValidEvent
testValidWebinarWithMeetLink
testEventWithoutRecruiter
testEventWithoutTitle
testEventWithShortTitle
testEventWithShortDescription
testEventWithInvalidEventType
testEventWithoutLocation
testEventWithPastDate
testEventWithInvalidCapacity
testEventWithCapacityGreaterThanLimit
testEventWithInvalidMeetLink
```

Les tests `testValidEvent` et `testValidWebinarWithMeetLink` vérifient que les événements corrects sont acceptés.

Les autres tests vérifient que les données invalides sont refusées avec `InvalidArgumentException`.

## Exécution des tests

Commande utilisée pour tester uniquement le module Event :

```bash
php bin/phpunit tests/Service/EventManagerTest.php --testdox
```

Résultat obtenu :

```text
Event Manager (App\Tests\Service\EventManager)
 ✔ Valid event
 ✔ Valid webinar with meet link
 ✔ Event without recruiter
 ✔ Event without title
 ✔ Event with short title
 ✔ Event with short description
 ✔ Event with invalid event type
 ✔ Event without location
 ✔ Event with past date
 ✔ Event with invalid capacity
 ✔ Event with capacity greater than limit
 ✔ Event with invalid meet link

OK (12 tests, 22 assertions)
```

Commande utilisée pour exécuter toute la suite de tests :

```bash
php bin/phpunit
```

Résultat obtenu après l'ajout des tests User, JobOffer, JobApplication, Interview et Event :

```text
OK (48 tests, 89 assertions)
```

## Conclusion

Les tests unitaires du module Event valident correctement les règles métier définies.

Le module Event est donc sécurisé au niveau de la validation de base avant la livraison.

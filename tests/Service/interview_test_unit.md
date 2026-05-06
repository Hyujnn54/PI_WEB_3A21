# Interview Test Unit

## Objectif

Créer un test unitaire pour le module Interview afin de vérifier les règles métier liées à la planification d'un entretien.

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
InterviewManagerTest
```

Structure utilisée dans le projet :

```text
src/
└── Service/
    └── InterviewManager.php

tests/
└── Service/
    └── InterviewManagerTest.php
```

## Service métier créé

Fichier créé :

```text
src/Service/InterviewManager.php
```

Le service contient une méthode :

```php
public function validate(Interview $interview): bool
```

Cette méthode retourne `true` si l'entretien est valide. Si une règle métier n'est pas respectée, elle lance une exception :

```php
\InvalidArgumentException
```

## Règles métier testées

Les règles métier choisies pour le module Interview sont :

1. Une candidature est obligatoire.
2. Un recruteur est obligatoire.
3. La date et l'heure de l'entretien sont obligatoires.
4. La date de l'entretien doit être dans le futur.
5. L'entretien ne peut pas être planifié à plus de 90 jours.
6. La durée doit être entre 15 et 240 minutes.
7. Le mode doit être `online` ou `onsite`.
8. Le lien de réunion est obligatoire pour un entretien en ligne.
9. Le lien de réunion doit être une URL `http(s)` valide.
10. La localisation est obligatoire pour un entretien sur site.
11. Les notes ne doivent pas dépasser 1000 caractères et doivent contenir des caractères valides.
12. Le statut de l'entretien doit être valide.

Valeurs métier utilisées pour le statut :

```text
SCHEDULED, COMPLETED, CANCELLED
```

## Tests unitaires créés

Fichier créé :

```text
tests/Service/InterviewManagerTest.php
```

Tests implémentés :

```text
testValidOnlineInterview
testValidOnsiteInterview
testInterviewWithoutApplication
testInterviewWithoutRecruiter
testInterviewWithPastDate
testInterviewScheduledTooFarAhead
testInterviewWithInvalidDuration
testOnlineInterviewWithoutMeetingLink
testOnlineInterviewWithInvalidMeetingLink
testOnsiteInterviewWithoutLocation
testInterviewWithInvalidNotes
testInterviewWithInvalidStatus
```

Les tests `testValidOnlineInterview` et `testValidOnsiteInterview` vérifient que les entretiens corrects sont acceptés.

Les autres tests vérifient que les données invalides sont refusées avec `InvalidArgumentException`.

## Exécution des tests

Commande utilisée pour tester uniquement le module Interview :

```bash
php bin/phpunit tests/Service/InterviewManagerTest.php --testdox
```

Résultat obtenu :

```text
Interview Manager (App\Tests\Service\InterviewManager)
 ✔ Valid online interview
 ✔ Valid onsite interview
 ✔ Interview without application
 ✔ Interview without recruiter
 ✔ Interview with past date
 ✔ Interview scheduled too far ahead
 ✔ Interview with invalid duration
 ✔ Online interview without meeting link
 ✔ Online interview with invalid meeting link
 ✔ Onsite interview without location
 ✔ Interview with invalid notes
 ✔ Interview with invalid status

OK (12 tests, 22 assertions)
```

Commande utilisée pour exécuter toute la suite de tests :

```bash
php bin/phpunit
```

Résultat obtenu après l'ajout des tests User, JobOffer, JobApplication et Interview :

```text
OK (36 tests, 67 assertions)
```

## Conclusion

Les tests unitaires du module Interview valident correctement les règles métier définies.

Le module Interview est donc sécurisé au niveau de la validation de base avant la livraison.

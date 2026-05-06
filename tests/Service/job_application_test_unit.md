# Job Application Test Unit

## Objectif

Créer un test unitaire pour le module JobApplication afin de vérifier les règles métier liées à la soumission d'une candidature.

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
JobApplicationManagerTest
```

Structure utilisée dans le projet :

```text
src/
└── Service/
    └── JobApplicationManager.php

tests/
└── Service/
    └── JobApplicationManagerTest.php
```

## Service métier créé

Fichier créé :

```text
src/Service/JobApplicationManager.php
```

Le service contient une méthode :

```php
public function validate(Job_application $application): bool
```

Cette méthode retourne `true` si la candidature est valide. Si une règle métier n'est pas respectée, elle lance une exception :

```php
\InvalidArgumentException
```

## Règles métier testées

Les règles métier choisies pour le module JobApplication sont :

1. Une offre d'emploi est obligatoire.
2. Un candidat est obligatoire.
3. Le numéro de téléphone est obligatoire et doit respecter le format tunisien.
4. La lettre de motivation est obligatoire.
5. La lettre de motivation doit contenir entre 50 et 2000 caractères.
6. Un CV est obligatoire.
7. Le statut de la candidature doit être valide.
8. La date de candidature ne peut pas être dans le futur.

Valeurs métier utilisées pour le statut :

```text
SUBMITTED, IN_REVIEW, SHORTLISTED, REJECTED, INTERVIEW, HIRED
```

Format du téléphone accepté :

```text
+216XXXXXXXX, 216XXXXXXXX, 0XXXXXXXX ou XXXXXXXX
```

## Tests unitaires créés

Fichier créé :

```text
tests/Service/JobApplicationManagerTest.php
```

Tests implémentés :

```text
testValidJobApplication
testJobApplicationWithoutOffer
testJobApplicationWithoutCandidate
testJobApplicationWithInvalidPhone
testJobApplicationWithShortCoverLetter
testJobApplicationWithoutCv
testJobApplicationWithInvalidStatus
testJobApplicationWithFutureApplicationDate
```

Le test `testValidJobApplication` vérifie qu'une candidature correcte est acceptée.

Les autres tests vérifient que les données invalides sont refusées avec `InvalidArgumentException`.

## Exécution des tests

Commande utilisée pour tester uniquement le module JobApplication :

```bash
php bin/phpunit tests/Service/JobApplicationManagerTest.php --testdox
```

Résultat obtenu :

```text
Job Application Manager (App\Tests\Service\JobApplicationManager)
 ✔ Valid job application
 ✔ Job application without offer
 ✔ Job application without candidate
 ✔ Job application with invalid phone
 ✔ Job application with short cover letter
 ✔ Job application without cv
 ✔ Job application with invalid status
 ✔ Job application with future application date

OK (8 tests, 15 assertions)
```

Commande utilisée pour exécuter toute la suite de tests :

```bash
php bin/phpunit
```

Résultat obtenu après l'ajout des tests User, JobOffer et JobApplication :

```text
OK (24 tests, 45 assertions)
```

## Conclusion

Les tests unitaires du module JobApplication valident correctement les règles métier définies.

Le module JobApplication est donc sécurisé au niveau de la validation de base avant la livraison.

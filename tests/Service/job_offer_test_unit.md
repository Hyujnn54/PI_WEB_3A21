# Job Offer Test Unit

## Objectif

Créer un test unitaire pour le module JobOffer afin de vérifier les règles métier liées à la création et la validation d'une offre d'emploi.

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
JobOfferManagerTest
```

Structure utilisée dans le projet :

```text
src/
└── Service/
    └── JobOfferManager.php

tests/
└── Service/
    └── JobOfferManagerTest.php
```

## Service métier créé

Fichier créé :

```text
src/Service/JobOfferManager.php
```

Le service contient une méthode :

```php
public function validate(Job_offer $offer): bool
```

Cette méthode retourne `true` si l'offre d'emploi est valide. Si une règle métier n'est pas respectée, elle lance une exception :

```php
\InvalidArgumentException
```

## Règles métier testées

Les règles métier choisies pour le module JobOffer sont :

1. Le titre est obligatoire.
2. Le titre doit contenir entre 3 et 150 caractères valides.
3. Le type de contrat doit être valide.
4. Le statut doit être valide.
5. La description doit contenir entre 10 et 1000 caractères valides.
6. La localisation est obligatoire.
7. La date limite doit être supérieure à la date actuelle.

Valeurs métier utilisées :

```text
Types de contrat : CDI, CDD, Internship, Freelance, Full-time, Part-time, Remote Contract
Statuts : open, paused, closed
```

## Tests unitaires créés

Fichier créé :

```text
tests/Service/JobOfferManagerTest.php
```

Tests implémentés :

```text
testValidJobOffer
testJobOfferWithoutTitle
testJobOfferWithInvalidTitle
testJobOfferWithInvalidContractType
testJobOfferWithInvalidStatus
testJobOfferWithShortDescription
testJobOfferWithoutLocation
testJobOfferWithPastDeadline
```

Le test `testValidJobOffer` vérifie qu'une offre d'emploi correcte est acceptée.

Les autres tests vérifient que les données invalides sont refusées avec `InvalidArgumentException`.

## Exécution des tests

Commande utilisée pour tester uniquement le module JobOffer :

```bash
php bin/phpunit tests/Service/JobOfferManagerTest.php --testdox
```

Résultat obtenu :

```text
Job Offer Manager (App\Tests\Service\JobOfferManager)
 ✔ Valid job offer
 ✔ Job offer without title
 ✔ Job offer with invalid title
 ✔ Job offer with invalid contract type
 ✔ Job offer with invalid status
 ✔ Job offer with short description
 ✔ Job offer without location
 ✔ Job offer with past deadline

OK (8 tests, 15 assertions)
```

Commande utilisée pour exécuter toute la suite de tests :

```bash
php bin/phpunit
```

Résultat obtenu après l'ajout des tests User et JobOffer :

```text
OK (16 tests, 30 assertions)
```

## Conclusion

Les tests unitaires du module JobOffer valident correctement les règles métier définies.

Le module JobOffer est donc sécurisé au niveau de la validation de base avant la livraison.

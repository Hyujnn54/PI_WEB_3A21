# Doctrine Doctor Report

## Objectif

Utiliser Doctrine Doctor dans le profiler Symfony afin de detecter les problemes Doctrine importants avant la livraison du projet.

Le travail a ete fait en deux etapes :

1. Capturer l'etat avant correction avec une capture d'ecran Doctrine Doctor.
2. Corriger les problemes critiques et les avertissements les plus importants.

## Installation

Doctrine Doctor a ete installe en environnement de developpement et active dans Symfony.

Fichiers concernes :

```text
composer.json
config/bundles.php
```

## Corrections Critiques

Les problemes critiques ont ete traites en priorite.

Corrections principales :

- Correction de la configuration timezone entre Symfony, PHP et MySQL.
- Configuration de MySQL pour utiliser une timezone compatible.
- Correction du mapping Doctrine de `Application_status_history.changed_by`.
- Renommage de la colonne base de donnees `changed_by` vers `changed_by_id`.
- Ajout et execution d'une migration ciblee pour corriger cette relation.

Resultat : Doctrine Doctor ne signale plus d'erreurs critiques sur les pages verifiees.

## Avertissements Importants Corriges

Les avertissements les plus importants etaient lies a l'integrite des donnees et aux performances.

Corrections principales :

- Alignement entre les suppressions Doctrine ORM et les contraintes `ON DELETE CASCADE` en base de donnees.
- Correction de plusieurs relations afin d'eviter des suppressions automatiques non controlees.
- Ajout de migrations ciblees pour mettre a jour uniquement les cles etrangeres concernees.
- Remplacement de certains `findAll()` par des `count()` ou des requetes limitees.
- Ajout de limites sur certaines listes admin afin d'eviter le chargement complet des tables.

Exemples de modules corriges :

- Job application
- Candidate skill
- Event registration
- Event review
- Interview
- Interview feedback
- Application status history
- Job offer warning

## Avertissements Conserves

Certains avertissements mineurs ont ete conserves volontairement.

Exemple :

```text
Offer_skill.offer_id
```

Cette relation correspond a des donnees dependantes d'une offre. Le comportement de suppression en cascade peut donc etre acceptable dans ce contexte.

## Verification Finale

Apres les corrections :

```bash
php bin/console doctrine:schema:validate --skip-sync
vendor/bin/phpstan analyse
php bin/phpunit
```

Resultats :

```text
Doctrine mapping : OK
PHPStan : OK
PHPUnit : OK (48 tests, 89 assertions)
Doctrine Doctor : 0 erreur critique
```

## Remarque Importante

Les anciennes migrations du projet sont encore marquees comme `new` dans Doctrine Migrations.

Pour eviter d'executer accidentellement d'anciennes migrations deja presentes en base, les corrections ont ete appliquees avec des migrations ciblees.

Il ne faut donc pas lancer directement :

```bash
php bin/console doctrine:migrations:migrate
```

## Conclusion

La phase Doctrine Doctor est validee pour le workshop.

Les problemes critiques ont ete corriges, les avertissements importants ont ete traites, et les avertissements mineurs restants ont ete documentes comme acceptables pour le contexte du projet.

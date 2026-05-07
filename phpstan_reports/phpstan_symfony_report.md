# PHPStan Symfony Report

## Objectif

Utiliser PHPStan avec l'extension Symfony pour analyser statiquement le projet, sauvegarder les résultats avant correction, corriger les erreurs étape par étape, puis sauvegarder les résultats après correction.

Ce travail suit la logique du workshop PHPStan Symfony :

1. Installer PHPStan.
2. Vérifier l'installation.
3. Lancer une première analyse sans configuration.
4. Créer le fichier `phpstan.neon`.
5. Relancer l'analyse avec configuration.
6. Corriger les erreurs détectées.
7. Relancer PHPStan après les corrections.
8. Comparer les résultats avant et après.

## Installation

Commande utilisée :

```bash
composer require --dev phpstan/phpstan phpstan/phpstan-symfony --no-interaction
```

Packages installés :

```text
phpstan/phpstan 2.1.54
phpstan/phpstan-symfony 2.0.15
```

## Vérification

Commande utilisée :

```bash
vendor/bin/phpstan --version
```

Résultat :

```text
PHPStan - PHP Static Analysis Tool 2.1.54
```

## Première analyse sans configuration

Commande utilisée :

```bash
vendor/bin/phpstan analyse src
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_before_config_analysis.txt
```

Résultat obtenu au niveau par défaut :

```text
[OK] No errors
```

Remarque : PHPStan indique que le niveau par défaut est `0`, donc cette première analyse effectue uniquement les vérifications les plus basiques.

## Configuration PHPStan Symfony

Fichier créé :

```text
phpstan.neon
```

Configuration utilisée :

```neon
includes:
    - vendor/phpstan/phpstan-symfony/extension.neon
    - vendor/phpstan/phpstan-symfony/rules.neon

parameters:
    level: 5
    paths:
        - src
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
```

Cette configuration active :

1. Un niveau d'analyse intermédiaire : `level 5`.
2. L'analyse du dossier `src`.
3. L'extension PHPStan Symfony.
4. Les règles spécifiques Symfony.
5. Le conteneur Symfony généré dans `var/cache/dev`.

## Analyse avant correction

Commande utilisée :

```bash
vendor/bin/phpstan analyse
```

Résultats sauvegardés dans :

```text
phpstan_reports/phpstan_before_fixes_level5.txt
phpstan_reports/phpstan_before_fixes_level5.json
phpstan_reports/phpstan_before_fixes_level5_summary.md
```

Résultat global avant correction :

```text
Found 92 errors
```

Résumé par fichier :

```text
src/Command/GenerateEntitiesCommand.php: 2
src/Controller/BackOfficeController.php: 14
src/Controller/FaceAuthController.php: 1
src/Controller/FrontPortalController.php: 15
src/Controller/LoginController.php: 1
src/Controller/Management/JobApplication/CandidateApplicationController.php: 6
src/Entity/Candidate_skill.php: 1
src/Entity/Interview.php: 7
src/Entity/Job_application.php: 3
src/Entity/Job_offer.php: 2
src/Entity/Job_offer_comment.php: 21
src/Entity/Job_offer_warning.php: 1
src/Entity/Recruitment_event.php: 2
src/Form/Filter/JobOfferFilterType.php: 2
src/Repository/Job_offerRepository.php: 2
src/Repository/UsersRepository.php: 1
src/Service/CandidateOfferMatchingService.php: 2
src/Service/CommentAnalyzerService.php: 1
src/Service/Interview/InterviewMapLookupService.php: 3
src/Service/Interview/InterviewReminderDispatcher.php: 1
src/Service/JobApplication/ApplicationAiRankingService.php: 3
src/Service/LuxandFaceService.php: 1
```

## Vérification des tests unitaires

Commande utilisée :

```bash
php bin/phpunit
```

Résultat :

```text
OK (48 tests, 89 assertions)
```

## Correction 1 : services

Analyse ciblée avant correction :

```bash
vendor/bin/phpstan analyse src/Service
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_before_fixes_services_level5.txt
```

Résultat avant correction des services :

```text
Found 11 errors
```

Fichiers corrigés :

```text
src/Service/CandidateOfferMatchingService.php
src/Service/CommentAnalyzerService.php
src/Service/Interview/InterviewMapLookupService.php
src/Service/Interview/InterviewReminderDispatcher.php
src/Service/JobApplication/ApplicationAiRankingService.php
src/Service/LuxandFaceService.php
```

Types de corrections effectuées :

1. Suppression de `??` inutiles lorsque PHPStan connaît déjà la clé du tableau.
2. Suppression de `is_array()` inutiles lorsque la méthode retourne déjà un tableau.
3. Suppression de `isset()` inutile sur un groupe capturé par expression régulière.
4. Correction de l'utilisation de Doctrine QueryBuilder dans le service de rappels d'entretien.

Analyse ciblée après correction :

```bash
vendor/bin/phpstan analyse src/Service
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_after_service_fixes_level5.txt
```

Résultat après correction des services :

```text
[OK] No errors
```

Résultat global après correction des services :

```text
Before: 92 errors
After service fixes: 81 errors
```

Vérification PHPUnit après correction :

```text
OK (48 tests, 89 assertions)
```

## Correction 2 : repositories et formulaire de filtre

Analyse ciblée avant correction :

```bash
vendor/bin/phpstan analyse src/Repository src/Form
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_before_fixes_repository_form_level5.txt
```

Résultat avant correction :

```text
Found 5 errors
```

Fichiers corrigés :

```text
src/Form/Filter/JobOfferFilterType.php
src/Repository/Job_offerRepository.php
src/Repository/UsersRepository.php
```

Types de corrections effectuées :

1. Utilisation de l'expression Doctrine via `$qb->expr()` au lieu d'une méthode non définie sur `QueryInterface`.
2. Suppression de conditions toujours vraies dans le calcul des statistiques.
3. Nettoyage d'une condition de recherche redondante.

Analyse ciblée après correction :

```bash
vendor/bin/phpstan analyse src/Repository src/Form
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_after_repository_form_fixes_level5.txt
```

Résultat après correction :

```text
[OK] No errors
```

Résultat global après cette correction :

```text
Before: 92 errors
After service fixes: 81 errors
After repository/form fixes: 76 errors
```

Vérification PHPUnit après correction :

```text
OK (48 tests, 89 assertions)
```

## Correction 3 : entités

Analyse ciblée avant correction :

```bash
vendor/bin/phpstan analyse src/Entity
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_before_fixes_entities_level5.txt
```

Résultat avant correction :

```text
Found 37 errors
```

Fichiers corrigés :

```text
src/Entity/Candidate_skill.php
src/Entity/Interview.php
src/Entity/Job_application.php
src/Entity/Job_offer.php
src/Entity/Job_offer_comment.php
src/Entity/Job_offer_warning.php
src/Entity/Recruitment_event.php
```

Types de corrections effectuées :

1. Suppression de `isset()` et `??` inutiles sur des propriétés non nullables.
2. Ajout de getters/setters manquants pour les propriétés Doctrine de `Job_offer_comment`.
3. Initialisation des relations `Collection` avec `ArrayCollection`.
4. Ajout de getters pour les collections Doctrine utilisées par les relations.

Analyse ciblée après correction :

```bash
vendor/bin/phpstan analyse src/Entity
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_after_entity_fixes_level5.txt
```

Résultat après correction :

```text
[OK] No errors
```

Résultat global après cette correction :

```text
Before: 92 errors
After service fixes: 81 errors
After repository/form fixes: 76 errors
After entity fixes: 39 errors
```

Vérification PHPUnit après correction :

```text
OK (48 tests, 89 assertions)
```

## Correction 4 : commandes

Analyse ciblée avant correction :

```bash
vendor/bin/phpstan analyse src/Command
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_before_fixes_command_level5.txt
```

Résultat avant correction :

```text
Found 2 errors
```

Fichier corrigé :

```text
src/Command/GenerateEntitiesCommand.php
```

Types de corrections effectuées :

1. Suppression d'une dépendance `Filesystem` injectée mais inutilisée.
2. Correction des tags PHPDoc `@param` pour correspondre aux paramètres réels.

Analyse ciblée après correction :

```bash
vendor/bin/phpstan analyse src/Command
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_after_command_fixes_level5.txt
```

Résultat après correction :

```text
[OK] No errors
```

Résultat global après cette correction :

```text
Before: 92 errors
After service fixes: 81 errors
After repository/form fixes: 76 errors
After entity fixes: 39 errors
After command fixes: 37 errors
```

Vérification PHPUnit après correction :

```text
OK (48 tests, 89 assertions)
```

## Correction 5 : contrôleurs

Analyse ciblée avant correction :

```bash
vendor/bin/phpstan analyse src/Controller
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_before_fixes_controllers_level5.txt
```

Résultat avant correction :

```text
Found 37 errors
```

Fichiers corrigés :

```text
src/Controller/BackOfficeController.php
src/Controller/FaceAuthController.php
src/Controller/FrontPortalController.php
src/Controller/LoginController.php
src/Controller/Management/JobApplication/CandidateApplicationController.php
```

Types de corrections effectuées :

1. Suppression des `instanceof` inutiles lorsque PHPStan connaissait déjà le type exact.
2. Suppression des `method_exists()` inutiles sur des méthodes existantes.
3. Suppression des `??` inutiles sur des clés de tableau garanties.
4. Suppression de méthodes privées inutilisées.
5. Remplacement d'une comparaison impossible avec `false` par une création directe de `DateTimeImmutable`.

Analyse ciblée après correction :

```bash
vendor/bin/phpstan analyse src/Controller
```

Résultat sauvegardé dans :

```text
phpstan_reports/phpstan_after_controller_fixes_level5.txt
```

Résultat après correction :

```text
[OK] No errors
```

Vérification PHPUnit après correction :

```text
OK (48 tests, 89 assertions)
```

## Analyse après correction

Commande utilisée :

```bash
vendor/bin/phpstan analyse
```

Résultats sauvegardés dans :

```text
phpstan_reports/phpstan_after_fixes_level5.txt
phpstan_reports/phpstan_after_fixes_level5.json
phpstan_reports/phpstan_after_fixes_level5_summary.md
```

Résultat global après correction :

```text
[OK] No errors
```

Progression globale :

```text
Before fixes: 92 errors
After service fixes: 81 errors
After repository/form fixes: 76 errors
After entity fixes: 39 errors
After command fixes: 37 errors
After controller fixes: 0 errors
```

Vérification finale PHPUnit :

```bash
php bin/phpunit
```

Résultat :

```text
OK (48 tests, 89 assertions)
```

## Conclusion

PHPStan Symfony est configuré au niveau 5 et l'analyse complète du dossier `src` ne retourne plus aucune erreur.

Les résultats avant correction et après correction sont sauvegardés dans le dossier `phpstan_reports`.

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 10:51 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rh`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` bigint(20) NOT NULL,
  `assigned_area` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `assigned_area`) VALUES
(1, 'SUPER ADMIN'),
(2, 'NORMAL ADMIN'),
(5, 'NORMAL ADMIN');

-- --------------------------------------------------------

--
-- Table structure for table `application_status_history`
--

CREATE TABLE `application_status_history` (
  `id` bigint(20) NOT NULL,
  `application_id` bigint(20) NOT NULL,
  `status` enum('SUBMITTED','IN_REVIEW','SHORTLISTED','REJECTED','INTERVIEW','HIRED','ARCHIVED','UNARCHIVED') NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `changed_by` bigint(20) NOT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_status_history`
--

INSERT INTO `application_status_history` (`id`, `application_id`, `status`, `changed_at`, `changed_by`, `note`) VALUES
(1, 1, 'SUBMITTED', '2026-04-05 19:58:46', 3, 'Initial submission from portal.'),
(2, 1, 'IN_REVIEW', '2026-04-05 19:59:12', 4, 'Status updated by recruiter.'),
(3, 1, 'SHORTLISTED', '2026-04-05 20:06:40', 4, 'I\'ve shortlisted this application for the next step. Additional note: hey'),
(4, 1, 'IN_REVIEW', '2026-04-05 20:06:50', 4, 'I\'m reviewing the application.'),
(5, 1, 'REJECTED', '2026-04-05 20:08:19', 4, 'I\'ve rejected this application after review.'),
(6, 1, 'SUBMITTED', '2026-04-05 20:09:45', 4, 'I\'m marking the application as submitted.'),
(7, 1, 'IN_REVIEW', '2026-04-05 20:10:50', 4, 'I\'m reviewing the application.'),
(8, 1, 'SUBMITTED', '2026-04-05 20:11:09', 4, 'I\'m marking the application as submitted.'),
(9, 1, 'IN_REVIEW', '2026-04-05 20:12:42', 4, 'I\'m reviewing the application.'),
(10, 2, 'IN_REVIEW', '2026-04-05 21:25:51', 4, 'I\'m reviewing the application.'),
(11, 2, 'ARCHIVED', '2026-04-05 21:26:26', 1, 'dqsdsDSQdSQ'),
(12, 2, 'SHORTLISTED', '2026-04-06 11:21:45', 4, 'I\'ve shortlisted this application for the next step.');

-- --------------------------------------------------------

--
-- Table structure for table `candidate`
--

CREATE TABLE `candidate` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate`
--

INSERT INTO `candidate` (`id`, `user_id`, `location`, `education_level`, `experience_years`, `cv_path`) VALUES
(3, 3, 'ben arous', 'college', 4, 'uploads\\cvs\\96a99fad-99ff-405e-a55f-7975254b9521_Aziz_Abidi_CV.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_skill`
--

CREATE TABLE `candidate_skill` (
  `id` bigint(20) NOT NULL,
  `candidate_id` bigint(20) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `level` enum('BEGINNER','INTERMEDIATE','ADVANCED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_skill`
--

INSERT INTO `candidate_skill` (`id`, `candidate_id`, `skill_name`, `level`) VALUES
(20, 3, 'Java', 'ADVANCED'),
(21, 3, 'Spring Boot', 'INTERMEDIATE'),
(22, 3, 'SQL', 'ADVANCED'),
(23, 3, 'Angular', 'BEGINNER'),
(24, 3, 'Docker', 'INTERMEDIATE'),
(25, 3, 'CSS', 'INTERMEDIATE'),
(26, 3, 'React', 'ADVANCED'),
(27, 3, 'Type script', 'INTERMEDIATE'),
(28, 3, 'Java (computer programming)', 'INTERMEDIATE');

-- --------------------------------------------------------

--
-- Table structure for table `event_registration`
--

CREATE TABLE `event_registration` (
  `id` bigint(20) NOT NULL,
  `event_id` bigint(20) NOT NULL,
  `candidate_id` bigint(20) NOT NULL,
  `registered_at` datetime DEFAULT current_timestamp(),
  `attendance_status` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_registration`
--

INSERT INTO `event_registration` (`id`, `event_id`, `candidate_id`, `registered_at`, `attendance_status`) VALUES
(1, 1, 3, '2026-03-02 13:00:00', 'CONFIRMED'),
(2, 2, 3, '2026-02-10 10:00:00', 'CONFIRMED'),
(3, 3, 3, '2026-03-02 02:26:38', 'REJECTED'),
(4, 4, 3, '2026-03-02 11:39:41', 'CANCELLED'),
(7, 8, 3, '2026-03-02 11:41:55', 'CONFIRMED'),
(8, 9, 3, '2026-03-02 11:47:16', 'CANCELLED');

-- --------------------------------------------------------

--
-- Table structure for table `event_review`
--

CREATE TABLE `event_review` (
  `id` bigint(20) NOT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `candidate_id` bigint(20) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_review`
--

INSERT INTO `event_review` (`id`, `event_id`, `candidate_id`, `rating`, `comment`, `created_at`) VALUES
(2, 2, 3, 5, '3malet barcha jawww', '2026-03-02 10:43:51');

-- --------------------------------------------------------

--
-- Table structure for table `interview`
--

CREATE TABLE `interview` (
  `id` bigint(20) NOT NULL,
  `application_id` bigint(20) NOT NULL,
  `recruiter_id` bigint(20) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `mode` enum('ONLINE','ON_SITE') NOT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('SCHEDULED','CANCELLED','DONE') DEFAULT 'SCHEDULED',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `reminder_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_feedback`
--

CREATE TABLE `interview_feedback` (
  `id` bigint(20) NOT NULL,
  `interview_id` bigint(20) NOT NULL,
  `recruiter_id` bigint(20) NOT NULL,
  `overall_score` int(11) DEFAULT NULL,
  `decision` enum('ACCEPTED','REJECTED') NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_application`
--

CREATE TABLE `job_application` (
  `id` bigint(20) NOT NULL,
  `offer_id` bigint(20) NOT NULL,
  `candidate_id` bigint(20) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  `current_status` enum('SUBMITTED','IN_REVIEW','SHORTLISTED','REJECTED','INTERVIEW','HIRED') DEFAULT 'SUBMITTED',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_application`
--

INSERT INTO `job_application` (`id`, `offer_id`, `candidate_id`, `phone`, `cover_letter`, `cv_path`, `applied_at`, `current_status`, `is_archived`) VALUES
(1, 8, 3, '58222333', 'heyy how are u im doing great alo alooo', 'uploads/applications/cv_3_69d2a2d6648d52.08464154.pdf', '2026-04-05 19:58:46', 'IN_REVIEW', 0),
(2, 7, 3, '58222333', 'aywa aywabil 5ifaa al3eb aywa aywabil 5ifaa al3eb aywa aywabil 5ifaa al3eb aywa aywabil 5ifaa al3eb', 'uploads/applications/cv_3_69d2a90e869d44.72635134.pdf', '2026-04-05 20:24:10', 'SHORTLISTED', 0);

-- --------------------------------------------------------

--
-- Table structure for table `job_offer`
--

CREATE TABLE `job_offer` (
  `id` bigint(20) NOT NULL,
  `recruiter_id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `contract_type` enum('CDI','CDD','INTERNSHIP','FREELANCE','PART_TIME','FULL_TIME') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deadline` datetime DEFAULT NULL,
  `status` enum('OPEN','CLOSED','FLAGGED') DEFAULT 'OPEN',
  `quality_score` int(11) DEFAULT NULL,
  `ai_suggestions` text DEFAULT NULL,
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `flagged_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_offer`
--

INSERT INTO `job_offer` (`id`, `recruiter_id`, `title`, `description`, `location`, `latitude`, `longitude`, `contract_type`, `created_at`, `deadline`, `status`, `quality_score`, `ai_suggestions`, `is_flagged`, `flagged_at`) VALUES
(1, 4, 'Senior Java Developer', 'Expert in Spring Boot and Microservices.', 'Tunis', NULL, NULL, 'CDI', '2026-02-01 10:00:00', '2026-03-15 10:00:00', 'OPEN', NULL, NULL, 0, NULL),
(2, 4, 'Data Analyst Junior', 'Visualizing complex datasets with PowerBI.', 'Ariana', NULL, NULL, 'INTERNSHIP', '2026-01-10 09:00:00', '2026-02-28 23:59:59', 'CLOSED', NULL, NULL, 0, NULL),
(3, 4, 'DevOps Engineer', 'Cloud infrastructure and CI/CD pipelines.', 'Ariana', NULL, NULL, 'CDI', '2026-01-20 08:30:00', '2026-02-20 17:00:00', 'OPEN', NULL, NULL, 0, NULL),
(4, 4, 'Frontend Developer', 'UI/UX implementation with React and TS.', 'Remote', NULL, NULL, 'FREELANCE', '2026-02-15 11:00:00', '2026-03-30 23:59:59', 'OPEN', NULL, NULL, 0, NULL),
(5, 4, 'Embedded Systems Intern', 'Working on automotive ECU firmware.', 'Ghazela', NULL, NULL, 'INTERNSHIP', '2026-03-01 09:00:00', '2026-04-01 12:00:00', 'OPEN', NULL, NULL, 0, NULL),
(6, 4, 'QA Automation Specialist', 'Testing with Selenium and JUnit.', 'Tunis', NULL, NULL, 'CDD', '2026-02-10 14:00:00', '2026-03-10 18:00:00', 'OPEN', NULL, NULL, 0, NULL),
(7, 4, 'devops', 'je veux pas que les etrangé travaille chez moi', 'tunis', NULL, NULL, 'INTERNSHIP', '2026-03-02 02:30:46', '2026-03-20 22:59:00', 'OPEN', NULL, NULL, 1, '2026-03-02 11:21:29'),
(8, 4, 'software engenier', 'Nous recherchons un Ingenieur Logiciel pour developper et maintenir des solutions logicielles innovantes. Vous serez responsable de la conception, du developpement et de la mise en production de nos produits logiciels. Vous travaillerez sur l\'analyse des exigences, la conception de l\'architecture technique et la mise en oeuvre des solutions. Vous participerez aux tests et aux debogages des logiciels ainsi qu\'a la mise a jour de la documentation technique. Vous colaborerez avec les equipes de test et de developpement pour assurer la qualite et la fiabilite des produits.', 'tunis', NULL, NULL, 'FREELANCE', '2026-03-02 11:19:33', '2026-03-19 22:59:00', 'FLAGGED', NULL, NULL, 1, '2026-03-02 11:21:10');

-- --------------------------------------------------------

--
-- Table structure for table `job_offer_warning`
--

CREATE TABLE `job_offer_warning` (
  `id` bigint(20) NOT NULL,
  `job_offer_id` bigint(20) NOT NULL,
  `recruiter_id` bigint(20) NOT NULL,
  `admin_id` bigint(20) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('SENT','SEEN','RESOLVED','DISMISSED') NOT NULL DEFAULT 'SENT',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `seen_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_offer_warning`
--

INSERT INTO `job_offer_warning` (`id`, `job_offer_id`, `recruiter_id`, `admin_id`, `reason`, `message`, `status`, `created_at`, `seen_at`, `resolved_at`) VALUES
(1, 6, 4, 1, 'Vague Description', 'Specify tech stack for QA role.', 'RESOLVED', '2026-02-11 10:00:00', NULL, NULL),
(2, 4, 4, 2, 'Salary Info', 'Add budget for freelance mission.', 'SEEN', '2026-03-02 01:50:00', '2026-03-02 03:40:50', NULL),
(3, 7, 4, 1, 'Contenu potentiellement non conforme', 'L\'offre semble contenir du contenu offensant.\nToxicité: 0.47, Insulte: 0.42, Menaces: 0.01, Haine: 0.55\nMerci de corriger la description sinon elle sera supprimée.', 'RESOLVED', '2026-03-02 02:36:08', '2026-03-02 02:36:41', NULL),
(4, 5, 4, 1, 'Information trompeuse', 'Nous avons détecté une information trompeuse dans votre offre d\'emploi intitulée \"Embedded Systems Intern\" qui porte sur le développement de logiciels pour les systèmes embarqués dans l\'industrie automobile, plus précisément sur les firmwares des unités de contrôle électronique (ECU) automobiles. \n\nPlus précisément, la description de l\'offre d\'emploi ne fournit pas suffisamment d\'informations sur les exigences et les responsabilités spécifiques du poste, ce qui pourrait induire les candidats en erreur quant aux compétences requises et aux tâches à accomplir. \n\nIl est essentiel de corriger ces points pour éviter toute confusion et garantir que les candidats soient bien informés sur le poste et les attentes. Si ces informations ne sont pas corrigées, cela pourrait entraîner une sélection de candidats non appropriés, ce qui pourrait avoir des conséquences négatives sur le processus de recrutement et potentiellement sur la performance de l\'équipe. \n\nNous vous recommandons vivement de revoir et de mettre à jour la description de l\'offre d\'emploi pour inclure des détails précis sur les responsabilités, les exigences et les compétences requises pour le poste d\'Embedded Systems Intern. Cela contribuera à attirer les candidats les plus qualifiés et à améliorer l\'efficacité globale du processus de recrutement.', 'RESOLVED', '2026-03-02 02:36:25', '2026-03-02 02:36:56', NULL),
(5, 7, 4, 1, 'Information trompeuse', 'Nous avons détecté une offre d\'emploi intitulée \"devops\" qui contient des informations trompeuses et potentiellement discriminatoires. La description de l\'offre indique explicitement que les candidats étrangers ne sont pas souhaités, ce qui est contraire aux principes d\'égalité et de non-discrimination.\n\nNous vous demandons de corriger cette offre en supprimant toute mention de critères de sélection fondés sur la nationalité ou l\'origine des candidats. Il est essentiel de garantir que les offres d\'emploi soient ouvertes à tous les candidats qualifiés, sans distinction de leur origine ou de leur nationalité.\n\nSi cette offre n\'est pas corrigée, nous serons dans l\'obligation de la retirer de notre plateforme pour non-conformité à nos règles et à la législation en vigueur. Nous vous encourageons à réviser l\'offre et à la reformuler de manière à ce qu\'elle soit conforme aux principes d\'égalité et de non-discrimination. Nous sommes à votre disposition pour vous aider à rédiger une offre d\'emploi conforme aux normes et aux exigences légales. Nous vous remercions de votre attention à cette affaire et de votre coopération.', 'RESOLVED', '2026-03-02 03:41:43', '2026-03-02 03:41:57', NULL),
(6, 8, 4, 1, 'Discrimination', 'Nous avons détecté une discrimination potentielle dans votre offre d\'emploi pour le poste de Software Engineer. Plus précisément, le terme \"Ingenieur Logiciel\" utilisé dans la description pourrait être perçu comme excluant les candidatures féminines, car le terme \"Ingenieur\" est souvent associé à un genre masculin. De plus, l\'utilisation d\'un langage non inclusif pourrait décourager les candidatures de personnes issues de divers horizons.\n\nNous vous recommandons de modifier la description de l\'offre d\'emploi pour utiliser un langage plus inclusif et neutre, tel que \"Ingénieur(e) Logiciel\" ou \"Développeur(se) de logiciels\". Cela permettra de garantir que tous les candidats, quels que soient leur genre ou leur origine, se sentent invités à postuler.\n\nSi ces modifications ne sont pas apportées, nous serons contraints de supprimer l\'offre d\'emploi de notre plateforme pour garantir l\'égalité des chances et la non-discrimination. Nous vous encourageons à prendre ces remarques en compte pour éviter toute conséquence négative et pour attirer un large éventail de talents. Nous sommes à votre disposition pour discuter de ces points et vous aider à améliorer votre offre d\'emploi.', 'SEEN', '2026-03-02 11:21:10', '2026-03-02 11:22:10', NULL),
(7, 7, 4, 1, 'Contenu potentiellement non conforme', 'L\'offre semble contenir du contenu offensant.\nToxicité: 0.47, Insulte: 0.42, Menaces: 0.01, Haine: 0.55\nMerci de corriger la description sinon elle sera supprimée.', 'SENT', '2026-03-02 11:21:29', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `offer_skill`
--

CREATE TABLE `offer_skill` (
  `id` bigint(20) NOT NULL,
  `offer_id` bigint(20) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `level_required` enum('BEGINNER','INTERMEDIATE','ADVANCED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offer_skill`
--

INSERT INTO `offer_skill` (`id`, `offer_id`, `skill_name`, `level_required`) VALUES
(8, 1, 'Java', 'ADVANCED'),
(9, 1, 'Spring Boot', 'INTERMEDIATE'),
(10, 3, 'Docker', 'INTERMEDIATE'),
(11, 3, 'Kubernetes', 'BEGINNER'),
(12, 4, 'React', 'ADVANCED'),
(13, 4, 'TypeScript', 'INTERMEDIATE'),
(14, 5, 'C Language', 'ADVANCED'),
(15, 6, 'Selenium', 'INTERMEDIATE'),
(16, 7, 'Jenkins', 'INTERMEDIATE'),
(17, 7, 'Docker', 'INTERMEDIATE'),
(18, 7, 'Kubernetes', 'INTERMEDIATE'),
(19, 7, 'Ansible', 'INTERMEDIATE'),
(20, 7, 'Prometheus', 'INTERMEDIATE'),
(21, 7, 'Grafana', 'INTERMEDIATE'),
(22, 7, 'Git', 'INTERMEDIATE'),
(23, 7, 'Terraform', 'INTERMEDIATE'),
(24, 8, 'Java', 'INTERMEDIATE'),
(25, 8, 'Python', 'INTERMEDIATE'),
(26, 8, 'C++', 'INTERMEDIATE'),
(27, 8, 'Agile', 'INTERMEDIATE'),
(28, 8, 'Scrum', 'INTERMEDIATE'),
(29, 8, 'Git', 'INTERMEDIATE'),
(30, 8, 'Maven', 'INTERMEDIATE'),
(31, 8, 'Jenkins', 'INTERMEDIATE'),
(32, 8, 'JUnit', 'INTERMEDIATE');

-- --------------------------------------------------------

--
-- Table structure for table `recruiter`
--

CREATE TABLE `recruiter` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_location` varchar(255) DEFAULT NULL,
  `company_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recruiter`
--

INSERT INTO `recruiter` (`id`, `user_id`, `company_name`, `company_location`, `company_description`) VALUES
(4, 4, 'Actia', 'Ghazela centre, نهج الأنصار, المدينة الفاضلة, معتمدية رواد, ولاية أريانة, 2083, تونس', NULL),
(6, 6, 'esprit', 'الجوف الشرقية, معتمدية الزريبة, ولاية زغوان, 1152, تونس', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recruitment_event`
--

CREATE TABLE `recruitment_event` (
  `id` bigint(20) NOT NULL,
  `recruiter_id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `capacity` int(11) DEFAULT 0,
  `meet_link` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recruitment_event`
--

INSERT INTO `recruitment_event` (`id`, `recruiter_id`, `title`, `description`, `event_type`, `location`, `event_date`, `capacity`, `meet_link`, `created_at`) VALUES
(1, 4, 'Actia Tech Day', 'Discover our ECU projects.', 'Open Day', 'Ghazela centre', '2026-04-10 14:00:00', 50, NULL, '2026-03-02 02:00:33'),
(2, 4, 'Java Workshop', 'Hands-on Spring session.', 'Workshop', 'Online', '2026-02-15 18:00:00', 100, NULL, '2026-03-02 02:00:33'),
(3, 4, 'Career Fair 2026', 'Recruitment drive.', 'Fair', 'Palais des Congrès', '2026-05-20 09:00:00', 500, NULL, '2026-03-02 02:00:33'),
(4, 4, 'java', 'Rejoignez notre équipe de talents pour un événement exceptionnel consacré à Java, où vous découvrirez les dernières tendances et innovations dans le domaine. Vous aurez l\'opportunité de rencontrer nos experts et de discuter des possibilités de carrière dans un environnement dynamique et stimulant. Nous sommes à la recherche de candidats passionnés et motivés pour rejoindre notre équipe et contribuer à la création de solutions innovantes. Vous pourrez présenter vos compétences, vos expériences et vos projets, et découvrir comment vous pouvez grandir professionnellement avec nous. Nous offrons un environnement de travail collaboratif et une chance de travailler sur des projets passionnants et stimulants.', 'WEBINAIRE', 'ماطر, تونس', '2026-03-18 23:00:00', 200, 'google.com', '2026-03-02 02:31:43'),
(7, 4, '3IMED DHAYA', 'Rejoignez-nous pour un webinaire exceptionnel à الغرابة, معتمدية باجة الشمالية, ولاية باجة, en Tunisie, où vous pourrez découvrir de nouvelles opportunités de carrière et rencontrer notre équipe dédiée. Vous aurez l\'occasion de vous informer sur les dernières tendances du marché du travail et de présenter vos compétences et expériences aux entreprises leaders de notre secteur. Cet événement est une chance unique de se connecter avec des professionnels passionnés et de prendre un nouveau départ dans votre carrière. Nous vous attendons pour partager nos expériences et nos connaissances, et pour explorer les possibilités de collaboration et de croissance mutuelle.', 'WEBINAIRE', 'الغرابة, معتمدية باجة الشمالية, ولاية باجة, تونس', '2026-03-03 23:00:00', 23, 'google.com', '2026-03-02 11:36:19'),
(8, 4, 'popular test', 'description test', 'Interview day', 'سيدي محرصي, نيابوليس, معتمدية نابل, ولاية نابل, 8000, تونس', '2026-03-11 23:00:00', 1, NULL, '2026-03-02 11:41:26'),
(9, 4, 'urgencyy', 'Rejoignez-nous à تونس pour découvrir des opportunités de carrière exceptionnelles et rencontrer des employeurs de premier plan. Vous aurez l\'occasion de présenter votre candidature, de discuter avec des professionnels du secteur et de découvrir les dernières tendances du marché du travail. Nous vous offrons un espace unique pour vous connecter avec des entreprises innovantes et des leaders de l\'industrie, tout en bénéficiant de conseils d\'experts pour booster votre carrière. Participez à cet événement incontournable pour accélérer votre recherche d\'emploi et atteindre vos objectifs professionnels. Vous pourrez également bénéficier de séances de coaching et de développement personnel pour améliorer vos compétences et augmenter vos chances de réussite.', 'Job_Faire', 'تونس', '2026-03-02 23:00:00', 1, NULL, '2026-03-02 11:46:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `forget_code` varchar(10) DEFAULT NULL,
  `forget_code_expires` datetime DEFAULT NULL,
  `face_person_id` varchar(128) DEFAULT NULL,
  `face_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `first_name`, `last_name`, `phone`, `is_active`, `created_at`, `forget_code`, `forget_code_expires`, `face_person_id`, `face_enabled`) VALUES
(1, 'mohamedmkaouem@gmail.com', '$2a$12$wlTJMvbbsiwyoquTSeumfugPrzNGvezC90kl0NDItmeT4cMfsZkUK', 'Amine', 'mkaouem', '50638321', 1, '2026-03-02 01:49:15', NULL, NULL, '34eab4c9-1616-11f1-b6c8-0242ac120003', 1),
(2, 'zex54lol@gmail.com', '$2a$12$wlTJMvbbsiwyoquTSeumfugPrzNGvezC90kl0NDItmeT4cMfsZkUK', 'mohamed', 'ben moussa', '53757969', 1, '2026-03-02 01:50:38', NULL, NULL, NULL, 0),
(3, 'aziz15abidi@gmail.com', '$2a$12$tE5Rcp/JdYcMfj2mKyLIXuEENpKKrfuh/4TbQ9Hpm.FPTo5LwA7hu', 'mohamed aziz', 'abidi', '58222333', 1, '2026-03-02 01:53:17', NULL, NULL, '170f1cab-15d9-11f1-b6c8-0242ac120003', 1),
(4, 'ammounazaidi9@gmail.com', '$2a$12$tuWch2NHVu2Tv1U.rkT8luOCnFMrDchyempYoTVRKjde7DJS9qu3q', 'emna', 'zaidi', '53752303', 1, '2026-03-02 01:55:29', NULL, NULL, NULL, 0),
(5, 'azizgamercr7@gmail.com', '$2a$12$d3EhFUSBwSpvXeiKxNYc1udP4HkcrA4zCh/8SNxNIjptnA75pko6i', 'Rayan', 'Ben Amor', '90513331', 1, '2026-03-02 03:35:23', NULL, NULL, NULL, 0),
(6, 'facebokmohamedamine@gmail.com', '$2a$12$ww67roSl6ajmK.g3FZJQ2epgM/27F5Uv1CjaqIaj6lHe3bojGl2Zi', 'examen', 'examen', '90513331', 1, '2026-03-02 12:10:14', NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `warning_correction`
--

CREATE TABLE `warning_correction` (
  `id` bigint(20) NOT NULL,
  `warning_id` bigint(20) NOT NULL,
  `job_offer_id` bigint(20) NOT NULL,
  `recruiter_id` bigint(20) NOT NULL,
  `correction_note` text DEFAULT NULL,
  `old_title` varchar(255) DEFAULT NULL,
  `new_title` varchar(255) DEFAULT NULL,
  `old_description` text DEFAULT NULL,
  `new_description` text DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `admin_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warning_correction`
--

INSERT INTO `warning_correction` (`id`, `warning_id`, `job_offer_id`, `recruiter_id`, `correction_note`, `old_title`, `new_title`, `old_description`, `new_description`, `status`, `submitted_at`, `reviewed_at`, `admin_note`) VALUES
(1, 1, 6, 4, 'Added Selenium/JUnit details.', NULL, NULL, NULL, NULL, 'APPROVED', '2026-03-02 02:00:33', NULL, NULL),
(2, 3, 7, 4, 'Nous avons procede aux corrections necessaires suite au signalement (Contenu potentiellement non conforme). L\'offre a ete revue et mise a jour pour repondre aux exigences de la plateforme.', NULL, 'devops', NULL, 'je veux pas que les etrangé travaille chez moi', 'APPROVED', '2026-03-02 02:36:55', '2026-03-02 02:37:26', 'Correction approuvée'),
(3, 4, 5, 4, 'Nous avons procede aux corrections necessaires suite au signalement (Information trompeuse). L\'offre a ete revue et mise a jour pour repondre aux exigences de la plateforme.', NULL, 'Embedded Systems Intern', NULL, 'Working on automotive ECU firmware.', 'APPROVED', '2026-03-02 02:37:02', '2026-03-02 02:37:22', 'Correction approuvée'),
(4, 5, 7, 4, 'Nous avons procede aux corrections necessaires suite au signalement (Information trompeuse). L\'offre a ete revue et mise a jour pour repondre aux exigences de la plateforme.', NULL, 'devops', NULL, 'je veux pas que les etrangé travaille chez moi', 'APPROVED', '2026-03-02 03:42:15', '2026-03-02 03:42:33', 'Correction approuvée'),
(5, 6, 8, 4, 'Nous avons procede aux corrections necessaires suite au signalement (Discrimination). L\'offre a ete revue et mise a jour pour repondre aux exigences de la plateforme.', NULL, 'software engenier', NULL, 'Nous recherchons un Ingenieur Logiciel pour developper et maintenir des solutions logicielles innovantes. Vous serez responsable de la conception, du developpement et de la mise en production de nos produits logiciels. Vous travaillerez sur l\'analyse des exigences, la conception de l\'architecture technique et la mise en oeuvre des solutions. Vous participerez aux tests et aux debogages des logiciels ainsi qu\'a la mise a jour de la documentation technique. Vous colaborerez avec les equipes de test et de developpement pour assurer la qualite et la fiabilite des produits.', 'PENDING', '2026-03-02 11:22:28', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `candidate`
--
ALTER TABLE `candidate`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `candidate_skill`
--
ALTER TABLE `candidate_skill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `event_registration`
--
ALTER TABLE `event_registration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_id` (`event_id`,`candidate_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `event_review`
--
ALTER TABLE `event_review`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_event_review_event` (`event_id`),
  ADD KEY `fk_event_review_candidate` (`candidate_id`);

--
-- Indexes for table `interview`
--
ALTER TABLE `interview`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `recruiter_id` (`recruiter_id`);

--
-- Indexes for table `interview_feedback`
--
ALTER TABLE `interview_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `interview_id` (`interview_id`),
  ADD KEY `recruiter_id` (`recruiter_id`);

--
-- Indexes for table `job_application`
--
ALTER TABLE `job_application`
  ADD PRIMARY KEY (`id`),
  ADD KEY `offer_id` (`offer_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `job_offer`
--
ALTER TABLE `job_offer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recruiter_id` (`recruiter_id`);

--
-- Indexes for table `job_offer_warning`
--
ALTER TABLE `job_offer_warning`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_warn_offer` (`job_offer_id`),
  ADD KEY `fk_warn_recruiter` (`recruiter_id`),
  ADD KEY `fk_warn_admin` (`admin_id`);

--
-- Indexes for table `offer_skill`
--
ALTER TABLE `offer_skill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `offer_id` (`offer_id`);

--
-- Indexes for table `recruiter`
--
ALTER TABLE `recruiter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recruitment_event`
--
ALTER TABLE `recruitment_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recruiter_id` (`recruiter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `warning_correction`
--
ALTER TABLE `warning_correction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_correction_warning` (`warning_id`),
  ADD KEY `fk_correction_job` (`job_offer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `application_status_history`
--
ALTER TABLE `application_status_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `candidate_skill`
--
ALTER TABLE `candidate_skill`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `event_registration`
--
ALTER TABLE `event_registration`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `event_review`
--
ALTER TABLE `event_review`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `interview`
--
ALTER TABLE `interview`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `interview_feedback`
--
ALTER TABLE `interview_feedback`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_application`
--
ALTER TABLE `job_application`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `job_offer`
--
ALTER TABLE `job_offer`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `job_offer_warning`
--
ALTER TABLE `job_offer_warning`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `offer_skill`
--
ALTER TABLE `offer_skill`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `recruitment_event`
--
ALTER TABLE `recruitment_event`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `warning_correction`
--
ALTER TABLE `warning_correction`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD CONSTRAINT `application_status_history_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_application` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `candidate`
--
ALTER TABLE `candidate`
  ADD CONSTRAINT `candidate_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_skill`
--
ALTER TABLE `candidate_skill`
  ADD CONSTRAINT `candidate_skill_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_registration`
--
ALTER TABLE `event_registration`
  ADD CONSTRAINT `event_registration_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `recruitment_event` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registration_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_review`
--
ALTER TABLE `event_review`
  ADD CONSTRAINT `fk_event_review_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_review_event` FOREIGN KEY (`event_id`) REFERENCES `recruitment_event` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interview`
--
ALTER TABLE `interview`
  ADD CONSTRAINT `interview_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_application` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interview_ibfk_2` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interview_feedback`
--
ALTER TABLE `interview_feedback`
  ADD CONSTRAINT `interview_feedback_ibfk_1` FOREIGN KEY (`interview_id`) REFERENCES `interview` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interview_feedback_ibfk_2` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_application`
--
ALTER TABLE `job_application`
  ADD CONSTRAINT `job_application_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `job_offer` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_application_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offer`
--
ALTER TABLE `job_offer`
  ADD CONSTRAINT `job_offer_ibfk_1` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offer_warning`
--
ALTER TABLE `job_offer_warning`
  ADD CONSTRAINT `fk_warn_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_warn_offer` FOREIGN KEY (`job_offer_id`) REFERENCES `job_offer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_warn_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `offer_skill`
--
ALTER TABLE `offer_skill`
  ADD CONSTRAINT `offer_skill_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `job_offer` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recruiter`
--
ALTER TABLE `recruiter`
  ADD CONSTRAINT `recruiter_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recruitment_event`
--
ALTER TABLE `recruitment_event`
  ADD CONSTRAINT `recruitment_event_ibfk_1` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warning_correction`
--
ALTER TABLE `warning_correction`
  ADD CONSTRAINT `fk_correction_job` FOREIGN KEY (`job_offer_id`) REFERENCES `job_offer` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_correction_warning` FOREIGN KEY (`warning_id`) REFERENCES `job_offer_warning` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

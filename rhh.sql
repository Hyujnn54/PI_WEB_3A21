-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 10:41 AM
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
(11, 'General Management');

-- --------------------------------------------------------

--
-- Table structure for table `application_status_history`
--

CREATE TABLE `application_status_history` (
  `id` bigint(20) NOT NULL,
  `application_id` bigint(20) NOT NULL,
  `status` enum('SUBMITTED','IN_REVIEW','SHORTLISTED','REJECTED','INTERVIEW','HIRED','ARCHIVED','UNARCHIVED') NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `changed_by_id` bigint(20) NOT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_status_history`
--

INSERT INTO `application_status_history` (`id`, `application_id`, `status`, `changed_at`, `changed_by_id`, `note`) VALUES
(166, 66, 'SUBMITTED', '2026-05-14 10:11:13', 109, 'Mohamed Aziz Abidi submitted a new application.'),
(167, 66, 'SHORTLISTED', '2026-05-14 10:12:43', 110, 'Emna Zaidi shortlisted the application for the next step.'),
(168, 66, 'SHORTLISTED', '2026-05-14 10:13:31', 110, 'good application'),
(169, 66, 'INTERVIEW', '2026-05-14 10:14:20', 110, 'Interview scheduled; application moved to Interview stage.');

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
  `cv_path` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate`
--

INSERT INTO `candidate` (`id`, `user_id`, `location`, `education_level`, `experience_years`, `cv_path`, `latitude`, `longitude`) VALUES
(109, NULL, 'Ben Arous', 'It Engineering', 5, '', 36.6306483, 10.2100827);

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
(46, 109, 'Figma', 'INTERMEDIATE'),
(47, 109, 'Adobe XD', 'ADVANCED'),
(48, 109, 'Design System', 'INTERMEDIATE');

-- --------------------------------------------------------

--
-- Table structure for table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260507021600', '2026-05-07 08:17:55', 48),
('DoctrineMigrations\\Version20260507030500', '2026-05-07 08:54:23', 33),
('DoctrineMigrations\\Version20260507031500', '2026-05-07 08:56:12', 39),
('DoctrineMigrations\\Version20260507032500', '2026-05-07 08:57:51', 36),
('DoctrineMigrations\\Version20260507033500', '2026-05-07 09:00:37', 40),
('DoctrineMigrations\\Version20260507034500', '2026-05-07 09:01:41', 32),
('DoctrineMigrations\\Version20260507035500', '2026-05-07 09:02:52', 41),
('DoctrineMigrations\\Version20260507040500', '2026-05-07 09:04:00', 34),
('DoctrineMigrations\\Version20260507041500', '2026-05-07 09:06:42', 43),
('DoctrineMigrations\\Version20260507042500', '2026-05-07 09:14:36', 36);

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
(11, 16, 109, '2026-05-14 08:16:06', 'confirmed');

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

--
-- Dumping data for table `interview`
--

INSERT INTO `interview` (`id`, `application_id`, `recruiter_id`, `scheduled_at`, `duration_minutes`, `mode`, `meeting_link`, `location`, `status`, `notes`, `created_at`, `reminder_sent`) VALUES
(1, 66, 110, '2026-05-21 10:00:00', 60, 'ONLINE', 'https://meet.jit.si/talentbridge-interview-app66-799132da6b86', '', 'SCHEDULED', 'Be right in time', '2026-05-14 10:14:20', 0);

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
(66, 2000000000000004, 109, '+21658913065', 'I am writing to express my interest in the Web Designer position in Tunis as a freelance opportunity. With 5 years of experience in IT engineering and a strong background in design, I am confident that I can bring significant value to this role. My skills in Adobe XD, Design System, and Figma will enable me to create visually appealing and user-friendly web designs that meet the requirements of your clients.\n\nAs a highly motivated and experienced professional, I am well-equipped to work independently as a freelancer and manage multiple projects simultaneously. My advanced knowledge of Adobe XD and intermediate knowledge of Design System and Figma will allow me to create cohesive and effective design solutions. I am excited about the prospect of working with clients in Tunis and contributing my skills and expertise to deliver high-quality web design services.\n\nI am a strong communicator and team player, with excellent problem-solving skills and attention to detail. I am confident that my skills and experience align with the requirements of this role, and I am eager to discuss my application further. Please do not hesitate to contact me at azizgamercr7@gmail.com or 58913065 to discuss this opportunity further. I look forward to the chance to contribute my skills and experience to this role and work with you as a freelance web designer.', 'Aziz-Abidi-CV-6a0583a18f1db.pdf', '2026-05-14 10:11:13', 'INTERVIEW', 0);

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
(2000000000000004, 110, 'Web Designer', 'Nous recherchons un(e) Designer UX/UI pour creer des experiences utilisateurs intuitives et esthetiques. Vous realiserez des recherches utilisateurs, wireframes et prototypes interactifs. Vous collaborerez avec les developpeurs pour garantir la fidelite du design en production. Vous contribuerez a l\'evolution du Design System et des guidelines visuelles.', 'Tunis', NULL, NULL, 'FREELANCE', '2026-05-14 08:09:19', '2026-05-29 22:59:00', 'OPEN', 75, '{\"flagged\":false,\"reason\":\"Comment Analyzer and Groq did not detect enough risk to flag this offer.\",\"commentAnalyzer\":{\"toxicityScore\":0.35,\"spamScore\":0.18,\"sentiment\":\"neutral\",\"labels\":[]},\"groq\":{\"source\":\"groq\",\"riskLevel\":\"medium\",\"summary\":\"The job offer is mostly clear, but lacks specific details about the freelance contract, working conditions, and equal opportunity statement.\",\"issues\":[\"Missing information about payment terms and freelance contract details\",\"No mention of equal opportunity or non-discrimination policy\",\"Lack of clarity on working hours, workload, and expected outcomes\",\"No clear description of the recruitment process and selection criteria\",\"Language used is mostly French, which may limit the applicant pool\"],\"recommendations\":[\"Add a clear description of the freelance contract, including payment terms and working conditions\",\"Include an equal opportunity statement to ensure non-discrimination\",\"Provide more details about the expected workload, working hours, and performance metrics\",\"Clarify the recruitment process, including the selection criteria and timeline\",\"Consider translating the job offer into other languages to attract a more diverse applicant pool\"]}}', 0, '2026-05-14 10:18:10');

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
(1778746833670189, 2000000000000004, 110, 11, '[Policy violation] Nous avons identifié une violation de notre politique dans l\'offre d\'emploi que vous avez publiée pour le poste de Web Designer à Tunis en tant que freelance. Plus précisément, la description du poste et les compétences requises ne s...', 'Nous avons identifié une violation de notre politique dans l\'offre d\'emploi que vous avez publiée pour le poste de Web Designer à Tunis en tant que freelance. Plus précisément, la description du poste et les compétences requises ne sont pas conformes à nos exigences en matière de clarté et de précision. Nous vous demandons de corriger cette offre en fournissant des détails plus spécifiques sur les responsabilités du poste et les compétences requises, ainsi que de préciser les attentes en matière', 'SEEN', '2026-05-14 10:20:33', '2026-05-14 08:24:16', '2026-05-14 10:20:33');

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
(1778743842836695, 2000000000000004, 'Figma', 'INTERMEDIATE'),
(1778743842836696, 2000000000000004, 'Adobe XD', 'INTERMEDIATE'),
(1778743842836697, 2000000000000004, 'User Research', 'INTERMEDIATE'),
(1778743842836698, 2000000000000004, 'Prototyping', 'INTERMEDIATE'),
(1778743842836699, 2000000000000004, 'Design System', 'INTERMEDIATE'),
(1778743842836700, 2000000000000004, 'Accessibility', 'INTERMEDIATE'),
(1778743842836701, 2000000000000004, 'Sketch', 'INTERMEDIATE');

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
(110, NULL, 'google', 'RL518, Sidi Thabet, سبالة بن عمار, معتمدية سيدي ثابت, Ariana, 2094, Tunisia', NULL);

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
(16, 110, 'Php Workshop', 'Elevate your backend development skills at our hands-on PHP Workshop this May in Radès. Join our engineering team for an interactive session where you will tackle real-world coding challenges and discover how we build scalable solutions. Seats are limited to 50 participants, so secure your spot today to network with our recruiters and showcase your technical expertise.', 'Workshop', 'Rue Bir Ettaraz, رادس المدينة, Radès, معتمدية رادس, Ben Arous, 2040, Tunisia', '2026-05-30 10:15:00', 20, '', '2026-05-14 10:15:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `forget_code` varchar(10) DEFAULT NULL,
  `forget_code_expires` datetime DEFAULT NULL,
  `face_person_id` varchar(128) DEFAULT NULL,
  `face_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `discr` varchar(255) NOT NULL,
  `google_authenticator_secret` varchar(255) DEFAULT NULL,
  `google_authenticator_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `roles`, `password`, `first_name`, `last_name`, `phone`, `is_active`, `created_at`, `forget_code`, `forget_code_expires`, `face_person_id`, `face_enabled`, `discr`, `google_authenticator_secret`, `google_authenticator_enabled`) VALUES
(11, 'admin@gmail.com', '[\"ROLE_ADMIN\"]', '$2y$13$CkoFplROsEf3/C62Cty17O5sHbPI1dE66rLjLhVZ/k1TsPJYf0/3W', 'admin', 'admin', '12345678', 1, '2026-04-14 19:09:08', NULL, NULL, NULL, 0, 'admin', NULL, 0),
(109, 'azizgamercr7@gmail.com', '[\"ROLE_CANDIDATE\"]', '$2y$13$WQJ8wtJTI2ACjqDSyiw.0ee6vj3zsgYEMcHwqMJr73/k/b13ztr3y', 'Mohamed Aziz', 'Abidi', '58913065', 1, '2026-05-14 09:59:43', NULL, NULL, NULL, 0, 'candidate', NULL, 0),
(110, 'emna@gmail.com', '[\"ROLE_RECRUITER\"]', '$2y$13$VmmId/t72m0PVeHsFopg9e/ReL5ILET6kJtgVJvAPqy/xv0lJA1v6', 'Emna', 'Zaidi', '50998332', 1, '2026-05-14 10:06:50', NULL, NULL, NULL, 0, 'recruiter', NULL, 0);

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
(6, 1778746833670189, 2000000000000004, 110, 'Nous avons procede aux corrections necessaires suite au signalement ([Policy violation] Nous avons identifié une violation de notre politique dans l\'offre d\'emploi que vous avez publiée pour le poste de Web Designer à Tunis en tant que freelance. Plus précisément, la description du poste et les compétences requises ne s...). L\'offre a ete revue et mise a jour pour repondre aux exigences de la plateforme.', NULL, 'Web Designer', NULL, 'Nous recherchons un(e) Designer UX/UI pour creer des experiences utilisateurs intuitives et esthetiques. Vous realiserez des recherches utilisateurs, wireframes et prototypes interactifs. Vous collaborerez avec les developpeurs pour garantir la fidelite du design en production. Vous contribuerez a l\'evolution du Design System et des guidelines visuelles.', 'PENDING', '2026-05-14 08:24:33', NULL, NULL),
(7, 1778746833670189, 2000000000000004, 110, 'Nous avons procede aux corrections necessaires suite au signalement ([Policy violation] Nous avons identifié une violation de notre politique dans l\'offre d\'emploi que vous avez publiée pour le poste de Web Designer à Tunis en tant que freelance. Plus précisément, la description du poste et les compétences requises ne s...). L\'offre a ete revue et mise a jour pour repondre aux exigences de la plateforme.', NULL, 'Web Designer', NULL, 'Nous recherchons un(e) Designer UX/UI pour creer des experiences utilisateurs intuitives et esthetiques. Vous realiserez des recherches utilisateurs, wireframes et prototypes interactifs. Vous collaborerez avec les developpeurs pour garantir la fidelite du design en production. Vous contribuerez a l\'evolution du Design System et des guidelines visuelles.', 'PENDING', '2026-05-14 08:41:22', NULL, NULL);

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
  ADD KEY `changed_by_id` (`changed_by_id`);

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
-- Indexes for table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `candidate_skill`
--
ALTER TABLE `candidate_skill`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `event_registration`
--
ALTER TABLE `event_registration`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `event_review`
--
ALTER TABLE `event_review`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `interview`
--
ALTER TABLE `interview`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `interview_feedback`
--
ALTER TABLE `interview_feedback`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_application`
--
ALTER TABLE `job_application`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `job_offer`
--
ALTER TABLE `job_offer`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2000000000000005;

--
-- AUTO_INCREMENT for table `job_offer_warning`
--
ALTER TABLE `job_offer_warning`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1778746833670190;

--
-- AUTO_INCREMENT for table `offer_skill`
--
ALTER TABLE `offer_skill`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1778743842836702;

--
-- AUTO_INCREMENT for table `recruitment_event`
--
ALTER TABLE `recruitment_event`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `warning_correction`
--
ALTER TABLE `warning_correction`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  ADD CONSTRAINT `FK_APPLICATION_STATUS_HISTORY_CHANGED_BY_ID` FOREIGN KEY (`changed_by_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_status_history_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_application` (`id`);

--
-- Constraints for table `candidate`
--
ALTER TABLE `candidate`
  ADD CONSTRAINT `candidate_ibfk_1` FOREIGN KEY (`id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_skill`
--
ALTER TABLE `candidate_skill`
  ADD CONSTRAINT `candidate_skill_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`);

--
-- Constraints for table `event_registration`
--
ALTER TABLE `event_registration`
  ADD CONSTRAINT `event_registration_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `recruitment_event` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registration_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`);

--
-- Constraints for table `event_review`
--
ALTER TABLE `event_review`
  ADD CONSTRAINT `fk_event_review_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`),
  ADD CONSTRAINT `fk_event_review_event` FOREIGN KEY (`event_id`) REFERENCES `recruitment_event` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interview`
--
ALTER TABLE `interview`
  ADD CONSTRAINT `interview_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_application` (`id`),
  ADD CONSTRAINT `interview_ibfk_2` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`);

--
-- Constraints for table `interview_feedback`
--
ALTER TABLE `interview_feedback`
  ADD CONSTRAINT `interview_feedback_ibfk_1` FOREIGN KEY (`interview_id`) REFERENCES `interview` (`id`),
  ADD CONSTRAINT `interview_feedback_ibfk_2` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_application`
--
ALTER TABLE `job_application`
  ADD CONSTRAINT `job_application_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `job_offer` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_application_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`id`);

--
-- Constraints for table `job_offer`
--
ALTER TABLE `job_offer`
  ADD CONSTRAINT `job_offer_ibfk_1` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offer_warning`
--
ALTER TABLE `job_offer_warning`
  ADD CONSTRAINT `fk_warn_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`),
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

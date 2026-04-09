-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 09, 2026 at 02:07 PM
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
(89, 25, 'SUBMITTED', '2026-04-08 00:45:32', 3, 'Application submitted');

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

--
-- Dumping data for table `interview`
--

INSERT INTO `interview` (`id`, `application_id`, `recruiter_id`, `scheduled_at`, `duration_minutes`, `mode`, `meeting_link`, `location`, `status`, `notes`, `created_at`, `reminder_sent`) VALUES
(1, 25, 4, '2026-04-25 01:00:00', 60, 'ON_SITE', '', 'tunisdd', 'SCHEDULED', 'qsdqsdqsd', '2026-04-08 02:00:37', 0);

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
(25, 1775601948423129, 3, '50232355', 'April 08, 2026\n\nDear Hiring Manager,\n\nI am writing to express my strong interest in the taw ta5rali fih position at Actia. With my background in college and 4 ans d\'expérience, I am confident that I can make a significant contribution to your team.\n\nThroughout my career, I have developed strong expertise in the following areas:\n• Java (ADVANCED)\n• React (ADVANCED)\n• SQL (ADVANCED)\n• CSS (INTERMEDIATE)\n• Docker (INTERMEDIATE)\n• Java (computer programming) (INTERMEDIATE)\n• Spring Boot (INTERMEDIATE)\n• Type script (INTERMEDIATE)\n• Angular (BEGINNER)\n\nThese skills have enabled me to consistently deliver high-quality results and exceed expectations in my professional roles.\n\nI am particularly drawn to Actia because of your commitment to excellence and innovation in the industry. I am excited about the opportunity to contribute my expertise and grow professionally within your esteemed organization. I am confident that my skills, experience, and dedication make me an ideal candidate for this position.\n\nI would welcome the opportunity to discuss how my qualifications align with your team\'s needs. Please feel free to contact me at 58222333 or aziz15abidi@gmail.com at your earliest convenience.\n\nThank you for considering my application. I look forward to the possibility of contributing to Actia and making a positive impact on your organization.\n\nSincerely,\n\nmohamed aziz abidi', 'uploads\\applications\\05e9a8d6-f83a-41ae-9821-fc6f11820b4e_Aziz_Abidi_CV.pdf', '2026-04-08 00:45:32', '', 0);

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
(2, 4, 'Data Analyst Junior', 'Visualizing complex datasets with PowerBI.', 'Ariana', NULL, NULL, 'INTERNSHIP', '2026-01-10 09:00:00', '2026-02-28 23:59:59', 'CLOSED', NULL, NULL, 0, NULL),
(3, 4, 'DevOps Engineerdd', 'Cloud infrastructure and CI/CD pipelines.', 'Ariana', 0, 0, 'CDI', '2026-01-20 08:30:00', '2026-04-30 17:00:00', 'CLOSED', NULL, NULL, 0, NULL),
(1775601948423129, 4, 'taw ta5rali fih', 'sqdqsdqsdqsdqsdqsd', 'tunis', 0, 0, 'CDI', '2026-04-08 00:45:48', '2026-04-16 23:45:00', 'OPEN', 100, '', 0, '2026-04-08 00:45:48');

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
(1775602935557576, 3, 4, 1, 'sdqsdq', 'sdqsdq', 'DISMISSED', '2026-04-08 01:02:15', '2026-04-08 01:02:15', '2026-04-08 01:02:15'),
(1775604203929371, 1775601948423129, 4, 1, '[Incorrect information] sdqsdqs', '[Incorrect information] sdqsdqs', 'DISMISSED', '2026-04-08 01:23:23', '2026-04-08 01:23:23', '2026-04-08 01:23:23'),
(1775604517343478, 1775601948423129, 4, 1, '[Incorrect information] qsdqsdqsd', '[Incorrect information] qsdqsdqsd', 'DISMISSED', '2026-04-08 01:28:37', '2026-04-08 01:28:37', '2026-04-08 01:28:37'),
(1775604867459712, 1775601948423129, 4, 1, '[Policy violation] change the title', '[Policy violation] change the title', 'DISMISSED', '2026-04-08 01:34:27', '2026-04-08 01:34:27', '2026-04-08 01:34:27');

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
(1775604885169515, 1775601948423129, 'bil5ifa', 'BEGINNER'),
(1775605597466839, 3, 'Kubernetes', 'INTERMEDIATE'),
(1775605597468391, 3, 'Docker', 'BEGINNER');

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
  `discr` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `roles`, `password`, `first_name`, `last_name`, `phone`, `is_active`, `created_at`, `forget_code`, `forget_code_expires`, `face_person_id`, `face_enabled`, `discr`) VALUES
(1, 'mohamedmkaouem@gmail.com', '', '$2a$12$wlTJMvbbsiwyoquTSeumfugPrzNGvezC90kl0NDItmeT4cMfsZkUK', 'Amine', 'mkaouem', '50638321', 1, '2026-03-02 01:49:15', NULL, NULL, '34eab4c9-1616-11f1-b6c8-0242ac120003', 1, ''),
(2, 'zex54lol@gmail.com', '', '$2a$12$wlTJMvbbsiwyoquTSeumfugPrzNGvezC90kl0NDItmeT4cMfsZkUK', 'mohamed', 'ben moussa', '53757969', 1, '2026-03-02 01:50:38', NULL, NULL, NULL, 0, ''),
(3, 'aziz15abidi@gmail.com', '', '$2a$12$tE5Rcp/JdYcMfj2mKyLIXuEENpKKrfuh/4TbQ9Hpm.FPTo5LwA7hu', 'mohamed aziz', 'abidi', '58222333', 1, '2026-03-02 01:53:17', NULL, NULL, '170f1cab-15d9-11f1-b6c8-0242ac120003', 1, ''),
(4, 'ammounazaidi9@gmail.com', '', '$2a$12$tuWch2NHVu2Tv1U.rkT8luOCnFMrDchyempYoTVRKjde7DJS9qu3q', 'emna', 'zaidi', '53752303', 1, '2026-03-02 01:55:29', NULL, NULL, NULL, 0, ''),
(5, 'azizgamercr7@gmail.com', '', '$2a$12$d3EhFUSBwSpvXeiKxNYc1udP4HkcrA4zCh/8SNxNIjptnA75pko6i', 'Rayan', 'Ben Amor', '90513331', 1, '2026-03-02 03:35:23', NULL, NULL, NULL, 0, ''),
(6, 'facebokmohamedamine@gmail.com', '', '$2a$12$ww67roSl6ajmK.g3FZJQ2epgM/27F5Uv1CjaqIaj6lHe3bojGl2Zi', 'examen', 'examen', '90513331', 1, '2026-03-02 12:10:14', NULL, NULL, NULL, 0, '');

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `job_offer`
--
ALTER TABLE `job_offer`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1775601948423130;

--
-- AUTO_INCREMENT for table `job_offer_warning`
--
ALTER TABLE `job_offer_warning`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1775604867459713;

--
-- AUTO_INCREMENT for table `offer_skill`
--
ALTER TABLE `offer_skill`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1775605597468392;

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

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : mar. 19 août 2025 à 18:20
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_stagiaires`
--

-- --------------------------------------------------------

--
-- Structure de la table `encadreur`
--

CREATE TABLE `encadreur` (
  `id_utilisateur` int(11) NOT NULL,
  `poste` varchar(100) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `encadreur`
--

INSERT INTO `encadreur` (`id_utilisateur`, `poste`, `service`) VALUES
(6, 'Responsable IT', 'Informatique');

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `encadreur_id` int(11) NOT NULL,
  `qualite_travail` tinyint(1) NOT NULL DEFAULT 0,
  `autonomie` tinyint(1) NOT NULL DEFAULT 0,
  `initiative` tinyint(1) NOT NULL DEFAULT 0,
  `assiduite` tinyint(1) NOT NULL DEFAULT 0,
  `communication` tinyint(1) NOT NULL DEFAULT 0,
  `integration` tinyint(1) NOT NULL DEFAULT 0,
  `note_globale` decimal(5,2) NOT NULL DEFAULT 0.00,
  `note_rapports` decimal(5,2) DEFAULT 0.00,
  `note_taches` decimal(5,2) DEFAULT 0.00,
  `note_presence` decimal(5,2) DEFAULT 0.00,
  `date_evaluation` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `jours_presence` int(11) DEFAULT 0,
  `jours_retard` int(11) DEFAULT 0,
  `jours_absence` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jours_feries`
--

CREATE TABLE `jours_feries` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `jours_feries`
--

INSERT INTO `jours_feries` (`id`, `date`, `nom`) VALUES
(1, '2025-01-01', 'Jour de l\'An'),
(2, '2025-04-21', 'Lundi de Pâques'),
(3, '2025-05-01', 'Fête du Travail'),
(4, '2025-05-08', 'Victoire 1945'),
(5, '2025-05-29', 'Ascension'),
(6, '2025-06-09', 'Lundi de Pentecôte'),
(7, '2025-07-14', 'Fête Nationale'),
(8, '2025-08-15', 'Assomption'),
(9, '2025-11-01', 'Toussaint'),
(10, '2025-11-11', 'Armistice 1918'),
(11, '2025-12-25', 'Noël');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `expediteur_id` int(11) NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `sujet`, `contenu`, `fichier`, `date_envoi`, `lu`) VALUES
(1, 7, 6, 'manger', 'jhiAJOyAYUAGuz', NULL, '2025-08-12 15:05:40', 1),
(2, 7, 6, 'fyiuhihopo[', 'jhs12s1o2-so1-2os', NULL, '2025-08-12 16:57:53', 1),
(3, 6, 7, 'bonjour', 'hghoipoiugftyrsdasfghjkl', NULL, '2025-08-12 17:18:48', 1),
(4, 6, 7, 'cvqehqgwfdyqidjqkl', 'ndqk;dioqidjeqm', NULL, '2025-08-12 18:27:23', 1),
(5, 7, 6, 'manger', 'xisjoxiwuyftdxfqwh', NULL, '2025-08-12 19:10:57', 1),
(6, 7, 6, 'j\'ai faim', 'rjh45o;tlkjrh', NULL, '2025-08-13 09:09:12', 1),
(7, 7, 6, 'jksh', '2u2il2', NULL, '2025-08-13 10:17:59', 1),
(8, 7, 6, 'fyiuhihopo[', 'm,dqwdwk', NULL, '2025-08-13 13:00:25', 1),
(9, 7, 8, 'fkeyfjewhfewj', 'hsadgawhkfhelkj', NULL, '2025-08-15 15:26:35', 1),
(10, 7, 6, 'Nouveau rapport soumis : stryugihlu', 'Bonjour,\n\nLe stagiaire Stagiaire Marie a soumis un nouveau rapport intitulé : \"stryugihlu\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-15 15:36:22', 1),
(11, 7, 6, 'Nouveau rapport soumis : laravel', 'Bonjour,\n\nLe stagiaire Stagiaire Marie a soumis un nouveau rapport intitulé : \"laravel\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-18 09:00:40', 1),
(12, 7, 6, 'Nouveau rapport soumis : gun', 'Bonjour,\n\nLe stagiaire Stagiaire Marie a soumis un nouveau rapport intitulé : \"gun\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-18 09:51:19', 1),
(13, 7, 6, 'manger', 'hsGXHGAKDHWQKH', NULL, '2025-08-18 11:20:04', 1),
(14, 7, 6, 'bonjour', 'comment vous aller', NULL, '2025-08-18 15:25:39', 1),
(15, 6, 7, 'j\'ai faim', '50000', NULL, '2025-08-18 15:28:31', 1),
(16, 7, 6, 'Nouveau rapport soumis : qwbdjkwqh', 'Bonjour,\n\nLe stagiaire Stagiaire Marie a soumis un nouveau rapport intitulé : \"qwbdjkwqh\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-18 15:38:13', 1),
(17, 6, 7, 'bonjour', 'merci', NULL, '2025-08-18 16:03:40', 1),
(18, 7, 6, 'djwce', 'jhjehv', NULL, '2025-08-18 16:04:28', 1),
(19, 7, 6, 'Nouveau rapport soumis : asdjh', 'Bonjour,\n\nLe stagiaire Stagiaire Marie a soumis un nouveau rapport intitulé : \"asdjh\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-18 16:06:26', 1),
(20, 7, 6, 'bonjour', 'test', NULL, '2025-08-18 17:06:50', 1),
(21, 6, 7, 'bonjour ', 'comment tu vas ?', NULL, '2025-08-19 10:44:17', 1),
(22, 7, 6, 'Nouveau rapport soumis : jkdhdh', 'Bonjour,\n\nLe stagiaire Stagiaire Marie a soumis un nouveau rapport intitulé : \"jkdhdh\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-19 12:21:50', 1);

-- --------------------------------------------------------

--
-- Structure de la table `message_pieces_jointes`
--

CREATE TABLE `message_pieces_jointes` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `nom_fichier` varchar(255) NOT NULL,
  `chemin` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `message_pieces_jointes`
--

INSERT INTO `message_pieces_jointes` (`id`, `message_id`, `nom_fichier`, `chemin`) VALUES
(1, 1, '3cbd561d-1dc8-497a-9f78-fe2e167f591e.jpg', '1755007540_689b4a346b947.jpg'),
(2, 2, 'Créer un plan de loc.png', '1755014273_689b648199a79.png'),
(3, 3, 'gestion presence en.png', '1755015528_689b69685f9c6.png'),
(4, 4, 'insertion et existant.docx', '1755019643_689b797bc4bb1.docx'),
(5, 5, 'acceeilEncadreur.png', '1755022257_689b83b12c974.png'),
(6, 6, 'Doc1.docx', '1755072552_689c4828e2bc9.docx'),
(7, 7, 'insertion et existant.pdf', '1755076679_689c584772e9c.pdf'),
(8, 8, 'php.docx', '1755086425_689c7e5950444.docx'),
(9, 9, 'gestionStagiaire.png', '1755267995_689f439bb3a8e.png'),
(10, 13, 'creer image de cet o.png', '1755512404_68a2fe54263e8.png'),
(11, 17, 'dash.png', '1755529420_68a340cc12c20.png'),
(12, 18, 'Capture d’écran 2025-08-01 123152.png', '1755529468_68a340fcd5a15.png'),
(13, 20, 'creer image de cet o.png', '1755533210_68a34f9ab9a09.png'),
(14, 21, 'erreur.png', '1755596657_68a44771b293e.png');

-- --------------------------------------------------------

--
-- Structure de la table `presence`
--

CREATE TABLE `presence` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `statut_journee` enum('present','absent','retard') NOT NULL DEFAULT 'present',
  `heure_arrivee` time DEFAULT NULL,
  `heure_depart` time DEFAULT NULL,
  `heure_debut_pause` time DEFAULT NULL,
  `heure_fin_pause` time DEFAULT NULL,
  `localisation_arrivee` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `presence`
--

INSERT INTO `presence` (`id`, `stagiaire_id`, `date`, `statut_journee`, `heure_arrivee`, `heure_depart`, `heure_debut_pause`, `heure_fin_pause`, `localisation_arrivee`) VALUES
(1, 7, '2025-08-14', 'retard', '16:25:24', '16:43:11', '16:43:02', '16:42:57', '4.050192220492866,9.698295935365325'),
(2, 8, '2025-08-14', 'retard', '16:24:32', NULL, NULL, '16:25:13', 'Rue Boué de Lapeyrère (N° 1.382), Akwa, Douala I, Cameroun'),
(3, 7, '2025-08-18', 'retard', '10:17:55', '10:21:57', NULL, '10:21:52', 'Rue Boué de Lapeyrère (N° 1.382), Akwa, Douala I, Cameroun'),
(4, 8, '2025-08-18', 'retard', '12:40:56', NULL, NULL, NULL, 'Rue Boué de Lapeyrère (N° 1.382), Douala I');

-- --------------------------------------------------------

--
-- Structure de la table `rapports`
--

CREATE TABLE `rapports` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `type` enum('journalier','hebdomadaire','mensuel') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `activites` text NOT NULL,
  `difficultes` text NOT NULL,
  `solutions` text NOT NULL,
  `date_soumission` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('en attente','validé','rejeté') DEFAULT 'en attente',
  `commentaire_encadreur` text DEFAULT NULL,
  `fichier_pdf` varchar(255) DEFAULT NULL,
  `date_validation` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `rapports`
--

INSERT INTO `rapports` (`id`, `stagiaire_id`, `type`, `titre`, `activites`, `difficultes`, `solutions`, `date_soumission`, `statut`, `commentaire_encadreur`, `fichier_pdf`, `date_validation`) VALUES
(1, 7, 'hebdomadaire', 'hgcjs', 'adkw', 'lwuqw', 'hqwdjq', '2025-08-13 09:36:17', 'rejeté', 'mauvais', 'rapport_7_1_2025-08-13_11-36-17.pdf', NULL),
(2, 7, 'journalier', '1', '2', '3', '4', '2025-08-13 09:37:33', 'validé', '', 'rapport_7_2_2025-08-13_11-37-33.pdf', NULL),
(3, 7, 'hebdomadaire', 'wbsj,wq', 'klsjwq', 'lkjsw', 'iwquysgw', '2025-08-13 11:56:49', 'validé', 'BON', 'rapport_7_3_2025-08-13_13-56-49.pdf', NULL),
(4, 7, 'journalier', 'DORMIR', 'uytudydf', 'jhul', 'oiooi', '2025-08-13 14:56:52', 'rejeté', 'movais', 'rapport_7_4_2025-08-13_16-56-52.pdf', NULL),
(5, 7, 'hebdomadaire', 'dbwjkd', 'hsio', 'ql', 'aSU', '2025-08-13 17:08:41', 'validé', '', 'rapport_7_5_2025-08-13_19-08-41.pdf', NULL),
(6, 7, 'journalier', 'stryugihlu', 'yutuiyioupop', 'yutukyil_', 'treytytiuyoiupo', '2025-08-15 14:36:22', 'validé', '', 'rapport_7_6_2025-08-15_15-36-22.pdf', NULL),
(7, 7, 'journalier', 'laravel', 'php', 'js', 'hhhh', '2025-08-18 08:00:40', 'validé', '', 'rapport_7_7_2025-08-18_09-00-40.pdf', NULL),
(8, 7, 'mensuel', 'gun', 'meliodas', 'natsu', 'naruto', '2025-08-18 08:51:19', 'rejeté', 'mauvais', 'rapport_7_8_2025-08-18_09-51-19.pdf', NULL),
(9, 7, 'journalier', 'qwbdjkwqh', 'kljskljw', 'qwguwqh', 'wyiw', '2025-08-18 14:38:13', 'validé', 'bien', 'rapport_7_9_2025-08-18_15-38-13.pdf', NULL),
(10, 7, 'journalier', 'asdjh', 'jhdkjwhdk', 'hgdq', 'shdjq', '2025-08-18 15:06:26', 'rejeté', '', 'rapport_7_10_2025-08-18_16-06-26.pdf', NULL),
(11, 7, 'hebdomadaire', 'jkdhdh', 'jwlelkq', 'kwljqekl', 'ldjkl', '2025-08-19 11:21:50', 'validé', '', 'rapport_7_11_2025-08-19_12-21-50.pdf', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `stagiaire`
--

CREATE TABLE `stagiaire` (
  `id_utilisateur` int(11) NOT NULL,
  `encadreur_id` int(11) DEFAULT NULL,
  `filiere` varchar(100) DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `stagiaire`
--

INSERT INTO `stagiaire` (`id_utilisateur`, `encadreur_id`, `filiere`, `niveau`, `date_debut`, `date_fin`) VALUES
(7, 6, 'Informatique', 'Licence', '2025-08-17', '2025-11-17'),
(8, 6, 'Génie Logiciel', 'Licence 3', '2025-08-17', '2025-11-17');

-- --------------------------------------------------------

--
-- Structure de la table `taches`
--

CREATE TABLE `taches` (
  `id` int(11) NOT NULL,
  `encadreur_id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_echeance` date NOT NULL,
  `date_modification` datetime DEFAULT NULL,
  `date_completion` datetime DEFAULT NULL,
  `statut` enum('en_attente','terminee','en_retard') NOT NULL DEFAULT 'en_attente',
  `fichier_joint` varchar(255) DEFAULT NULL,
  `nom_fichier_original` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Déchargement des données de la table `taches`
--

INSERT INTO `taches` (`id`, `encadreur_id`, `stagiaire_id`, `titre`, `description`, `date_creation`, `date_echeance`, `date_modification`, `date_completion`, `statut`, `fichier_joint`, `nom_fichier_original`) VALUES
(2, 6, 7, 'manger', 'kjhdfhgjkul', '2025-08-14 10:59:14', '2025-08-14', NULL, '2025-08-14 12:18:05', 'terminee', NULL, NULL),
(5, 6, 7, 'jhsq', 'jhs', '2025-08-18 15:40:01', '2025-08-20', NULL, '2025-08-18 16:49:13', 'terminee', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `themes`
--

CREATE TABLE `themes` (
  `id` int(11) NOT NULL,
  `encadreur_id` int(11) NOT NULL,
  `stagiaire_id` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `filiere` varchar(100) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `themes`
--

INSERT INTO `themes` (`id`, `encadreur_id`, `stagiaire_id`, `titre`, `description`, `filiere`, `date_debut`, `date_fin`) VALUES
(1, 6, 8, 'gestion des colis', 'gere les colis et autre', 'genie logicielle', '2025-08-14', '2025-08-31'),
(2, 6, 7, 'gryjhkljl', 'tretyyryu', 'frehyt', '2025-08-23', '2025-08-31');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('stagiaire','encadreur','admin') NOT NULL,
  `sex` enum('M','F') NOT NULL DEFAULT 'M',
  `telephone` varchar(20) DEFAULT NULL,
  `encadreur_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `sex`, `telephone`, `encadreur_id`) VALUES
(6, 'Jean', 'Encadreur', 'encadreur@example.com', 'password', 'encadreur', 'M', '679222554', NULL),
(7, 'Marie', 'Stagiaire', 'stagiaire@example.com', 'password', 'stagiaire', 'F', '678456789', 6),
(8, 'Ngono', 'Arnaud', 'arnaud.ngono@example.com', 'password', 'stagiaire', 'M', '677123456', 6);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `encadreur`
--
ALTER TABLE `encadreur`
  ADD PRIMARY KEY (`id_utilisateur`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stagiaire_id` (`stagiaire_id`),
  ADD KEY `encadreur_id` (`encadreur_id`);

--
-- Index pour la table `jours_feries`
--
ALTER TABLE `jours_feries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expediteur_id` (`expediteur_id`),
  ADD KEY `destinataire_id` (`destinataire_id`);

--
-- Index pour la table `message_pieces_jointes`
--
ALTER TABLE `message_pieces_jointes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Index pour la table `presence`
--
ALTER TABLE `presence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stagiaire_id` (`stagiaire_id`);

--
-- Index pour la table `rapports`
--
ALTER TABLE `rapports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rapport_stagiaire` (`stagiaire_id`),
  ADD KEY `idx_rapport_date` (`date_soumission`),
  ADD KEY `idx_rapport_statut` (`statut`),
  ADD KEY `idx_rapport_type` (`type`);

--
-- Index pour la table `stagiaire`
--
ALTER TABLE `stagiaire`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD KEY `encadreur_id` (`encadreur_id`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_encadreur` (`encadreur_id`),
  ADD KEY `idx_stagiaire` (`stagiaire_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_echeance` (`date_echeance`);

--
-- Index pour la table `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `encadreur_id` (`encadreur_id`),
  ADD KEY `stagiaire_id` (`stagiaire_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `encadreur_id` (`encadreur_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jours_feries`
--
ALTER TABLE `jours_feries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `message_pieces_jointes`
--
ALTER TABLE `message_pieces_jointes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `presence`
--
ALTER TABLE `presence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `rapports`
--
ALTER TABLE `rapports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `encadreur`
--
ALTER TABLE `encadreur`
  ADD CONSTRAINT `encadreur_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`stagiaire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`encadreur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `message_pieces_jointes`
--
ALTER TABLE `message_pieces_jointes`
  ADD CONSTRAINT `message_pieces_jointes_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `presence`
--
ALTER TABLE `presence`
  ADD CONSTRAINT `presence_ibfk_1` FOREIGN KEY (`stagiaire_id`) REFERENCES `stagiaire` (`id_utilisateur`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rapports`
--
ALTER TABLE `rapports`
  ADD CONSTRAINT `fk_rapport_stagiaire` FOREIGN KEY (`stagiaire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stagiaire`
--
ALTER TABLE `stagiaire`
  ADD CONSTRAINT `stagiaire_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stagiaire_ibfk_2` FOREIGN KEY (`encadreur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `taches`
--
ALTER TABLE `taches`
  ADD CONSTRAINT `taches_ibfk_1` FOREIGN KEY (`encadreur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `taches_ibfk_2` FOREIGN KEY (`stagiaire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `themes`
--
ALTER TABLE `themes`
  ADD CONSTRAINT `themes_ibfk_1` FOREIGN KEY (`encadreur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `themes_ibfk_2` FOREIGN KEY (`stagiaire_id`) REFERENCES `stagiaire` (`id_utilisateur`) ON DELETE SET NULL;

--
-- Contraintes pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD CONSTRAINT `utilisateurs_ibfk_1` FOREIGN KEY (`encadreur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

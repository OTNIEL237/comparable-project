-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : jeu. 28 août 2025 à 19:17
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
(6, 'Responsable IT', 'Informatique'),
(13, 'info', 'genie');

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

--
-- Déchargement des données de la table `evaluations`
--

INSERT INTO `evaluations` (`id`, `stagiaire_id`, `encadreur_id`, `qualite_travail`, `autonomie`, `initiative`, `assiduite`, `communication`, `integration`, `note_globale`, `note_rapports`, `note_taches`, `note_presence`, `date_evaluation`, `jours_presence`, `jours_retard`, `jours_absence`) VALUES
(1, 7, 6, 1, 1, 0, 1, 1, 1, 16.50, 0.00, 0.00, 0.00, '2025-08-26 11:19:46', 20, 3, 1);

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
(1, 7, 6, 'Problème avec la base de données', 'Bonjour M. Jean,\n\nJ\'essaie de me connecter à la base de données de test mais j\'obtiens une erreur \"Access Denied\". Pourriez-vous vérifier mes identifiants svp ?\n\nMerci,\nMari', NULL, '2025-08-26 12:19:45', 1),
(2, 6, 7, 'Re: Problème avec la base de données', 'Bonjour Mari,\n\nLe mot de passe a été réinitialisé. Le nouveau est : StagiaireDev2025!.\nFais attention à ne pas le partager.\n\nBon courage,\nJean Encadreur', NULL, '2025-08-26 12:19:45', 1),
(37, 6, 16, 'Rappel pour la tâche en retard', 'Bonjour Arnaud,\n\nJe constate que la tâche \"Analyse des vulnérabilités OpenVAS\" est en retard. Y a-t-il un problème particulier ? Merci de me faire un retour rapidement.\n\nJean Encadreur', NULL, '2025-08-26 14:17:41', 1),
(38, 16, 6, 'Re: Rappel pour la tâche en retard', 'Bonjour M. Jean,\n\nJe suis désolé pour le retard. L\'installation d\'OpenVAS a pris plus de temps que prévu. Le scan est en cours, je vous envoie le rapport dès que possible.\n\nCordialement,\nArnaud Ngono', NULL, '2025-08-26 14:17:41', 1),
(39, 13, 6, 'Collaboration sur un projet', 'Salut Jean, j\'ai vu que tu encadrais un stagiaire sur un projet de développement. J\'ai un thème sur la CI/CD qui pourrait l\'intéresser pour la suite de son stage. On en discute ?', NULL, '2025-08-26 14:17:41', 1),
(40, 6, 13, 'Re: Collaboration sur un projet', 'Salut Evra, excellente idée ! Mari est très compétente, je pense que ce serait une excellente mission pour elle. Passons un appel demain à 10h pour en parler.', NULL, '2025-08-26 14:17:41', 0),
(41, 7, 6, 'Nouveau rapport soumis : DORMIR', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"DORMIR\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-26 18:25:27', 1),
(42, 7, 6, 'Nouveau rapport soumis : mouf me day', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"mouf me day\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-27 12:36:34', 1),
(109, 16, 6, 'Serveur inconnu sur le réseau', 'Bonjour M. Jean, lors de mon scan Nmap, j\'ai découvert un serveur à l\'adresse 192.168.1.50 dont je ne trouve aucune trace dans la documentation. Est-ce normal ?', NULL, '2025-08-27 15:24:35', 1),
(110, 6, 16, 'Re: Serveur inconnu sur le réseau', 'Bonjour Arnaud, bien vu. C\'est un ancien serveur de test que nous avons oublié d\'éteindre. Merci de l\'avoir signalé, je vais m\'en occuper.', NULL, '2025-08-27 15:24:35', 0),
(111, 1, 6, 'Point sur le stagiaire Arnaud Ngono', 'Bonjour Jean, merci de me faire un retour sur l\'avancement d\'Arnaud. J\'ai noté une tâche en retard sur son tableau de bord.', NULL, '2025-08-27 15:24:35', 1),
(112, 13, 6, 'Collaboration sur un projet', 'Salut Jean, j\'ai vu que tu encadrais un stagiaire sur un projet de développement. J\'ai un thème sur la CI/CD qui pourrait l\'intéresser. On en discute ?', NULL, '2025-08-27 15:24:35', 1),
(113, 6, 13, 'Re: Collaboration sur un projet', 'Salut Evra, excellente idée ! Mari est très compétente, je pense que ce serait une excellente mission pour elle. Passons un appel demain à 10h pour en parler.', NULL, '2025-08-27 15:24:35', 0),
(114, 7, 16, 'Salut !', 'Salut Arnaud, bon courage pour ton stage en sécurité !', NULL, '2025-08-27 15:24:35', 1),
(115, 16, 7, 'Re: Salut !', 'Merci Mari, à toi aussi pour l\'intranet !', NULL, '2025-08-27 15:24:35', 0),
(116, 6, 7, 'Point d\'avancement projet', 'Bonjour Mari, pouvez-vous me préparer un résumé de l\'avancement pour notre point de demain matin à 9h ?', NULL, '2025-08-27 15:24:35', 0),
(117, 7, 6, 'Nouveau rapport soumis : manger', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"manger\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 11:10:11', 0),
(118, 7, 6, 'Nouveau rapport soumis : manger', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"manger\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 16:04:45', 0),
(119, 7, 6, 'Nouveau rapport soumis : 1', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"1\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 16:24:13', 0),
(120, 7, 6, 'Nouveau rapport soumis : sndkajh', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"sndkajh\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 16:35:08', 0),
(121, 7, 6, 'Nouveau rapport soumis : sldjwlak', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"sldjwlak\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 16:36:33', 0),
(122, 7, 6, 'Nouveau rapport soumis : nvlkdnclka', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"nvlkdnclka\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 17:18:15', 0),
(123, 7, 6, 'Nouveau rapport soumis : i jasjcklsa', 'Bonjour,\n\nLe stagiaire Stagiaire Mari a soumis un nouveau rapport intitulé : \"i jasjcklsa\".\n\nLe rapport est maintenant disponible dans votre onglet \'Rapports\' en attente de validation.\n\nCordialement,\nSystème de Gestion des Stagiaires', NULL, '2025-08-28 17:27:06', 0);

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
(1, 1, 'screenshot_error.png', 'demo_error.png');

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
(1, 7, '2025-08-25', 'present', '08:05:10', '17:02:00', NULL, NULL, 'Akwa, Douala, Cameroun'),
(2, 7, '2025-08-26', 'retard', '08:50:00', '17:10:00', NULL, NULL, 'Akwa, Douala, Cameroun'),
(3, 16, '2025-08-25', 'present', '07:58:00', '17:05:00', NULL, NULL, 'Akwa, Douala, Cameroun'),
(4, 16, '2025-08-26', 'absent', NULL, NULL, NULL, NULL, NULL),
(5, 7, '2025-08-27', 'present', '08:02:00', '17:01:00', NULL, NULL, 'Akwa, Douala, Cameroun'),
(6, 16, '2025-08-26', 'present', '08:14:50', '17:05:30', NULL, NULL, 'Akwa, Douala, Cameroun'),
(7, 7, '2025-08-27', 'present', '08:02:00', '17:01:00', NULL, NULL, 'Akwa, Douala, Cameroun'),
(8, 16, '2025-08-27', 'present', '08:14:50', '17:05:30', NULL, NULL, 'Bali, Douala, Cameroun');

-- --------------------------------------------------------

--
-- Structure de la table `rapports`
--

CREATE TABLE `rapports` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `tache_id` int(11) DEFAULT NULL,
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

INSERT INTO `rapports` (`id`, `stagiaire_id`, `tache_id`, `type`, `titre`, `activites`, `difficultes`, `solutions`, `date_soumission`, `statut`, `commentaire_encadreur`, `fichier_pdf`, `date_validation`) VALUES
(1, 7, NULL, 'hebdomadaire', 'Rapport Semaine 1 : Prise en main et analyse', 'Installation de l\'environnement de développement (XAMPP, VS Code). Lecture de la documentation du projet. Premières ébauches de la structure de la base de données.', 'Compréhension du système d\'authentification existant à faire évoluer.', 'Session de travail avec un développeur de l\'équipe pour clarifier les points bloquants.', '2025-08-24 16:30:00', 'validé', 'Bon début Mari. L\'initiative de demander de l\'aide est une excellente chose. Continuez sur cette lancée.', 'rapport_mari_s1.pdf', NULL),
(2, 16, 2, 'journalier', 'Rapport du 25/08 : Scan Nmap', 'Scan du réseau local effectué. 25 hôtes identifiés. Un serveur inconnu a été découvert à l\'adresse 192.168.1.50.', 'Le scan a été plusieurs fois interrompu par des règles de sécurité réseau.', 'J\'ai dû ajuster les options de Nmap pour effectuer un scan plus discret (-sS -T2).', '2025-08-25 16:00:00', 'validé', 'bien', 'rapport_arnaud_2508.pdf', NULL),
(3, 7, 1, 'journalier', 'Rapport du 26/08 : Maquettage Figma', 'Finalisation de la maquette de la page d\'accueil et début de la page \"Annuaire du personnel\".', 'Aucune difficulté particulière.', 'Utilisation des composants Figma pour accélérer le design.', '2025-08-26 09:00:00', 'en attente', NULL, 'rapport_mari_2608.pdf', NULL),
(4, 16, NULL, 'journalier', 'Rapport du 26/08 : Analyse des configurations', 'Vérification des fichiers de configuration des routeurs Cisco. Identification de plusieurs ports ouverts non nécessaires.', 'Difficulté à accéder à un des équipements dont le mot de passe admin a été perdu.', 'J\'ai dû utiliser une procédure de récupération de mot de passe via la console série de l\'équipement.', '2025-08-26 10:20:00', '', NULL, NULL, NULL),
(5, 7, NULL, 'journalier', 'Rapport du 26/08 : Intégration page accueil', 'Intégration HTML/CSS de la maquette Figma pour la page d\'accueil. La page est maintenant responsive.', 'L\'affichage sur Safari mobile présentait quelques bugs mineurs.', 'Utilisation de préfixes vendeurs (-webkit-) pour assurer la compatibilité.', '2025-08-26 10:25:00', '', NULL, NULL, NULL),
(14, 7, NULL, 'journalier', 'Rapport du 25/09 : Audit de sécurité', 'Tests XSS et injections SQL effectués sur le formulaire de login. Aucune faille majeure trouvée.', 'Comprendre les différents types de XSS.', 'Lecture de la documentation de l\'OWASP.', '2025-09-25 16:00:00', 'en attente', NULL, NULL, NULL),
(15, 7, 16, 'hebdomadaire', 'Rapport S7 : Responsive Design', 'Toutes les pages sont maintenant adaptées aux mobiles et tablettes. Tests effectués sur Chrome DevTools.', 'Certains tableaux étaient difficiles à afficher sur mobile.', 'Mise en place d\'un scroll horizontal pour les tableaux larges.', '2025-10-03 16:00:00', 'en attente', NULL, NULL, NULL),
(18, 7, NULL, 'journalier', 'Rapport du 19/08 : Charte Graphique', 'Document de la charte graphique lu et compris. Les couleurs et polices principales ont été notées.', 'Aucune difficulté.', '', '2025-08-19 13:00:00', 'validé', 'Parfait, c\'est un bon réflexe avant de commencer le design.', NULL, NULL),
(45, 16, NULL, 'hebdomadaire', 'Rapport S5 : Framework MITRE', 'La note de synthèse est rédigée. Elle explique comment mapper nos alertes de sécurité aux tactiques et techniques de l\'ATT&CK.', 'Le framework est très vaste.', 'Je me suis concentré sur les techniques les plus pertinentes pour les serveurs web.', '2025-10-03 16:00:00', 'en attente', NULL, NULL, NULL),
(46, 16, NULL, 'journalier', 'Rapport du 09/10 : Scan ZAP', 'Le scan automatisé du site web a révélé 3 vulnérabilités de type \"Cross-Site Scripting (Reflected)\".', 'Distinguer les faux positifs des vraies vulnérabilités.', 'Vérification manuelle en injectant des payloads de test.', '2025-10-09 16:00:00', 'en attente', NULL, NULL, NULL),
(48, 16, NULL, 'hebdomadaire', 'Rapport S7 : Rapport Final', 'Le plan du rapport final est prêt. La compilation des résultats a commencé.', 'Structurer une grande quantité d\'informations.', 'Utilisation d\'un template standard de rapport de pentest.', '2025-10-14 16:00:00', 'validé', '', NULL, NULL),
(50, 7, NULL, 'journalier', 'Rapport du 27/08 : Finalisation MCD', 'Le MCD est terminé et validé. Début de la rédaction du dictionnaire de données.', 'Normaliser la table des adresses.', 'Création d\'une table séparée pour les villes et régions.', '2025-08-27 16:00:00', 'validé', 'Très propre. On peut passer au MLD.', NULL, NULL),
(51, 7, NULL, 'hebdomadaire', 'Rapport S3 : Module Actualités', 'Le CRUD pour les actualités est fonctionnel. L\'éditeur de texte riche (TinyMCE) est intégré.', 'L\'upload d\'images était complexe à sécuriser.', 'J\'ai mis en place une validation stricte des types MIME et un renommage des fichiers.', '2025-09-05 15:30:00', 'en attente', NULL, NULL, NULL),
(52, 7, NULL, 'journalier', 'Rapport du 10/09 : Recherche Annuaire', 'La barre de recherche en AJAX fonctionne. Elle filtre les résultats en temps réel à chaque touche pressée.', 'Optimiser la requête pour éviter de surcharger le serveur.', 'Mise en place d\'un \"debounce\" de 300ms en JavaScript.', '2025-09-10 16:15:00', 'en attente', NULL, NULL, NULL),
(53, 7, 14, 'journalier', 'Rapport du 18/09 : Upload de fichiers', 'La fonctionnalité d\'upload est prête. Les fichiers sont stockés dans un dossier sécurisé hors de la racine web.', 'Gérer les fichiers volumineux.', 'Augmentation de `upload_max_filesize` et `post_max_size` dans le php.ini.', '2025-09-18 16:00:00', 'en attente', NULL, NULL, NULL),
(56, 7, 17, 'journalier', 'Rapport du 09/10 : Doc utilisateur', 'Le guide pour les employés est rédigé à 50%.', 'Faire des captures d\'écran claires.', 'Utilisation d\'un outil de capture d\'écran avec annotations.', '2025-10-09 16:00:00', 'en attente', NULL, NULL, NULL),
(57, 7, 18, 'journalier', 'Rapport du 14/10 : Présentation', 'Le plan de la présentation est fait. 5 slides sur 15 sont créées.', 'Synthétiser 3 mois de travail en 20 minutes.', 'Je me concentre sur les défis techniques et les solutions apportées.', '2025-10-14 16:00:00', 'validé', '', NULL, NULL),
(59, 7, NULL, 'hebdomadaire', 'Rapport S6 : Déploiement', 'La version de test est en ligne sur le serveur de pré-production.', 'Problèmes avec les chemins de fichiers sur le serveur Linux.', 'Utilisation de chemins absolus et de variables d\'environnement.', '2025-10-01 16:00:00', 'en attente', NULL, NULL, NULL),
(60, 16, 21, 'journalier', 'Rapport du 28/08 : Audit Mots de Passe', 'L\'audit est terminé. La politique actuelle est trop faible (6 caractères, pas de complexité requise).', 'Aucune difficulté.', 'Proposition d\'une nouvelle politique basée sur les recommandations de l\'ANSSI.', '2025-08-28 15:00:00', 'validé', 'Bonnes recommandations, nous allons les appliquer.', NULL, NULL),
(61, 16, NULL, 'hebdomadaire', 'Rapport S2 : Analyse des Logs', 'Analyse des logs du firewall terminée. Plusieurs tentatives de connexion depuis des IPs suspectes ont été identifiées.', 'Le volume de logs est énorme.', 'Utilisation de scripts `grep` et `awk` pour filtrer les événements pertinents.', '2025-09-05 16:00:00', 'en attente', NULL, NULL, NULL),
(62, 16, 23, 'journalier', 'Rapport du 11/09 : Campagne Phishing', 'Le template de l\'email de phishing a été créé. La liste des cibles est prête.', 'Rédiger un email crédible sans être trop alarmiste.', 'Je me suis inspiré d\'exemples réels de campagnes de phishing.', '2025-09-11 16:00:00', 'en attente', NULL, NULL, NULL),
(63, 16, 24, 'hebdomadaire', 'Rapport S4 : Honeypot', 'Le honeypot a été déployé. Il simule un service SSH vulnérable. Déjà 3 tentatives de connexion brute-force enregistrées.', 'Isoler le honeypot du reste du réseau.', 'Création d\'un VLAN dédié pour le honeypot.', '2025-09-19 16:00:00', 'en attente', NULL, NULL, NULL),
(64, 16, NULL, 'journalier', 'Rapport du 25/09 : Patch Management', 'La liste des serveurs critiques à mettre à jour a été établie. Le serveur web principal a une vulnérabilité critique Apache.', 'Planifier les mises à jour sans interrompre la production.', 'Proposition d\'une fenêtre de maintenance pour ce week-end.', '2025-09-25 16:00:00', 'en attente', NULL, NULL, NULL),
(67, 16, NULL, 'journalier', 'Rapport du 01/09 : Crypto', 'Résumé sur RSA et ECC terminé.', 'Comprendre les mathématiques sous-jacentes.', 'Concentration sur les cas d\'usage pratiques.', '2025-09-01 16:00:00', 'validé', 'Bonne synthèse.', NULL, NULL),
(69, 16, NULL, 'journalier', 'Rapport du 08/09 : Fail2Ban', 'Fail2Ban est installé et configuré pour surveiller les logs SSH. 2 IPs ont déjà été bannies.', 'Adapter les expressions régulières.', 'Utilisation d\'outils en ligne pour tester les regex.', '2025-09-08 16:00:00', 'validé', 'Excellent !', NULL, NULL),
(70, 7, NULL, 'hebdomadaire', 'Rapport S3 : Module Recherche Annuaire', 'La recherche en AJAX est fonctionnelle.', 'Optimiser la requête pour la performance.', 'Mise en place d\'un \"debounce\" de 300ms en JavaScript.', '2025-09-12 16:00:00', 'en attente', NULL, NULL, NULL),
(71, 16, NULL, 'hebdomadaire', 'Rapport S2 : Analyse des Logs', 'Analyse des logs terminée. Plusieurs IPs suspectes identifiées.', 'Le volume de logs était énorme.', 'Utilisation de scripts `grep` pour filtrer les événements.', '2025-09-05 16:30:00', 'en attente', NULL, NULL, NULL),
(72, 7, 5, 'journalier', 'manger', 'skmkcljwe', 'idiuddew', 'hdkuqdq', '2025-08-28 10:10:11', 'en attente', NULL, 'rapport_7_72_2025-08-28_11-10-11.pdf', NULL),
(73, 7, 9, 'journalier', 'manger', 'hsfbjkwehkjf', 'alkjflkqewj', 'qkhkjeqwhfq', '2025-08-28 15:04:45', 'en attente', NULL, 'rapport_7_73_2025-08-28_16-04-45.pdf', NULL),
(74, 7, 9, 'mensuel', '1', '1', '23', '3', '2025-08-28 15:24:13', 'en attente', NULL, 'rapport_7_74_2025-08-28_16-24-13.pdf', NULL),
(75, 7, 9, 'mensuel', 'sndkajh', 'djbwqjkdhqw', 'dkwqhdkjqwh', 'nmqbjkqw', '2025-08-28 15:35:08', 'en attente', NULL, 'rapport_7_75_2025-08-28_16-35-08.pdf', NULL),
(76, 7, 73, 'mensuel', 'sldjwlak', 'dwnkfdqwlk', 'dhwkjqdfqwdmwqndkjhqwlk', 'ljwfkqneklfnwq', '2025-08-28 15:36:33', 'en attente', NULL, 'rapport_7_76_2025-08-28_16-36-33.pdf', NULL),
(77, 7, 14, 'mensuel', 'nvlkdnclka', 'cjhakjhclkasj', ',hkjwhjkfwq', 'fjnklwanckasnm', '2025-08-28 16:18:15', 'en attente', NULL, 'rapport_7_77_2025-08-28_17-18-15.pdf', NULL),
(78, 7, 14, 'mensuel', 'i jasjcklsa', 'lsakcjlksacm', ';aslkcj;asm', ',anc,asncjasklj', '2025-08-28 16:27:05', 'en attente', NULL, 'rapport_7_78_2025-08-28_17-27-06.pdf', NULL);

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
(16, 6, 'genie logicielle', '3', '2025-08-22', '2025-08-28'),
(18, NULL, 'genie logicielle', '3', '2025-08-28', '2025-09-07');

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
(1, 6, 7, 'Maquette de la page d\'accueil de l\'intranet', 'Créer une maquette fonctionnelle de la page d\'accueil en utilisant l\'outil Figma. Le design doit être moderne et responsive. Le fichier Figma est attendu.', '2025-08-26 12:19:45', '2025-08-25', NULL, '2025-08-25 14:00:00', 'terminee', 'demo_maquette.fig', 'maquette_accueil.fig'),
(2, 6, 16, 'Scanner le réseau avec Nmap', 'Effectuer un scan complet du sous-réseau 192.168.1.0/24 pour identifier tous les hôtes actifs et les ports ouverts. Produire un rapport de scan.', '2025-08-26 12:19:45', '2025-08-27', NULL, '2025-08-26 15:52:56', 'terminee', NULL, NULL),
(5, 6, 7, 'Créer le script de la base de données', 'Écrire le script SQL complet (CREATE TABLE) basé sur le diagramme de classes validé, en incluant les contraintes de clés primaires et étrangères.', '2025-08-26 12:42:17', '2025-08-29', NULL, '2025-08-28 14:21:42', 'terminee', NULL, NULL),
(6, 13, 16, 'Préparer la présentation de l\'audit', 'Créer un support de présentation (PowerPoint) résumant les failles de sécurité trouvées et les recommandations proposées.', '2025-08-26 12:42:17', '2025-09-10', NULL, NULL, 'en_attente', NULL, NULL),
(7, 6, 7, 'Développer la page \"Annuaire du personnel\"', 'Créer la page qui liste tous les employés avec une barre de recherche. Les données seront récupérées via une requête AJAX vers l\'API.', '2025-08-26 12:42:17', '2025-09-12', NULL, NULL, 'en_attente', NULL, NULL),
(8, 1, 16, 'Faire une veille sur les dernières cyberattaques', 'Rédiger une note de synthèse d\'une page sur les 3 types de cyberattaques les plus courantes au Cameroun en 2025.', '2025-08-26 12:42:17', '2025-09-02', NULL, NULL, 'en_attente', NULL, NULL),
(9, 6, 7, 'Développer le module d\'authentification', 'Mettre en place le système de connexion/déconnexion en PHP avec gestion des sessions sécurisées pour l\'intranet.', '2025-08-26 14:17:41', '2025-09-05', NULL, NULL, 'en_attente', NULL, NULL),
(10, 6, 16, 'Analyse des vulnérabilités OpenVAS', 'Installer et configurer l\'outil OpenVAS pour lancer un scan de vulnérabilités sur les serveurs identifiés. Le rapport est attendu pour la fin de semaine.', '2025-08-26 14:17:41', '2025-08-29', NULL, '2025-08-26 15:53:22', 'terminee', NULL, NULL),
(14, 6, 7, 'Création du système d\'upload de documents', 'Développer la fonctionnalité permettant aux admins de téléverser des documents (PDF, Word) sur le portail.', '2025-08-27 15:02:06', '2025-09-19', NULL, NULL, 'en_attente', NULL, NULL),
(16, 6, 7, 'Intégration du design responsive', 'S\'assurer que toutes les pages de l\'intranet s\'affichent correctement sur mobile et tablette.', '2025-08-27 15:02:06', '2025-10-03', NULL, NULL, 'en_attente', NULL, NULL),
(17, 6, 7, 'Rédaction de la documentation utilisateur', 'Rédiger un guide simple pour les employés expliquant comment utiliser le nouvel intranet.', '2025-08-27 15:02:06', '2025-10-10', NULL, NULL, 'en_attente', NULL, NULL),
(18, 6, 7, 'Préparation de la présentation finale du projet', 'Créer un support PowerPoint pour la soutenance de fin de stage.', '2025-08-27 15:02:06', '2025-10-15', NULL, NULL, 'en_attente', NULL, NULL),
(21, 6, 16, 'Audit des politiques de mot de passe', 'Vérifier la politique de mot de passe actuelle et proposer des recommandations conformes aux standards ANSSI.', '2025-08-27 15:02:06', '2025-08-29', NULL, '2025-08-28 17:00:00', 'terminee', NULL, NULL),
(23, 6, 16, 'Test de phishing interne', 'Préparer et lancer une campagne de simulation de phishing contrôlée pour sensibiliser les employés.', '2025-08-27 15:02:06', '2025-09-12', NULL, NULL, 'en_attente', NULL, NULL),
(24, 6, 16, 'Configuration d\'un honeypot', 'Mettre en place un pot de miel (honeypot) simple pour attirer et analyser les tentatives d\'attaques.', '2025-08-27 15:02:06', '2025-09-19', NULL, NULL, 'en_attente', NULL, NULL),
(31, 6, 7, 'Conception de la base de données de l\'intranet', 'Créer le MCD et MLD pour les modules actualités, annuaire et documents.', '2025-08-27 15:12:02', '2025-08-28', NULL, '2025-08-27 15:00:00', 'terminee', NULL, NULL),
(40, 1, 7, 'Déployer une version de test', 'Mettre en ligne une version de démonstration de l\'intranet sur un serveur de pré-production.', '2025-08-27 15:12:02', '2025-10-01', NULL, NULL, 'en_attente', NULL, NULL),
(45, 6, 16, 'Mise à jour des serveurs non patchés', 'Identifier les serveurs avec des mises à jour de sécurité critiques manquantes et planifier leur mise à jour.', '2025-08-27 15:12:02', '2025-09-26', NULL, NULL, 'en_attente', NULL, NULL),
(48, 13, 16, 'Revoir les bases de la cryptographie asymétrique', 'Faire une recherche sur les principes de RSA et ECC et préparer un résumé.', '2025-08-27 15:12:02', '2025-09-01', NULL, NULL, 'en_attente', NULL, NULL),
(50, 13, 16, 'Configuration de Fail2Ban sur le serveur web', 'Installer et configurer Fail2Ban pour bannir automatiquement les IPs qui tentent des attaques par force brute.', '2025-08-27 15:12:02', '2025-09-08', NULL, NULL, 'en_attente', NULL, NULL),
(66, 6, 16, 'Recherche sur le framework MITRE ATT&CK', 'Rédiger une synthèse expliquant comment le framework MITRE ATT&CK peut être utilisé pour améliorer notre sécurité.', '2025-08-27 15:20:49', '2025-10-03', NULL, NULL, 'en_attente', NULL, NULL),
(67, 6, 16, 'Scanner les applications web avec OWASP ZAP', 'Utiliser l\'outil OWASP ZAP pour scanner le site web public de l\'entreprise à la recherche de failles communes.', '2025-08-27 15:20:49', '2025-10-10', NULL, NULL, 'en_attente', NULL, NULL),
(69, 1, 16, 'Rapport de vulnérabilité final', 'Compiler tous les résultats des audits et scans dans un rapport final destiné à la direction.', '2025-08-27 15:20:49', '2025-10-15', NULL, NULL, 'en_attente', NULL, NULL),
(73, 6, 7, 'Mise en place de la recherche dans l\'annuaire', 'Implémenter une barre de recherche dynamique (AJAX) pour filtrer les employés par nom ou service.', '2025-08-27 15:21:20', '2025-09-12', NULL, NULL, 'en_attente', NULL, NULL),
(75, 6, 7, 'Tests de sécurité sur le formulaire de login', 'Effectuer des tests pour prévenir les injections SQL et les attaques XSS sur la page de connexion.', '2025-08-27 15:21:20', '2025-09-26', NULL, NULL, 'en_attente', NULL, NULL),
(79, 1, 7, 'Relecture de la charte graphique', 'Faire une relecture de la charte graphique de l\'entreprise avant d\'attaquer le design.', '2025-08-27 15:21:20', '2025-08-20', NULL, '2025-08-19 11:00:00', 'terminee', NULL, NULL);

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
(1, 6, 7, 'Développement du portail intranet de l\'entreprise', 'Analyse, conception et développement d\'un portail web interne pour la gestion des actualités, des documents et d\'un annuaire du personnel. Technologies : PHP, MySQL, JavaScript.', 'Informatique', '2025-08-17', '2025-11-17'),
(2, 6, 16, 'Audit de sécurité du réseau local (LAN)', 'Cartographie du réseau, analyse des vulnérabilités des équipements  et proposition d\'un plan de renforcement de la sécurité.', 'Réseaux & Sécurité', '2025-08-22', '2025-10-22'),
(11, 13, NULL, 'Optimisation des requêtes SQL d\'une application', 'Analyser le code d\'une application existante, identifier les requêtes lentes à l\'aide de l\'EXPLAIN plan et proposer des optimisations (index, réécriture).', 'Génie Logiciel', '2025-09-01', '2025-10-31'),
(12, 6, NULL, 'Déploiement d\'un serveur de monitoring Nagios', 'Installer et configurer Nagios pour superviser l\'état des serveurs critiques (CPU, RAM, Disque, Services Web). Mettre en place un système d\'alertes par email.', 'Réseaux & Administration Système', '2025-09-01', '2025-11-15');

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
  `statut` enum('actif','bloque') NOT NULL DEFAULT 'actif',
  `sex` enum('M','F') NOT NULL DEFAULT 'M',
  `telephone` varchar(20) DEFAULT NULL,
  `encadreur_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `statut`, `sex`, `telephone`, `encadreur_id`) VALUES
(1, 'Admin', 'Principal', 'admin@example.com', '$2y$10$wT3J0kY3F4W5yB9x2n1c.uH9sH0/8o9g0i7j6k5l4m3n2o1p.', 'admin', 'actif', 'M', '655555555', NULL),
(6, 'Jean', 'Encadreur', 'encadreur@example.com', '$2y$10$nuYyY1AbwzDPRxbd0zlp6OBaqOuojc96sAxBDROHMOtRjgMkk26Z2', 'encadreur', 'actif', 'M', '679222554', NULL),
(7, 'Mari', 'Stagiaire', 'stagiaire@example.com', '$2y$10$tV0dSgzlNFe6L9cjSqmt.eOf2blOFqzJg23dOeHSHCh0W3qStDl9G', 'stagiaire', 'actif', 'F', '678456789', 6),
(13, 'eyebe', 'evra', 'evra123@gmail.com', 'password', 'encadreur', 'actif', 'M', '679222554', NULL),
(16, 'Ngono', 'Arnaud', 'arnaud.ngono@example.com', 'password', 'stagiaire', 'actif', 'M', '677123456', NULL),
(18, 'eyebe', 'otniel', 'o6066990@gmail.com', 'password', 'stagiaire', 'actif', 'M', '679222554', NULL);

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
  ADD KEY `idx_rapport_type` (`type`),
  ADD KEY `fk_rapport_tache` (`tache_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `jours_feries`
--
ALTER TABLE `jours_feries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT pour la table `message_pieces_jointes`
--
ALTER TABLE `message_pieces_jointes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `presence`
--
ALTER TABLE `presence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `rapports`
--
ALTER TABLE `rapports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT pour la table `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  ADD CONSTRAINT `fk_rapport_stagiaire` FOREIGN KEY (`stagiaire_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rapport_tache` FOREIGN KEY (`tache_id`) REFERENCES `taches` (`id`) ON DELETE SET NULL;

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

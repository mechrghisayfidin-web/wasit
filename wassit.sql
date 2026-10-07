-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 11 juin 2026 à 21:18
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
-- Base de données : `wassit`
--

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `ecole_id` int(11) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `ecole_id`, `nom`, `niveau`) VALUES
(1, 1, 'باك علوم إعلامية أ', 'باكالوريا'),
(2, 1, 'باك علوم إعلامية ب', 'باكالوريا'),
(3, 1, 'باك رياضيات', 'باكالوريا');

-- --------------------------------------------------------

--
-- Structure de la table `ecoles`
--

CREATE TABLE `ecoles` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ecoles`
--

INSERT INTO `ecoles` (`id`, `nom`, `email`, `mot_de_passe`) VALUES
(1, 'zebi', 'deinsaif97@gmail.com', 'print(x)'),
(2, 'معهد النجاح الخاص', 'contact@najah.tn', '123456');

-- --------------------------------------------------------

--
-- Structure de la table `elevess`
--

CREATE TABLE `elevess` (
  `id` int(11) NOT NULL,
  `ecole_id` int(11) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `classe` varchar(50) DEFAULT NULL,
  `classe_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `elevess`
--

INSERT INTO `elevess` (`id`, `ecole_id`, `nom`, `parent_id`, `classe`, `classe_id`) VALUES
(1, 1, 'يوسف بن علي', 4, 'باك علوم إعلامية', NULL),
(2, 1, 'مريم بن علي', 4, 'باك رياضيات', NULL),
(3, 1, 'أنس بن سمير', 5, 'باك علوم إعلامية', NULL),
(4, 1, 'لينا بن سمير', 5, 'باك آداب', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `epreuves`
--

CREATE TABLE `epreuves` (
  `id` int(11) NOT NULL,
  `classe_id` int(11) DEFAULT NULL,
  `professeur_id` int(11) DEFAULT NULL,
  `matiere` varchar(100) DEFAULT NULL,
  `titre` varchar(100) DEFAULT NULL,
  `date_epreuve` date DEFAULT NULL,
  `note_max` decimal(5,2) DEFAULT 20.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `matieres_classes`
--

CREATE TABLE `matieres_classes` (
  `id` int(11) NOT NULL,
  `classe_id` int(11) DEFAULT NULL,
  `professeur_id` int(11) DEFAULT NULL,
  `matiere` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `expediteur_id` int(11) DEFAULT NULL,
  `destinataire_id` int(11) DEFAULT NULL,
  `sujet` varchar(200) DEFAULT NULL,
  `contenu` text DEFAULT NULL,
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `date_envoi` datetime DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `epreuve_id` int(11) DEFAULT NULL,
  `eleve_id` int(11) DEFAULT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  `statut` enum('en_attente','confirme') DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `observations`
--

CREATE TABLE `observations` (
  `id` int(11) NOT NULL,
  `professeur_id` int(11) DEFAULT NULL,
  `eleve_id` int(11) DEFAULT NULL,
  `contenu` text DEFAULT NULL,
  `date_obs` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `observations`
--

INSERT INTO `observations` (`id`, `professeur_id`, `eleve_id`, `contenu`, `date_obs`) VALUES
(1, 2, 1, 'يوسف يشارك بنشاط في القسم، لكن يحتاج تحسين في التمارين التطبيقية', '2025-05-10 09:30:00'),
(2, 2, 1, 'تحسن ملحوظ هذا الأسبوع، واصل يوسف!', '2025-05-17 09:30:00'),
(3, 3, 2, 'مريم متفوقة في الرياضيات، أداء ممتاز في الفرض', '2025-05-12 10:00:00'),
(4, 2, 3, 'أنس يحتاج مزيد من التركيز، كثير الحديث داخل القسم', '2025-05-11 08:00:00'),
(5, 3, 4, 'لينا هادئة ومجتهدة، لكن تحتاج تشجيع للمشاركة أكثر', '2025-05-13 11:00:00'),
(6, 2, 1, 'يوسف لم يسلم واجب هذا الأسبوع', '2025-05-20 09:00:00'),
(7, 3, 3, 'تحسن في سلوك أنس هذا الأسبوع، شكراً للأسرة', '2025-05-21 10:30:00');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `ecole_id` int(11) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','professeur','parent') DEFAULT NULL,
  `matiere` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `ecole_id`, `nom`, `email`, `mot_de_passe`, `role`, `matiere`) VALUES
(2, 1, 'المدير أحمد', 'admin@najah.tn', '$2y$10$R0pDHxbVTUvBWjGZXVYCjO.BqlFFPmyQkzk/k3Tv/PuDbUddW4tgi', 'admin', NULL),
(3, 1, 'أستاذ محمد', 'mohamed@najah.tn', '$2y$10$R0pDHxbVTUvBWjGZXVYCjO.BqlFFPmyQkzk/k3Tv/PuDbUddW4tgi', 'professeur', NULL),
(4, 1, 'أستاذة فاطمة', 'fatma@najah.tn', '$2y$10$R0pDHxbVTUvBWjGZXVYCjO.BqlFFPmyQkzk/k3Tv/PuDbUddW4tgi', 'professeur', NULL),
(5, 1, 'ولي أمر علي', 'ali.parent@gmail.com', '$2y$10$R0pDHxbVTUvBWjGZXVYCjO.BqlFFPmyQkzk/k3Tv/PuDbUddW4tgi', 'parent', NULL),
(6, 1, 'ولي أمر سمير', 'samir.parent@gmail.com', '$2y$10$R0pDHxbVTUvBWjGZXVYCjO.BqlFFPmyQkzk/k3Tv/PuDbUddW4tgi', 'parent', NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ecole_id` (`ecole_id`);

--
-- Index pour la table `ecoles`
--
ALTER TABLE `ecoles`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `elevess`
--
ALTER TABLE `elevess`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ecole_id` (`ecole_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `classe_id` (`classe_id`);

--
-- Index pour la table `epreuves`
--
ALTER TABLE `epreuves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `professeur_id` (`professeur_id`);

--
-- Index pour la table `matieres_classes`
--
ALTER TABLE `matieres_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `professeur_id` (`professeur_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expediteur_id` (`expediteur_id`),
  ADD KEY `destinataire_id` (`destinataire_id`);

--
-- Index pour la table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `epreuve_id` (`epreuve_id`),
  ADD KEY `eleve_id` (`eleve_id`);

--
-- Index pour la table `observations`
--
ALTER TABLE `observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professeur_id` (`professeur_id`),
  ADD KEY `eleve_id` (`eleve_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ecole_id` (`ecole_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `ecoles`
--
ALTER TABLE `ecoles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `elevess`
--
ALTER TABLE `elevess`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `epreuves`
--
ALTER TABLE `epreuves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `matieres_classes`
--
ALTER TABLE `matieres_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `observations`
--
ALTER TABLE `observations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`ecole_id`) REFERENCES `ecoles` (`id`);

--
-- Contraintes pour la table `elevess`
--
ALTER TABLE `elevess`
  ADD CONSTRAINT `elevess_ibfk_1` FOREIGN KEY (`ecole_id`) REFERENCES `ecoles` (`id`),
  ADD CONSTRAINT `elevess_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `elevess_ibfk_3` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`);

--
-- Contraintes pour la table `epreuves`
--
ALTER TABLE `epreuves`
  ADD CONSTRAINT `epreuves_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `epreuves_ibfk_2` FOREIGN KEY (`professeur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `matieres_classes`
--
ALTER TABLE `matieres_classes`
  ADD CONSTRAINT `matieres_classes_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `matieres_classes_ibfk_2` FOREIGN KEY (`professeur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`epreuve_id`) REFERENCES `epreuves` (`id`),
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `elevess` (`id`);

--
-- Contraintes pour la table `observations`
--
ALTER TABLE `observations`
  ADD CONSTRAINT `observations_ibfk_1` FOREIGN KEY (`professeur_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `observations_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `elevess` (`id`);

--
-- Contraintes pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD CONSTRAINT `utilisateurs_ibfk_1` FOREIGN KEY (`ecole_id`) REFERENCES `ecoles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

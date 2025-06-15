-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 11 juin 2025 à 02:20
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
-- Base de données : `stock_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `achats`
--

CREATE TABLE `achats` (
  `id_achat` int(11) NOT NULL,
  `id_utilisateur` int(11) NOT NULL,
  `date_achat` timestamp NOT NULL DEFAULT current_timestamp(),
  `montant_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `achats`
--

INSERT INTO `achats` (`id_achat`, `id_utilisateur`, `date_achat`, `montant_total`) VALUES
(1, 5, '2025-04-19 09:44:26', 401.76),
(2, 5, '2025-04-19 13:46:42', 414.00),
(3, 5, '2025-04-19 13:47:56', 405.76),
(4, 5, '2025-04-19 13:58:25', 207.52);

-- --------------------------------------------------------

--
-- Structure de la table `achat_produits`
--

CREATE TABLE `achat_produits` (
  `id_achat_produit` int(11) NOT NULL,
  `id_achat` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `achat_produits`
--

INSERT INTO `achat_produits` (`id_achat_produit`, `id_achat`, `id_produit`, `quantite`, `prix_unitaire`) VALUES
(1, 1, 10, 2, 0.88),
(4, 2, 11, 7, 2.00),
(5, 3, 10, 2, 0.88),
(6, 3, 11, 2, 2.00),
(9, 4, 10, 4, 0.88),
(10, 4, 11, 2, 2.00);

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

CREATE TABLE `client` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `solde` decimal(10,2) DEFAULT NULL,
  `num_commande` varchar(50) DEFAULT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`id`, `name`, `address`, `solde`, `num_commande`, `id_utilisateur`, `password`) VALUES
(5, 'fatima@tms.com', 'msala', 21194.72, '1111111', NULL, '$2y$10$hnuj0asfQ2rz.CVPjvmNZu4Mhj6TcdlESThmRxe/K17K8UN1h8MCe');

-- --------------------------------------------------------

--
-- Structure de la table `messages_admin`
--

CREATE TABLE `messages_admin` (
  `id` int(11) NOT NULL,
  `contenu` text DEFAULT NULL,
  `date_publication` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages_admin`
--

INSERT INTO `messages_admin` (`id`, `contenu`, `date_publication`) VALUES
(1, 'bonjour', '2025-06-09 14:11:17'),
(2, 'bonjour', '2025-06-09 14:18:21'),
(3, 'bonjour', '2025-06-09 14:18:25'),
(4, 'noubliez pas', '2025-06-09 14:18:35'),
(5, 'hi', '2025-06-09 14:19:04'),
(6, 'vérifiez votre stock aujourd\'hui', '2025-06-09 15:58:12'),
(7, 'il y a une reunion aujourd\'hui à 15h', '2025-06-09 16:00:27'),
(8, 'biba lghmari', '2025-06-09 16:02:23'),
(9, 'bonsoir', '2025-06-09 16:03:05'),
(10, 'i love u', '2025-06-09 16:04:20');

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

CREATE TABLE `produits` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `uid_codebar` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `nom`, `prix`, `quantite`, `categorie`, `uid_codebar`, `image`, `disponible`) VALUES
(9, 'papier', 12.00, 0, 'Hygiène', '0000', '../uploads/atay_sba3_min.jpeg', 0),
(10, 'eau miniral 2L', 0.88, 4, 'Hygiène', 'ffff', '../uploads/sidi-ali-pack-4x2l.jpg', 1),
(11, 'Abtal', 2.00, 60, 'Hygiène', '1111111', '../uploads/telechargement.jpeg', 1),
(13, 'pc', 123.00, 12, 'Hygiène', '012568', NULL, 1),
(14, 'pomme ', 1.00, 12, 'Hygiène', '13453', NULL, 1),
(15, 'popo', 123.00, 14, 'poisson', '11112', NULL, 1),
(21, 'rez', 20.00, 1, 'Hygiène', '132568', NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `receptions`
--

CREATE TABLE `receptions` (
  `id_reception` int(11) NOT NULL,
  `id_produit` int(10) DEFAULT NULL,
  `quantite_recue` int(11) DEFAULT NULL,
  `date_reception` date DEFAULT NULL,
  `date_peremption` date DEFAULT NULL,
  `qualite_validee` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `receptions`
--

INSERT INTO `receptions` (`id_reception`, `id_produit`, `quantite_recue`, `date_reception`, `date_peremption`, `qualite_validee`) VALUES
(1, 21, 5, '2025-06-09', '2025-06-09', 1),
(2, 21, 5, '2025-06-09', '2025-06-09', 1);

-- --------------------------------------------------------

--
-- Structure de la table `stock_journalier`
--

CREATE TABLE `stock_journalier` (
  `id` int(11) NOT NULL,
  `date_enregistrement` date NOT NULL,
  `stock_total` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `adresse` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `username`, `email`, `password`, `role`, `mot_de_passe`, `adresse`) VALUES
(1, 'admin', 'nada', 'ghmari@gmail.com', '123456', 'admin', '', ''),
(2, 'gest1', 'gest1', 'gest1@catiyakho.com', '123456', 'gestionaire', '', ''),
(3, 'fatima', 'fatima123', 'fatima@example.com', NULL, 'client', '75216c44a46bfff78f692d1fe695c02a407a2136625dcc17ca6cf3141e0c4c72', 'msalla'),
(5, 'fatima', 'fatima123', 'fatima2@example.com', NULL, 'client', '75216c44a46bfff78f692d1fe695c02a407a2136625dcc17ca6cf3141e0c4c72', 'msalla'),
(7, 'fatima', 'fatima123', 'fatima_ac70ad37@example.com', NULL, 'client', '75216c44a46bfff78f692d1fe695c02a407a2136625dcc17ca6cf3141e0c4c72', 'msalla'),
(8, 'fatima', 'fatima123', 'fatima_c2d4e4ec@example.com', NULL, 'client', '75216c44a46bfff78f692d1fe695c02a407a2136625dcc17ca6cf3141e0c4c72', 'msalla');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `achats`
--
ALTER TABLE `achats`
  ADD PRIMARY KEY (`id_achat`),
  ADD KEY `id_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `achat_produits`
--
ALTER TABLE `achat_produits`
  ADD PRIMARY KEY (`id_achat_produit`),
  ADD KEY `id_achat` (`id_achat`),
  ADD KEY `id_produit` (`id_produit`);

--
-- Index pour la table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `messages_admin`
--
ALTER TABLE `messages_admin`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `receptions`
--
ALTER TABLE `receptions`
  ADD PRIMARY KEY (`id_reception`),
  ADD KEY `FK_receptions_produits` (`id_produit`);

--
-- Index pour la table `stock_journalier`
--
ALTER TABLE `stock_journalier`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date_enregistrement` (`date_enregistrement`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `achats`
--
ALTER TABLE `achats`
  MODIFY `id_achat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `achat_produits`
--
ALTER TABLE `achat_produits`
  MODIFY `id_achat_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `client`
--
ALTER TABLE `client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `messages_admin`
--
ALTER TABLE `messages_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `receptions`
--
ALTER TABLE `receptions`
  MODIFY `id_reception` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `stock_journalier`
--
ALTER TABLE `stock_journalier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `achats`
--
ALTER TABLE `achats`
  ADD CONSTRAINT `achats_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `achat_produits`
--
ALTER TABLE `achat_produits`
  ADD CONSTRAINT `achat_produits_ibfk_1` FOREIGN KEY (`id_achat`) REFERENCES `achats` (`id_achat`),
  ADD CONSTRAINT `achat_produits_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id`);

--
-- Contraintes pour la table `receptions`
--
ALTER TABLE `receptions`
  ADD CONSTRAINT `FK_receptions_produits` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

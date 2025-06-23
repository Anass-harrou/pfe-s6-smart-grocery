-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 23 juin 2025 à 09:29
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
-- Base de données : `gestion_stock`
--

DELIMITER $$
--
-- Fonctions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `process_qr_payment` (`p_user_id` INT, `p_amount` DECIMAL(10,2), `p_transaction_id` VARCHAR(100)) RETURNS INT(11)  BEGIN
            DECLARE v_achat_id INT;
            DECLARE v_product_id INT;
            DECLARE v_current_balance DECIMAL(10,2);
            DECLARE v_new_balance DECIMAL(10,2);
            
            -- Get a valid product ID for QR payments
            SELECT id INTO v_product_id FROM produits WHERE nom = 'QR Payment' LIMIT 1;
            
            -- If no QR Payment product, use any valid product
            IF v_product_id IS NULL THEN
                SELECT id INTO v_product_id FROM produits LIMIT 1;
            END IF;
            
            -- Get user's current balance
            SELECT solde INTO v_current_balance FROM client WHERE id = p_user_id;
            
            -- Calculate new balance
            SET v_new_balance = v_current_balance - p_amount;
            
            -- Create purchase record
            INSERT INTO achats (id_utilisateur, montant_total)
            VALUES (p_user_id, p_amount);
            
            SET v_achat_id = LAST_INSERT_ID();
            
            -- Create purchase item with valid product ID
            INSERT INTO achat_produits (id_achat, id_produit, quantite, prix_unitaire)
            VALUES (v_achat_id, v_product_id, 1, p_amount);
            
            -- Update user balance
            UPDATE client SET solde = v_new_balance WHERE id = p_user_id;
            
            -- Create transaction record
            INSERT INTO transactions (client_id, title, subtitle, amount, type)
            VALUES (p_user_id, CONCAT('Payment via QR: ', p_transaction_id), 'Mobile app payment via QR code.', p_amount, 'debit');
            
            -- Create payment flag
            INSERT INTO payment_flags (user_id, amount, new_balance, timestamp, status)
            VALUES (p_user_id, p_amount, v_new_balance, NOW(), 'pending');
            
            RETURN v_achat_id;
        END$$

DELIMITER ;

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
(38, 11, '2025-06-15 05:15:11', 4.00),
(39, 11, '2025-06-15 05:19:05', 4.00),
(40, 11, '2025-06-15 14:08:45', 4.00),
(41, 11, '2025-06-15 15:22:36', 204.00),
(42, 11, '2025-06-17 21:56:08', 6.00),
(43, 11, '2025-06-17 22:03:30', 6.00),
(44, 11, '2025-06-18 01:52:00', 8.00),
(45, 11, '2025-06-18 02:25:45', 14.00),
(48, 11, '2025-06-20 23:04:08', 10.00),
(49, 11, '2025-06-20 23:09:11', 8.00),
(50, 11, '2025-06-20 23:15:37', 10.00),
(51, 11, '2025-06-21 10:43:34', 6.00),
(52, 11, '2025-06-21 13:07:54', 12.00),
(53, 11, '2025-06-21 13:09:33', 10.00),
(54, 11, '2025-06-22 20:44:26', 22.00),
(55, 11, '2025-06-23 03:04:34', 14.00),
(56, 11, '2025-06-23 03:05:49', 12.00);

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
(67, 38, 11, 2, 2.00),
(68, 39, 11, 2, 2.00),
(69, 40, 11, 2, 2.00),
(70, 41, 11, 2, 2.00),
(71, 41, 7, 1, 200.00),
(72, 42, 11, 1, 6.00),
(73, 43, 11, 2, 3.00),
(74, 44, 11, 2, 4.00),
(75, 45, 11, 2, 7.00),
(80, 50, 11, 5, 2.00),
(81, 51, 11, 3, 2.00),
(82, 52, 11, 6, 2.00),
(83, 53, 11, 5, 2.00),
(84, 54, 11, 11, 2.00),
(85, 55, 11, 7, 2.00),
(86, 56, 11, 6, 2.00);

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

CREATE TABLE `client` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `solde` decimal(10,2) DEFAULT NULL,
  `num_commande` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `rfid_uid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`id`, `name`, `email`, `address`, `solde`, `num_commande`, `password`, `phone`, `bio`, `last_login`, `rfid_uid`) VALUES
(11, 'Fatima', 'Fatima@tms.com', '123 Smart Street, Anytown, Morocco', 2396.00, 'CMD98765', '$2y$10$JuI9H7TdFg9/zTLzVMsBw.XaQtjX0urn2y5EYxkk8S1xfF5HaqS2m', '+212 612345678', 'Passionate mobile banking user, always looking for new features.', '2025-06-23 05:43:25', '63B0B4A7'),
(12, 'nada', 'nada@ghmari.ma', 'fnideq', 2000.00, 'CMD61241', '$2y$10$lyeCVFUQGW4FwsgVFtiURuxqnKjGE23AL2LBOte831kKPqepHYP56', '0644496823', '', '2025-06-21 17:17:10', 'D7D8EBD5');

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(11) NOT NULL,
  `reference` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `mode_paiement` varchar(50) DEFAULT NULL,
  `adresse_livraison` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commandes`
--

INSERT INTO `commandes` (`id`, `reference`, `user_id`, `montant`, `status`, `date_creation`, `date_modification`, `mode_paiement`, `adresse_livraison`, `notes`) VALUES
(1, 'CMD-2023001', 1, 125.50, 'completed', '2025-06-22 14:30:00', NULL, 'RFID', NULL, 'Livraison rapide'),
(2, 'CMD-2023002', 2, 89.99, 'processing', '2025-06-22 16:45:00', NULL, 'QR Code', NULL, NULL),
(3, 'CMD-2023003', 1, 210.75, 'pending', '2025-06-23 00:10:00', NULL, 'RFID', NULL, NULL),
(4, 'CMD-2023004', 3, 45.25, 'completed', '2025-06-21 09:15:00', NULL, 'QR Code', NULL, 'Client fidèle'),
(5, 'CMD-2023005', 4, 175.00, 'cancelled', '2025-06-20 11:20:00', NULL, 'RFID', NULL, 'Annulée à la demande du client');

-- --------------------------------------------------------

--
-- Structure de la table `commande_details`
--

CREATE TABLE `commande_details` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `prix_unitaire` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commande_details`
--

INSERT INTO `commande_details` (`id`, `commande_id`, `produit_id`, `quantite`, `prix_unitaire`) VALUES
(1, 1, 1, 2, 25.99),
(2, 1, 3, 1, 73.52),
(3, 2, 2, 1, 89.99),
(4, 3, 4, 3, 35.25),
(5, 3, 5, 1, 105.00),
(6, 4, 1, 1, 25.25),
(7, 4, 6, 1, 20.00),
(8, 5, 7, 5, 35.00);

-- --------------------------------------------------------

--
-- Structure de la table `messages_admin`
--

CREATE TABLE `messages_admin` (
  `id` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `date_publication` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sender` varchar(50) DEFAULT NULL,
  `importance` enum('low','medium','high') DEFAULT 'medium'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages_admin`
--

INSERT INTO `messages_admin` (`id`, `contenu`, `date_publication`, `is_read`, `sender`, `importance`) VALUES
(1, 'Arrivage de nouveaux produits prévu pour demain à 10h00. Prévoir espace de stockage.', '2025-06-23 01:10:36', 0, 'Système', 'high'),
(2, 'Rappel: Inventaire mensuel à effectuer avant la fin de semaine.', '2025-06-23 01:10:36', 0, 'Système', 'medium'),
(3, 'Mise à jour du système prévue ce soir à 22h00. Sauvegardez vos données.', '2025-06-22 01:10:36', 1, 'Système', 'medium');

-- --------------------------------------------------------

--
-- Structure de la table `payment_flags`
--

CREATE TABLE `payment_flags` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `new_balance` decimal(10,2) NOT NULL,
  `timestamp` datetime NOT NULL,
  `status` enum('pending','processed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `payment_flags`
--

INSERT INTO `payment_flags` (`id`, `user_id`, `amount`, `new_balance`, `timestamp`, `status`) VALUES
(1, 11, 6.00, 2266.50, '2025-06-17 23:03:30', 'processed'),
(2, 11, 8.00, 2258.50, '2025-06-18 02:52:00', 'processed'),
(3, 11, 14.00, 2244.50, '2025-06-18 03:25:45', 'processed'),
(4, 11, 10.00, 2490.00, '2025-06-21 00:04:08', 'processed'),
(5, 11, 8.00, 2482.00, '2025-06-21 00:09:11', 'processed'),
(6, 11, 10.00, 2472.00, '2025-06-21 00:15:37', 'processed'),
(7, 11, 6.00, 2466.00, '2025-06-21 11:43:34', 'processed'),
(8, 11, 12.00, 2454.00, '2025-06-21 14:07:54', 'processed'),
(9, 11, 10.00, 2444.00, '2025-06-21 14:09:33', 'processed'),
(10, 11, 22.00, 2422.00, '2025-06-22 21:44:26', 'processed'),
(11, 11, 14.00, 2408.00, '2025-06-23 04:04:34', 'processed'),
(12, 11, 12.00, 2396.00, '2025-06-23 04:05:49', 'processed');

-- --------------------------------------------------------

--
-- Structure de la table `pending_transactions`
--

CREATE TABLE `pending_transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `cart_data` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
  `disponible` tinyint(1) DEFAULT 1,
  `map_section` varchar(10) DEFAULT 'A1',
  `map_position_x` int(11) DEFAULT 0,
  `map_position_y` int(11) DEFAULT 0,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `nom`, `prix`, `quantite`, `categorie`, `uid_codebar`, `image`, `disponible`, `map_section`, `map_position_x`, `map_position_y`, `description`) VALUES
(7, 'kitab', 200.00, 399, 'Hygiène', '776754', '../uploads/BOOK.jpeg', 1, 'B3', 116, 45, ''),
(9, 'papier', 12.00, 0, 'Hygiène', '0000', '../uploads/atay_sba3_min.jpeg', 1, 'A2', 106, 35, ''),
(10, 'eau miniral 2L', 6.00, 4, 'Hygiène', 'ffff', '../uploads/sidi-ali-pack-4x2l.jpg', 1, 'A2', 77, 21, ''),
(11, 'Abtal', 2.00, 98, 'Hygiène', '1111111', '../uploads/telechargement.jpeg', 1, 'B3', 35, 91, '');

-- --------------------------------------------------------

--
-- Structure de la table `qr_logins`
--

CREATE TABLE `qr_logins` (
  `token` varchar(64) NOT NULL,
  `client_id` int(11) NOT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `authenticated` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `qr_logins`
--

INSERT INTO `qr_logins` (`token`, `client_id`, `auth_token`, `authenticated`, `expires_at`) VALUES
('002c331f674b45cf4b48650279d53eab', 11, NULL, 1, '2025-06-23 04:12:50'),
('02b3ee8e2d105778d7b4d33cd6a382a1', 12, NULL, 1, '2025-06-21 13:16:36'),
('03040d4417afbe05e799d4b851574cf2', 11, NULL, 1, '2025-06-20 23:12:54'),
('0cf3089a7916ad45c99fa6a57863fdb2', 11, NULL, 1, '2025-06-20 23:14:28'),
('14c73ba0ed6471360b04caf3d9bdafe6', 11, NULL, 1, '2025-06-21 14:16:10'),
('27c357bfb5cec6234d47cf2853de79b8', 11, NULL, 1, '2025-06-17 22:39:52'),
('2ca2cc8f94757e10037df1a34b477d68', 11, NULL, 1, '2025-06-17 22:40:41'),
('500e2616e6deccb15826fd8147fa7b5b', 11, NULL, 1, '2025-06-21 13:17:06'),
('59f105efba08b1cb43ed0a7180e563c9', 11, NULL, 1, '2025-06-21 11:44:25'),
('5c0f43c6961f7c89aa4e7bc84d027866', 12, NULL, 1, '2025-06-20 23:12:33'),
('5f2adee349c36173d6f6efb46d5eceb9', 11, NULL, 1, '2025-06-22 21:33:25'),
('879614e739b4e79a43236d315805a226', 11, NULL, 1, '2025-06-21 11:50:56'),
('8b7dc82ea188b74514d846d9c6b340b1', 11, NULL, 1, '2025-06-23 04:12:38'),
('8d6f53a294ab1a64f9356af2763e1c3e', 11, NULL, 1, '2025-06-17 22:39:22'),
('8e28dc7471024c971df9dcdb6a8a0235', 12, NULL, 1, '2025-06-20 23:09:22'),
('adb60daca432e8a546e0bfd256c3896d', 11, NULL, 1, '2025-06-18 16:27:53'),
('b5f86c4e64bd346fa572232a4aed2014', 11, NULL, 1, '2025-06-21 02:34:12'),
('bcba7561bece68d4d76fae20c6f87779', 12, NULL, 1, '2025-06-21 11:42:58'),
('bfacb12cc7f3e7c8d2003e5c0d5dbc99', 11, NULL, 1, '2025-06-23 04:13:00'),
('cb8ad5058dec7b4bf0e2667a7ca42528', 11, NULL, 1, '2025-06-17 22:43:21'),
('cb97e7f5f7a278d2d0e38315d0842d09', 11, NULL, 1, '2025-06-22 21:34:13'),
('e2b0d46c45fefa765ee2cc2dc0f4995a', 11, NULL, 1, '2025-06-18 02:55:12'),
('ec0e3d90fe05c28c1c5b5fb89b4546fe', 11, NULL, 1, '2025-06-23 04:12:27'),
('ee4f48f98407b88f4598037d32125d4e', 11, NULL, 1, '2025-06-17 22:53:56');

-- --------------------------------------------------------

--
-- Structure de la table `receptions`
--

CREATE TABLE `receptions` (
  `id` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite_recue` int(11) NOT NULL,
  `date_reception` date NOT NULL,
  `date_peremption` date DEFAULT NULL,
  `qualite_validee` tinyint(1) DEFAULT 1,
  `fournisseur` varchar(255) DEFAULT NULL,
  `numero_lot` varchar(100) DEFAULT NULL,
  `commentaires` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rfid_scan_logs`
--

CREATE TABLE `rfid_scan_logs` (
  `id` int(11) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `location` varchar(100) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `scan_requests`
--

CREATE TABLE `scan_requests` (
  `id` int(11) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `action` enum('add','remove','set') NOT NULL,
  `timestamp` datetime NOT NULL,
  `status` enum('pending','processed','error') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `transactions`
--

INSERT INTO `transactions` (`id`, `client_id`, `title`, `subtitle`, `amount`, `type`, `transaction_date`) VALUES
(1, 11, 'a BIBA', 'chdid', 100.00, 'credit', '2025-06-12 18:53:22'),
(2, 11, 'Paiement Web', 'Achat de 1 articles du panier via interface web.', 4.00, 'debit', '2025-06-15 15:08:45'),
(3, 11, 'Paiement Web', 'Achat de 2 articles du panier via interface web.', 204.00, 'debit', '2025-06-15 16:22:36'),
(6, 11, 'Paiement Web', 'Achat via QR code de paiement.', 6.00, 'debit', '2025-06-17 22:49:46'),
(7, 11, 'Payment via QR: qr_pay_6851e4763c507', 'Mobile app payment via QR code.', 6.00, 'debit', '2025-06-17 22:56:08'),
(8, 11, 'Payment via QR: qr_pay_6851e62e80711', 'Mobile app payment via QR code.', 6.00, 'debit', '2025-06-17 23:03:30'),
(9, 11, 'Payment via QR: qr_pay_68521bb621053', 'Mobile app payment via QR code.', 8.00, 'debit', '2025-06-18 02:52:00'),
(10, 11, 'Payment via QR: qr_pay_685223a4b1616', 'Mobile app payment via QR code.', 14.00, 'debit', '2025-06-18 03:25:45'),
(13, 11, 'Payment via QR: qr_pay_6855e8e65b1e3', 'Mobile app payment via QR code.', 10.00, 'debit', '2025-06-21 00:04:08'),
(14, 11, 'Payment via QR: qr_pay_6855ea146e117', 'Mobile app payment via QR code.', 8.00, 'debit', '2025-06-21 00:09:11'),
(15, 11, 'Payment via QR: qr_pay_6855eb96af40f', 'Mobile app payment via QR code.', 10.00, 'debit', '2025-06-21 00:15:37'),
(16, 11, 'Payment via QR: qr_pay_68568cd44ee3e', 'Mobile app payment via QR code.', 6.00, 'debit', '2025-06-21 11:43:34'),
(17, 11, 'Payment via QR: qr_pay_6856aea8d8a24', 'Mobile app payment via QR code.', 12.00, 'debit', '2025-06-21 14:07:54'),
(18, 11, 'Payment via QR: qr_pay_6856af0b009ee', 'Mobile app payment via QR code.', 10.00, 'debit', '2025-06-21 14:09:33'),
(19, 11, 'Payment via QR: qr_pay_68586b24d2511', 'Mobile app payment via QR code.', 22.00, 'debit', '2025-06-22 21:44:26'),
(20, 11, 'Payment via QR: qr_pay_6858c43ec3ed2', 'Mobile app payment via QR code.', 14.00, 'debit', '2025-06-23 04:04:34'),
(21, 11, 'Payment via QR: qr_pay_6858c48ad1b0c', 'Mobile app payment via QR code.', 12.00, 'debit', '2025-06-23 04:05:49');

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
  ADD KEY `achats_ibfk_1` (`id_utilisateur`),
  ADD KEY `idx_date_achat` (`date_achat`);

--
-- Index pour la table `achat_produits`
--
ALTER TABLE `achat_produits`
  ADD PRIMARY KEY (`id_achat_produit`),
  ADD KEY `id_achat` (`id_achat`),
  ADD KEY `idx_id_produit` (`id_produit`);

--
-- Index pour la table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `date_creation` (`date_creation`);

--
-- Index pour la table `commande_details`
--
ALTER TABLE `commande_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `commande_id` (`commande_id`),
  ADD KEY `produit_id` (`produit_id`);

--
-- Index pour la table `messages_admin`
--
ALTER TABLE `messages_admin`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `payment_flags`
--
ALTER TABLE `payment_flags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `pending_transactions`
--
ALTER TABLE `pending_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `qr_logins`
--
ALTER TABLE `qr_logins`
  ADD PRIMARY KEY (`token`);

--
-- Index pour la table `receptions`
--
ALTER TABLE `receptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_produit` (`id_produit`);

--
-- Index pour la table `rfid_scan_logs`
--
ALTER TABLE `rfid_scan_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Index pour la table `scan_requests`
--
ALTER TABLE `scan_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transactions_ibfk_1` (`client_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

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
  MODIFY `id_achat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT pour la table `achat_produits`
--
ALTER TABLE `achat_produits`
  MODIFY `id_achat_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT pour la table `client`
--
ALTER TABLE `client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `commande_details`
--
ALTER TABLE `commande_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `messages_admin`
--
ALTER TABLE `messages_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `payment_flags`
--
ALTER TABLE `payment_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `pending_transactions`
--
ALTER TABLE `pending_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `receptions`
--
ALTER TABLE `receptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rfid_scan_logs`
--
ALTER TABLE `rfid_scan_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `scan_requests`
--
ALTER TABLE `scan_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
  ADD CONSTRAINT `achats_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `client` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `achat_produits`
--
ALTER TABLE `achat_produits`
  ADD CONSTRAINT `achat_produits_ibfk_1` FOREIGN KEY (`id_achat`) REFERENCES `achats` (`id_achat`),
  ADD CONSTRAINT `achat_produits_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `receptions`
--
ALTER TABLE `receptions`
  ADD CONSTRAINT `receptions_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rfid_scan_logs`
--
ALTER TABLE `rfid_scan_logs`
  ADD CONSTRAINT `rfid_scan_logs_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`);

--
-- Contraintes pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

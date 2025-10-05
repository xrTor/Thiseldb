-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: אוקטובר 05, 2025 בזמן 08:15 PM
-- גרסת שרת: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `empty`
--

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `actors`
--

CREATE TABLE `actors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  `nationality` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `nickname` varchar(50) DEFAULT 'אורח',
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- הוצאת מידע עבור טבלה `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `nickname`, `message`, `created_at`) VALUES
(8, 'מייקל', 'ברוכים הבאים!', '2025-10-04 19:23:00');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `collections`
--

CREATE TABLE `collections` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_url` text DEFAULT NULL,
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `poster_image_url` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `cover_poster_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `collection_items`
--

CREATE TABLE `collection_items` (
  `id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `added_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `contact_reports`
--

CREATE TABLE `contact_reports` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) DEFAULT NULL,
  `collection_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `contact_requests`
--

CREATE TABLE `contact_requests` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `genres`
--

CREATE TABLE `genres` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `languages`
--

CREATE TABLE `languages` (
  `id` int(11) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `native_name` varchar(100) DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `posters`
--

CREATE TABLE `posters` (
  `id` int(11) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_he` varchar(255) DEFAULT NULL,
  `original_title` varchar(255) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `is_tv` tinyint(1) NOT NULL DEFAULT 0,
  `imdb_rating` decimal(3,1) DEFAULT NULL,
  `imdb_votes` int(11) DEFAULT NULL,
  `poster_url` text DEFAULT NULL,
  `trailer_url` text DEFAULT NULL,
  `tmdb_url` text DEFAULT NULL,
  `tvdb_url` text DEFAULT NULL,
  `imdb_link` varchar(255) DEFAULT NULL,
  `network` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `plot` text DEFAULT NULL,
  `plot_he` text DEFAULT NULL,
  `lang_code` varchar(10) DEFAULT NULL,
  `tvdb_id` varchar(100) DEFAULT NULL,
  `tmdb_id` int(11) DEFAULT NULL,
  `tmdb_type` varchar(10) DEFAULT NULL,
  `youtube_trailer` varchar(255) DEFAULT NULL,
  `genre` varchar(255) DEFAULT NULL,
  `actors` text DEFAULT NULL,
  `metacritic_score` varchar(50) DEFAULT NULL,
  `rt_score` varchar(50) DEFAULT NULL,
  `mc_score` int(11) DEFAULT NULL,
  `rt_url` text DEFAULT NULL,
  `mc_url` text DEFAULT NULL,
  `poster` text DEFAULT NULL,
  `metacritic_link` varchar(255) DEFAULT NULL,
  `rt_link` varchar(255) DEFAULT NULL,
  `imdb_id` varchar(20) DEFAULT NULL,
  `pending` tinyint(4) DEFAULT 0,
  `collection_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type_id` int(11) DEFAULT NULL,
  `directors` varchar(255) DEFAULT NULL,
  `writers` varchar(255) DEFAULT NULL,
  `producers` varchar(255) DEFAULT NULL,
  `cinematographers` varchar(255) DEFAULT NULL,
  `composers` varchar(255) DEFAULT NULL,
  `runtime` int(11) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `countries` varchar(255) DEFAULT NULL,
  `genres` text DEFAULT NULL,
  `networks` text DEFAULT NULL,
  `tmdb_collection_id` int(11) DEFAULT NULL,
  `seasons_count` int(11) DEFAULT 0,
  `episodes_count` int(11) DEFAULT 0,
  `network_logo` varchar(255) DEFAULT NULL,
  `has_subtitles` tinyint(1) DEFAULT 0,
  `is_dubbed` tinyint(1) DEFAULT 0,
  `overview_he` text DEFAULT NULL,
  `overview_en` text DEFAULT NULL,
  `cast` longtext DEFAULT NULL,
  `title_kind` varchar(50) DEFAULT NULL,
  `he_title` varchar(255) DEFAULT NULL,
  `runtime_minutes` int(11) DEFAULT NULL,
  `connections_count` int(11) NOT NULL DEFAULT 0,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `runtime_pretty` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_akas`
--

CREATE TABLE `poster_akas` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `aka_title` varchar(255) NOT NULL,
  `aka_lang` varchar(10) DEFAULT NULL,
  `source` enum('imdb','tmdb','tvdb','manual') DEFAULT 'imdb',
  `aka` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `imdb_id` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_bookmarks`
--

CREATE TABLE `poster_bookmarks` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `visitor_token` varchar(255) DEFAULT NULL,
  `vote_type` enum('like','dislike') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_categories`
--

CREATE TABLE `poster_categories` (
  `poster_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_collections`
--

CREATE TABLE `poster_collections` (
  `poster_id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_connections`
--

CREATE TABLE `poster_connections` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `relation_label` varchar(50) NOT NULL,
  `conn_title` varchar(255) DEFAULT NULL,
  `related_imdb_id` varchar(15) DEFAULT NULL,
  `conn_imdb_id` varchar(15) NOT NULL,
  `related_title` varchar(255) DEFAULT NULL,
  `related_poster_id` int(11) DEFAULT NULL,
  `relation_type` enum('Follows','Followed by','Remake of','Remade as','Spin-off','Spin-off from','Version of','Alternate versions') NOT NULL,
  `related_title_en` varchar(255) DEFAULT NULL,
  `related_year` varchar(20) DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'imdb',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kind` varchar(40) NOT NULL,
  `target_tt` varchar(16) NOT NULL,
  `target_title` varchar(255) DEFAULT NULL,
  `imdb_id` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_genres_user`
--

CREATE TABLE `poster_genres_user` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `genre` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_languages`
--

CREATE TABLE `poster_languages` (
  `poster_id` int(11) NOT NULL,
  `lang_code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_likes`
--

CREATE TABLE `poster_likes` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_reports`
--

CREATE TABLE `poster_reports` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `report_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `handled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_similar`
--

CREATE TABLE `poster_similar` (
  `poster_id` int(11) NOT NULL,
  `similar_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_types`
--

CREATE TABLE `poster_types` (
  `id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL DEFAULT '',
  `label_he` varchar(255) NOT NULL DEFAULT '',
  `label_en` varchar(255) NOT NULL DEFAULT '',
  `icon` varchar(255) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `image` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `poster_types`
--

INSERT INTO `poster_types` (`id`, `code`, `label_he`, `label_en`, `icon`, `description`, `sort_order`, `image`) VALUES
(3, 'movie', 'סרט', 'Movie', '🎬', '0', 1, 'movie.png'),
(4, 'series', 'סדרה', 'Series', '📺', '0', 3, 'series.png'),
(5, 'miniseries', 'מיני-סדרה', 'Miniseries', '📺', '0', 4, 'miniseries.png'),
(6, 'short-film', 'סרט קצר', 'Short Film', '🎞️', '0', 2, 'short.png'),
(11, ' stand-up', 'Stand-up Comedy', ' Stand-up Comedy', '🎞️', '0', 5, 'stand-up.png'),
(12, ' performance', ' Live Performance', ' Live Performance', '🎞️', '0', 6, 'performance.png'),
(13, 'special', 'ספיישל', 'Special Episodes', '🎬', '0', 7, 'special.png'),
(14, 'none', ' לא ידוע', 'None', '❓', '0', 8, '');

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `poster_votes`
--

CREATE TABLE `poster_votes` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) DEFAULT NULL,
  `visitor_token` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `vote_type` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `unique_visitors`
--

CREATE TABLE `unique_visitors` (
  `id` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `unique_visitors`
--

INSERT INTO `unique_visitors` (`id`, `count`) VALUES
(1, 12);

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `user_tags`
--

CREATE TABLE `user_tags` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `genre` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `visitors`
--

CREATE TABLE `visitors` (
  `id` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `visitors`
--

INSERT INTO `visitors` (`id`, `count`) VALUES
(1, 5843);

--
-- Indexes for dumped tables
--

--
-- אינדקסים לטבלה `actors`
--
ALTER TABLE `actors`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `collection_items`
--
ALTER TABLE `collection_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `collection_id` (`collection_id`),
  ADD KEY `poster_id` (`poster_id`);

--
-- אינדקסים לטבלה `contact_reports`
--
ALTER TABLE `contact_reports`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `contact_requests`
--
ALTER TABLE `contact_requests`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `posters`
--
ALTER TABLE `posters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_imdb` (`imdb_id`),
  ADD UNIQUE KEY `uniq_imdb_id` (`imdb_id`),
  ADD UNIQUE KEY `uq_imdb_id` (`imdb_id`),
  ADD KEY `idx_imdb_id` (`imdb_id`),
  ADD KEY `idx_title_en` (`title_en`(191)),
  ADD KEY `idx_title_he` (`title_he`(191)),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_created` (`created_at`);

--
-- אינדקסים לטבלה `poster_akas`
--
ALTER TABLE `poster_akas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_poster_aka` (`poster_id`,`aka_title`(191)),
  ADD UNIQUE KEY `uniq_aka` (`poster_id`,`source`,`aka`),
  ADD KEY `idx_poster_id` (`poster_id`),
  ADD KEY `idx_aka_title` (`aka_title`(191));

--
-- אינדקסים לטבלה `poster_bookmarks`
--
ALTER TABLE `poster_bookmarks`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `poster_categories`
--
ALTER TABLE `poster_categories`
  ADD PRIMARY KEY (`poster_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- אינדקסים לטבלה `poster_collections`
--
ALTER TABLE `poster_collections`
  ADD PRIMARY KEY (`poster_id`,`collection_id`),
  ADD KEY `collection_id` (`collection_id`);

--
-- אינדקסים לטבלה `poster_connections`
--
ALTER TABLE `poster_connections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_conn` (`poster_id`,`conn_imdb_id`,`relation_type`),
  ADD UNIQUE KEY `uniq_conn_rel` (`imdb_id`,`relation_label`,`related_imdb_id`),
  ADD KEY `idx_related_imdb` (`conn_imdb_id`),
  ADD KEY `idx_related_poster` (`related_poster_id`),
  ADD KEY `idx_pc_related` (`related_imdb_id`);

--
-- אינדקסים לטבלה `poster_genres_user`
--
ALTER TABLE `poster_genres_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poster_id` (`poster_id`);

--
-- אינדקסים לטבלה `poster_languages`
--
ALTER TABLE `poster_languages`
  ADD PRIMARY KEY (`poster_id`,`lang_code`);

--
-- אינדקסים לטבלה `poster_likes`
--
ALTER TABLE `poster_likes`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `poster_reports`
--
ALTER TABLE `poster_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poster_id` (`poster_id`);

--
-- אינדקסים לטבלה `poster_similar`
--
ALTER TABLE `poster_similar`
  ADD PRIMARY KEY (`poster_id`,`similar_id`),
  ADD KEY `similar_id` (`similar_id`);

--
-- אינדקסים לטבלה `poster_types`
--
ALTER TABLE `poster_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- אינדקסים לטבלה `poster_votes`
--
ALTER TABLE `poster_votes`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poster_id` (`poster_id`);

--
-- אינדקסים לטבלה `unique_visitors`
--
ALTER TABLE `unique_visitors`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `user_tags`
--
ALTER TABLE `user_tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poster_id` (`poster_id`);

--
-- אינדקסים לטבלה `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actors`
--
ALTER TABLE `actors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collection_items`
--
ALTER TABLE `collection_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_reports`
--
ALTER TABLE `contact_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_requests`
--
ALTER TABLE `contact_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `genres`
--
ALTER TABLE `genres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posters`
--
ALTER TABLE `posters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poster_akas`
--
ALTER TABLE `poster_akas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=925373;

--
-- AUTO_INCREMENT for table `poster_bookmarks`
--
ALTER TABLE `poster_bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poster_connections`
--
ALTER TABLE `poster_connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45472;

--
-- AUTO_INCREMENT for table `poster_genres_user`
--
ALTER TABLE `poster_genres_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `poster_likes`
--
ALTER TABLE `poster_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poster_reports`
--
ALTER TABLE `poster_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `poster_types`
--
ALTER TABLE `poster_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `poster_votes`
--
ALTER TABLE `poster_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unique_visitors`
--
ALTER TABLE `unique_visitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_tags`
--
ALTER TABLE `user_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18175;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- הגבלות לטבלאות שהוצאו
--

--
-- הגבלות לטבלה `collection_items`
--
ALTER TABLE `collection_items`
  ADD CONSTRAINT `collection_items_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collection_items_ibfk_2` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_akas`
--
ALTER TABLE `poster_akas`
  ADD CONSTRAINT `fk_poster_akas_posters` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_categories`
--
ALTER TABLE `poster_categories`
  ADD CONSTRAINT `poster_categories_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poster_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_collections`
--
ALTER TABLE `poster_collections`
  ADD CONSTRAINT `poster_collections_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poster_collections_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_connections`
--
ALTER TABLE `poster_connections`
  ADD CONSTRAINT `poster_connections_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poster_connections_ibfk_2` FOREIGN KEY (`related_poster_id`) REFERENCES `posters` (`id`) ON DELETE SET NULL;

--
-- הגבלות לטבלה `poster_genres_user`
--
ALTER TABLE `poster_genres_user`
  ADD CONSTRAINT `poster_genres_user_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_languages`
--
ALTER TABLE `poster_languages`
  ADD CONSTRAINT `poster_languages_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_reports`
--
ALTER TABLE `poster_reports`
  ADD CONSTRAINT `poster_reports_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `poster_similar`
--
ALTER TABLE `poster_similar`
  ADD CONSTRAINT `poster_similar_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poster_similar_ibfk_2` FOREIGN KEY (`similar_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `user_tags`
--
ALTER TABLE `user_tags`
  ADD CONSTRAINT `user_tags_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `posters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

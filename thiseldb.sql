-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: יולי 28, 2025 בזמן 03:26 AM
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
-- Database: `thiseldb`
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
-- מבנה טבלה עבור טבלה `collections`
--

CREATE TABLE `collections` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `image_url` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `collections`
--

INSERT INTO `collections` (`id`, `name`, `description`, `created_at`, `image_url`) VALUES
(7, 'אוסף לדוגמא', '', '2025-07-27 21:12:46', '');

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
  `year` varchar(20) DEFAULT NULL,
  `imdb_rating` decimal(3,1) DEFAULT NULL,
  `imdb_link` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `plot` text DEFAULT NULL,
  `plot_he` text DEFAULT NULL,
  `lang_code` varchar(10) DEFAULT NULL,
  `is_dubbed` tinyint(1) DEFAULT 0,
  `has_subtitles` tinyint(1) DEFAULT 0,
  `tvdb_id` varchar(100) DEFAULT NULL,
  `youtube_trailer` varchar(255) DEFAULT NULL,
  `genre` varchar(255) DEFAULT NULL,
  `actors` text DEFAULT NULL,
  `metacritic_score` varchar(50) DEFAULT NULL,
  `rt_score` varchar(50) DEFAULT NULL,
  `metacritic_link` varchar(255) DEFAULT NULL,
  `rt_link` varchar(255) DEFAULT NULL,
  `imdb_id` varchar(15) DEFAULT NULL,
  `pending` tinyint(4) DEFAULT 0,
  `collection_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `type_id` int(11) DEFAULT NULL,
  `directors` varchar(255) DEFAULT NULL,
  `writers` varchar(255) DEFAULT NULL,
  `producers` varchar(255) DEFAULT NULL,
  `cinematographers` varchar(255) DEFAULT NULL,
  `composers` varchar(255) DEFAULT NULL,
  `runtime` int(11) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `countries` varchar(255) DEFAULT NULL,
  `tmdb_collection_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `posters`
--

INSERT INTO `posters` (`id`, `title_en`, `title_he`, `year`, `imdb_rating`, `imdb_link`, `image_url`, `plot`, `plot_he`, `lang_code`, `is_dubbed`, `has_subtitles`, `tvdb_id`, `youtube_trailer`, `genre`, `actors`, `metacritic_score`, `rt_score`, `metacritic_link`, `rt_link`, `imdb_id`, `pending`, `collection_name`, `created_at`, `type_id`, `directors`, `writers`, `producers`, `cinematographers`, `composers`, `runtime`, `languages`, `countries`, `tmdb_collection_id`) VALUES
(1, 'Movie', 'דוגמא', '2012', 0.0, '', '', '', NULL, 'hebrew', 0, 0, '', '', '', '', '', '', '', '', '', 0, NULL, '2025-07-28 04:24:28', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

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
  `collection_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `poster_collections`
--

INSERT INTO `poster_collections` (`poster_id`, `collection_id`) VALUES
(1, 7);

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

--
-- הוצאת מידע עבור טבלה `poster_languages`
--

INSERT INTO `poster_languages` (`poster_id`, `lang_code`) VALUES
(1, 'hebrew');

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
  `code` varchar(50) DEFAULT NULL,
  `label_he` varchar(100) DEFAULT NULL,
  `label_en` varchar(100) DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- הוצאת מידע עבור טבלה `poster_types`
--

INSERT INTO `poster_types` (`id`, `code`, `label_he`, `label_en`, `icon`, `description`, `sort_order`) VALUES
(3, 'movie', 'סרט', 'Movie', '🎬', '0', 1),
(4, 'series', 'סדרה', 'Series', '📺', '0', 3),
(5, 'miniseries', 'מיני-סדרה', 'Miniseries', '📺', '0', 4),
(6, 'short', 'סרט קצר', 'Short Film', '🎞️', '0', 2),
(11, ' Stand-up', ' Stand-up', ' Stand-up Comedy', '🎞️', '0', 5),
(12, ' Performance', ' Live Performance', ' Live Performance', '🎞️', '0', 6),
(13, 'Special', 'ספיישל', 'Special Episodes', '🎬', '0', 7),
(14, 'none', ' לא ידוע', 'None', '❓', '0', 8);

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

--
-- הוצאת מידע עבור טבלה `poster_votes`
--

INSERT INTO `poster_votes` (`id`, `poster_id`, `visitor_token`, `ip_address`, `vote_type`, `created_at`) VALUES
(26, 1, 'mikjfqmmv7s6eckind0jvcle7i', NULL, 'like', '2025-07-27 00:13:46');

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
-- מבנה טבלה עבור טבלה `user_tags`
--

CREATE TABLE `user_tags` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `genre` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD PRIMARY KEY (`id`);

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
-- אינדקסים לטבלה `user_tags`
--
ALTER TABLE `user_tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poster_id` (`poster_id`);

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
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `poster_bookmarks`
--
ALTER TABLE `poster_bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `poster_votes`
--
ALTER TABLE `poster_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_tags`
--
ALTER TABLE `user_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

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

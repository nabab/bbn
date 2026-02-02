-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: db.local
-- Generation Time: Jan 13, 2026 at 12:03 AM
-- Server version: 11.7.2-MariaDB-ubu2404
-- PHP Version: 8.5.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `apst_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_configurations`
--

CREATE TABLE `bbn_ai_lab_configurations` (
  `id` binary(16) NOT NULL,
  `model_id` binary(16) NOT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `top_p` decimal(4,2) DEFAULT NULL,
  `top_k` decimal(4,2) DEFAULT NULL,
  `max_tokens` int(11) DEFAULT NULL,
  `stop_sequences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stop_sequences`)),
  `seed` int(11) DEFAULT NULL,
  `repeat_penalty` decimal(4,2) DEFAULT NULL,
  `presence_penalty` decimal(4,2) DEFAULT NULL,
  `frequency_penalty` decimal(4,2) DEFAULT NULL,
  `logit_bias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`logit_bias`)),
  `extra_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_params`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_datasets`
--

CREATE TABLE `bbn_ai_lab_datasets` (
  `id` binary(16) NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_dataset_items`
--

CREATE TABLE `bbn_ai_lab_dataset_items` (
  `id` binary(16) NOT NULL,
  `dataset_id` binary(16) NOT NULL,
  `input_ref` varchar(255) DEFAULT NULL,
  `input_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`input_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_evaluations`
--

CREATE TABLE `bbn_ai_lab_evaluations` (
  `id` binary(16) NOT NULL,
  `run_id` binary(16) NOT NULL,
  `evaluator` varchar(128) NOT NULL,
  `metric_name` varchar(128) NOT NULL,
  `score_numeric` decimal(10,4) DEFAULT NULL,
  `score_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_experiments`
--

CREATE TABLE `bbn_ai_lab_experiments` (
  `id` binary(16) NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_models`
--

CREATE TABLE `bbn_ai_lab_models` (
  `id` binary(16) NOT NULL,
  `provider` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_prompts`
--

CREATE TABLE `bbn_ai_lab_prompts` (
  `id` binary(16) NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_prompt_versions`
--

CREATE TABLE `bbn_ai_lab_prompt_versions` (
  `id` binary(16) NOT NULL,
  `prompt_id` binary(16) NOT NULL,
  `version` int(11) NOT NULL,
  `prompt_text` text DEFAULT NULL,
  `messages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`messages`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbn_ai_lab_runs`
--

CREATE TABLE `bbn_ai_lab_runs` (
  `id` binary(16) NOT NULL,
  `experiment_id` binary(16) DEFAULT NULL,
  `configuration_id` binary(16) NOT NULL,
  `prompt_version_id` binary(16) DEFAULT NULL,
  `dataset_item_id` binary(16) DEFAULT NULL,
  `input_text` longtext DEFAULT NULL,
  `input_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_metadata`)),
  `config_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config_snapshot`)),
  `prompt_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`prompt_snapshot`)),
  `output_text` longtext DEFAULT NULL,
  `output_structured` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_structured`)),
  `finish_reason` varchar(64) DEFAULT NULL,
  `tokens_prompt` int(11) DEFAULT NULL,
  `tokens_completion` int(11) DEFAULT NULL,
  `tokens_total` int(11) DEFAULT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `cost` decimal(12,6) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bbn_ai_lab_configurations`
--
ALTER TABLE `bbn_ai_lab_configurations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_config_model` (`model_id`);

--
-- Indexes for table `bbn_ai_lab_datasets`
--
ALTER TABLE `bbn_ai_lab_datasets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_dataset_name` (`name`);

--
-- Indexes for table `bbn_ai_lab_dataset_items`
--
ALTER TABLE `bbn_ai_lab_dataset_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dataset_items_dataset` (`dataset_id`);

--
-- Indexes for table `bbn_ai_lab_evaluations`
--
ALTER TABLE `bbn_ai_lab_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evaluations_run` (`run_id`),
  ADD KEY `idx_evaluations_metric` (`metric_name`);

--
-- Indexes for table `bbn_ai_lab_experiments`
--
ALTER TABLE `bbn_ai_lab_experiments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_experiment_name` (`name`);

--
-- Indexes for table `bbn_ai_lab_models`
--
ALTER TABLE `bbn_ai_lab_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_provider_name` (`provider`,`name`);

--
-- Indexes for table `bbn_ai_lab_prompts`
--
ALTER TABLE `bbn_ai_lab_prompts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prompt_name` (`name`);

--
-- Indexes for table `bbn_ai_lab_prompt_versions`
--
ALTER TABLE `bbn_ai_lab_prompt_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prompt_version` (`prompt_id`,`version`);

--
-- Indexes for table `bbn_ai_lab_runs`
--
ALTER TABLE `bbn_ai_lab_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_runs_experiment` (`experiment_id`),
  ADD KEY `idx_runs_configuration` (`configuration_id`),
  ADD KEY `idx_runs_prompt_version` (`prompt_version_id`),
  ADD KEY `idx_runs_dataset_item` (`dataset_item_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bbn_ai_lab_configurations`
--
ALTER TABLE `bbn_ai_lab_configurations`
  ADD CONSTRAINT `fk_configurations_model` FOREIGN KEY (`model_id`) REFERENCES `bbn_ai_lab_models` (`id`);

--
-- Constraints for table `bbn_ai_lab_dataset_items`
--
ALTER TABLE `bbn_ai_lab_dataset_items`
  ADD CONSTRAINT `fk_dataset_items_dataset` FOREIGN KEY (`dataset_id`) REFERENCES `bbn_ai_lab_datasets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bbn_ai_lab_evaluations`
--
ALTER TABLE `bbn_ai_lab_evaluations`
  ADD CONSTRAINT `fk_evaluations_run` FOREIGN KEY (`run_id`) REFERENCES `bbn_ai_lab_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bbn_ai_lab_prompt_versions`
--
ALTER TABLE `bbn_ai_lab_prompt_versions`
  ADD CONSTRAINT `fk_prompt_versions_prompt` FOREIGN KEY (`prompt_id`) REFERENCES `bbn_ai_lab_prompts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bbn_ai_lab_runs`
--
ALTER TABLE `bbn_ai_lab_runs`
  ADD CONSTRAINT `fk_runs_configuration` FOREIGN KEY (`configuration_id`) REFERENCES `bbn_ai_lab_configurations` (`id`),
  ADD CONSTRAINT `fk_runs_dataset_item` FOREIGN KEY (`dataset_item_id`) REFERENCES `bbn_ai_lab_dataset_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_runs_experiment` FOREIGN KEY (`experiment_id`) REFERENCES `bbn_ai_lab_experiments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_runs_prompt_version` FOREIGN KEY (`prompt_version_id`) REFERENCES `bbn_ai_lab_prompt_versions` (`id`) ON DELETE SET NULL;

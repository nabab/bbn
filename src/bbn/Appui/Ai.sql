CREATE TABLE `bbn_ai_test_models` (
  `id`            BINARY(16)  NOT NULL,
  `provider`      VARCHAR(64) NOT NULL,
  `name`          VARCHAR(128) NOT NULL,
  `display_name`  VARCHAR(255) NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_provider_name` (`provider`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_prompts` (
  `id`          BINARY(16)  NOT NULL,
  `name`        VARCHAR(128) NOT NULL,
  `description` TEXT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prompt_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_prompt_versions` (
  `id`           BINARY(16)  NOT NULL,
  `prompt_id`    BINARY(16)  NOT NULL,
  `version`      INT NOT NULL,
  `prompt_text`  TEXT NULL,
  `messages`     JSON NULL,  -- JSON array of messages [{role, content}, ...]
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_prompt_version` (`prompt_id`, `version`),
  CONSTRAINT `fk_prompt_versions_prompt`
    FOREIGN KEY (`prompt_id`) REFERENCES `bbn_ai_test_prompts` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_datasets` (
  `id`          BINARY(16)  NOT NULL,
  `name`        VARCHAR(128) NOT NULL,
  `description` TEXT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dataset_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_dataset_items` (
  `id`          BINARY(16) NOT NULL,
  `dataset_id`  BINARY(16) NOT NULL,
  `input_ref`   VARCHAR(255) NULL,
  `input_data`  JSON NOT NULL, -- { "text": "...", "metadata": {...} }
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dataset_items_dataset` (`dataset_id`),
  CONSTRAINT `fk_dataset_items_dataset`
    FOREIGN KEY (`dataset_id`) REFERENCES `bbn_ai_test_datasets` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_experiments` (
  `id`          BINARY(16)  NOT NULL,
  `name`        VARCHAR(128) NOT NULL,
  `description` TEXT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_experiment_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_configurations` (
  `id`            BINARY(16) NOT NULL,
  `model_id`      BINARY(16) NOT NULL,
  `temperature`   DECIMAL(4,2) NULL,
  `top_p`         DECIMAL(4,2) NULL,
  `max_tokens`    INT NULL,
  `stop_sequences` JSON NULL,  -- JSON array of strings
  `seed`          INT NULL,
  `extra_params`  JSON NULL,   -- provider-specific parameters
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_config_model` (`model_id`),
  CONSTRAINT `fk_configurations_model`
    FOREIGN KEY (`model_id`) REFERENCES `bbn_ai_test_models` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_runs` (
  `id`                 BINARY(16) NOT NULL,
  `experiment_id`      BINARY(16) NULL,
  `configuration_id`   BINARY(16) NOT NULL,
  `prompt_version_id`  BINARY(16) NULL,
  `dataset_item_id`    BINARY(16) NULL,

  -- if you’re not always using dataset_items:
  `input_text`         LONGTEXT NULL,
  `input_metadata`     JSON NULL,

  -- snapshots of what was actually used
  `config_snapshot`    JSON NOT NULL,
  `prompt_snapshot`    JSON NULL,

  -- outputs
  `output_text`        LONGTEXT NULL,
  `output_structured`  JSON NULL,
  `finish_reason`      VARCHAR(64) NULL,

  -- metrics
  `tokens_prompt`      INT NULL,
  `tokens_completion`  INT NULL,
  `tokens_total`       INT NULL,
  `latency_ms`         INT NULL,
  `cost`               DECIMAL(12,6) NULL,

  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_runs_experiment`      (`experiment_id`),
  KEY `idx_runs_configuration`   (`configuration_id`),
  KEY `idx_runs_prompt_version`  (`prompt_version_id`),
  KEY `idx_runs_dataset_item`    (`dataset_item_id`),

  CONSTRAINT `fk_runs_experiment`
    FOREIGN KEY (`experiment_id`) REFERENCES `bbn_ai_test_experiments` (`id`)
    ON DELETE SET NULL,

  CONSTRAINT `fk_runs_configuration`
    FOREIGN KEY (`configuration_id`) REFERENCES `bbn_ai_test_configurations` (`id`)
    ON DELETE RESTRICT,

  CONSTRAINT `fk_runs_prompt_version`
    FOREIGN KEY (`prompt_version_id`) REFERENCES `bbn_ai_test_prompt_versions` (`id`)
    ON DELETE SET NULL,

  CONSTRAINT `fk_runs_dataset_item`
    FOREIGN KEY (`dataset_item_id`) REFERENCES `bbn_ai_test_dataset_items` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `bbn_ai_test_evaluations` (
  `id`            BINARY(16) NOT NULL,
  `run_id`        BINARY(16) NOT NULL,
  `evaluator`     VARCHAR(128) NOT NULL,     -- 'human:alice', 'auto:rouge', ...
  `metric_name`   VARCHAR(128) NOT NULL,     -- 'accuracy', 'helpfulness', ...
  `score_numeric` DECIMAL(10,4) NULL,
  `score_text`    TEXT NULL,                 -- comments
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evaluations_run` (`run_id`),
  KEY `idx_evaluations_metric` (`metric_name`),
  CONSTRAINT `fk_evaluations_run`
    FOREIGN KEY (`run_id`) REFERENCES `bbn_ai_test_runs` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

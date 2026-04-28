CREATE TABLE IF NOT EXISTS profile_ai_enrichment_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    profile_id BIGINT UNSIGNED NOT NULL,
    input_type VARCHAR(30) NOT NULL,
    input_value VARCHAR(500) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    prompt_version VARCHAR(50) DEFAULT NULL,
    confidence_global DECIMAL(5,4) DEFAULT NULL,
    active_profile_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX IDX_PAEJ_PROFILE_STATUS_CREATED (profile_id, status, created_at),
    INDEX IDX_PAEJ_STATUS_CREATED (status, created_at),
    UNIQUE INDEX UNIQ_PAEJ_ONE_ACTIVE_PROFILE (active_profile_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS profile_ai_suggestions (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    enrichment_job_id BIGINT UNSIGNED NOT NULL,
    profile_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    suggested_value LONGTEXT NOT NULL,
    confidence_score DECIMAL(5,4) DEFAULT NULL,
    source_type VARCHAR(50) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'suggested',
    final_value LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX IDX_PAS_JOB_ID (enrichment_job_id),
    INDEX IDX_PAS_PROFILE_STATUS (profile_id, status),
    UNIQUE INDEX UNIQ_PAS_JOB_FIELD (enrichment_job_id, field_name),
    CONSTRAINT FK_PAS_ENRICHMENT_JOB FOREIGN KEY (enrichment_job_id) REFERENCES profile_ai_enrichment_jobs (id) ON DELETE CASCADE,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

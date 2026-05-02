ALTER TABLE profile_ai_enrichment_jobs
    ADD COLUMN input_region VARCHAR(120) DEFAULT NULL AFTER input_value;

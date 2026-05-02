/*
Mini mock test script for the profile AI worker.

How to use:
1) Update @profile_id with a real wp_users.id value.
2) Run this script in phpMyAdmin/MySQL client.
3) Run the worker in mock mode for the returned @job_id:
   php ... app:profile-ai:worker --mode=mock --job-id=<job_id> --limit=1
4) Run the "CHECK" queries below to verify output.
*/

/* ---------- SEED ---------- */
SET @profile_id := 1; /* TODO: replace with a real profile/user id */
SET @input_type := 'studio_name'; /* studio_name | website */
SET @input_value := CONCAT('mock_test_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'));
SET @input_region := 'Ile-de-France'; /* optionnel, seulement pour studio_name */

INSERT INTO profile_ai_enrichment_jobs (
    profile_id,
    input_type,
    input_value,
    input_region,
    status,
    attempt_count,
    last_error,
    prompt_version,
    confidence_global,
    active_profile_id,
    created_at,
    updated_at
) VALUES (
    @profile_id,
    @input_type,
    @input_value,
    @input_region,
    'pending',
    0,
    NULL,
    'mock-seed-v1',
    NULL,
    @profile_id,
    NOW(),
    NOW()
);

SET @job_id := LAST_INSERT_ID();

SELECT
    @job_id AS created_job_id,
    @profile_id AS profile_id,
    @input_type AS input_type,
    @input_value AS input_value,
    @input_region AS input_region;

SELECT
    id,
    profile_id,
    input_type,
    input_value,
    input_region,
    status,
    attempt_count,
    last_error,
    prompt_version,
    confidence_global,
    active_profile_id,
    created_at,
    updated_at
FROM profile_ai_enrichment_jobs
WHERE id = @job_id;

/* ---------- CHECK (run after worker) ---------- */
SELECT
    id,
    status,
    attempt_count,
    last_error,
    prompt_version,
    confidence_global,
    active_profile_id,
    created_at,
    updated_at
FROM profile_ai_enrichment_jobs
WHERE id = @job_id;

SELECT
    id,
    field_name,
    LEFT(suggested_value, 140) AS suggested_value_preview,
    confidence_score,
    source_type,
    status,
    created_at,
    updated_at
FROM profile_ai_suggestions
WHERE enrichment_job_id = @job_id
ORDER BY id ASC;

SELECT
    status,
    COUNT(*) AS count_by_status
FROM profile_ai_suggestions
WHERE enrichment_job_id = @job_id
GROUP BY status;

/* ---------- OPTIONAL QUICK DECISION MOCK ---------- */
/* Uncomment if you want to mark all suggestions as accepted for quick API apply tests.
UPDATE profile_ai_suggestions
SET status = 'accepted', updated_at = NOW()
WHERE enrichment_job_id = @job_id;
*/


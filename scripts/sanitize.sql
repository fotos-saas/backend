-- ============================================================
-- PhotoStack SaaS - Adatszanitizalos SQL
-- Futtatando MINDEN produkcios dump importalasa UTAN!
--
-- GDPR + biztonsag: Minden PII, token, fizetesi adat torolve/anonimizalva
-- ============================================================

BEGIN;

-- ============================================================
-- 1. FELHASZNALOK - Jelszavak, tokenek, PII
-- ============================================================
-- Jelszo: 'password' (bcrypt)
UPDATE users SET
    name = 'User ' || id,
    email = 'user' || id || '@dev.test',
    phone = NULL,
    address = NULL,
    password = '$2y$04$YEbSxKHkGb7xEYPFzAVGbuOvMOGNbSFbl6vBET3bVaJCTPiOaQJ4G',
    remember_token = NULL,
    guest_token = NULL,
    last_login_ip = NULL,
    failed_login_attempts = 0,
    locked_until = NULL
WHERE email IS NOT NULL;

-- Admin user megtartasa (bejelentkezeshez)
UPDATE users SET
    email = 'admin@dev.test',
    name = 'Dev Admin'
WHERE id = (SELECT MIN(id) FROM users WHERE role = 'super_admin');

-- ============================================================
-- 2. TOKENEK ES SESSION-OK - TELJES TORLES
-- ============================================================
TRUNCATE personal_access_tokens;
TRUNCATE password_reset_tokens;
TRUNCATE sessions;

-- ============================================================
-- 3. KISKORUAK ADATAI - Diakok, vendegek
-- ============================================================
UPDATE tablo_missing_persons SET
    name = 'Diak ' || id,
    local_id = NULL,
    note = NULL;

UPDATE tablo_guest_sessions SET
    guest_name = 'Vendeg ' || id,
    guest_email = 'vendeg' || id || '@dev.test',
    guest_phone = NULL,
    ip_address = '127.0.0.1',
    device_identifier = NULL,
    session_token = gen_random_uuid()::text,
    restore_token = NULL;

UPDATE tablo_contacts SET
    name = 'Kontakt ' || id,
    email = 'kontakt' || id || '@dev.test',
    phone = NULL,
    note = NULL;

-- ============================================================
-- 4. FIZETESI ADATOK - Stripe, szamlazas
-- ============================================================
UPDATE partners SET
    company_name = 'Teszt Ceg ' || id,
    tax_number = NULL,
    billing_postal_code = '1011',
    billing_city = 'Budapest',
    billing_address = 'Teszt utca ' || id,
    phone = NULL,
    stripe_customer_id = NULL,
    stripe_subscription_id = NULL;

UPDATE tablo_partners SET
    payment_stripe_public_key = NULL,
    payment_stripe_secret_key = NULL,
    payment_stripe_webhook_secret = NULL;

UPDATE stripe_settings SET
    secret_key = NULL,
    public_key = NULL,
    webhook_secret = NULL;

UPDATE invoicing_providers SET
    api_key = NULL,
    agent_key = NULL,
    api_v3_key = NULL,
    settings = NULL;

-- ============================================================
-- 5. MEGRENDELESEK ANONIMIZALASA
-- ============================================================
UPDATE orders SET
    guest_name = NULL,
    guest_email = NULL,
    guest_phone = NULL,
    guest_address = NULL,
    stripe_pi = NULL,
    invoice_no = 'DEV-' || id;

-- ============================================================
-- 6. BIOMETRIKUS ADATOK - Arcfelismeres
-- ============================================================
UPDATE photos SET face_subject = NULL WHERE face_subject IS NOT NULL;
DELETE FROM face_group_photo;
DELETE FROM face_groups;

-- ============================================================
-- 7. AUDIT LOGOK
-- ============================================================
TRUNCATE login_audits;

-- ============================================================
-- 8. TANARI ARCHIVUM
-- ============================================================
UPDATE teacher_archive SET
    canonical_name = 'Tanar ' || id,
    title_prefix = NULL,
    notes = NULL;

UPDATE teacher_aliases SET
    alias_name = 'Tanar Alias ' || id;

COMMIT;

-- Osszefoglalas
DO $$
BEGIN
    RAISE NOTICE '=== Szanitizalas KESZ! ===';
    RAISE NOTICE 'Admin bejelentkezes: admin@dev.test / password';
    RAISE NOTICE 'Tobbi user: userN@dev.test / password';
END $$;

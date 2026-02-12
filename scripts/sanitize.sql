-- ============================================================
-- PhotoStack SaaS - Adatszanitizalos SQL
-- Futtatando MINDEN produkcios dump importalasa UTAN!
--
-- GDPR + biztonsag: Minden PII, token, fizetesi adat torolve/anonimizalva
-- Graceful: hianyzo tablakat/oszlopokat kihagyja
-- ============================================================

-- 1. FELHASZNALOK - Jelszavak, tokenek, PII
-- Jelszo: 'password' (bcrypt hash, rounds=4)
UPDATE users SET
    name = 'User ' || id,
    email = 'user' || id || '@dev.test',
    phone = NULL,
    password = '$2y$04$rsJA3sDBpfN0syv9TwHoaeJEqZQjjwajGBTN83uIVCNSptNybjaUG',
    remember_token = NULL
WHERE email IS NOT NULL;

-- Admin user megtartasa (az elso user legyen admin)
UPDATE users SET
    email = 'admin@dev.test',
    name = 'Dev Admin'
WHERE id = (SELECT MIN(id) FROM users);

-- 2. TOKENEK ES SESSION-OK
TRUNCATE personal_access_tokens;
TRUNCATE password_reset_tokens;
TRUNCATE sessions;

-- 3. KISKORUAK ADATAI (graceful - hianyzo tablat/oszlopot kihagyja)
DO $$
BEGIN
    -- tablo_persons (diakok, tanarok)
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tablo_persons') THEN
        EXECUTE 'UPDATE tablo_persons SET name = ''Szemely '' || id';
    END IF;

    -- tablo_missing_persons (ha letezik)
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tablo_missing_persons') THEN
        EXECUTE 'UPDATE tablo_missing_persons SET name = ''Diak '' || id';
    END IF;

    -- tablo_guest_sessions
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tablo_guest_sessions') THEN
        EXECUTE 'UPDATE tablo_guest_sessions SET
            guest_name = ''Vendeg '' || id,
            guest_email = ''vendeg'' || id || ''@dev.test'',
            ip_address = ''127.0.0.1'',
            device_identifier = NULL,
            session_token = gen_random_uuid(),
            restore_token = NULL';
        -- guest_phone (lehet hogy nincs meg az oszlop)
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tablo_guest_sessions' AND column_name = 'guest_phone') THEN
            EXECUTE 'UPDATE tablo_guest_sessions SET guest_phone = NULL';
        END IF;
    END IF;

    -- tablo_contacts
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tablo_contacts') THEN
        EXECUTE 'UPDATE tablo_contacts SET
            name = ''Kontakt '' || id,
            email = ''kontakt'' || id || ''@dev.test'',
            phone = NULL,
            note = NULL';
    END IF;

    -- users extra mezok (lehet hogy nincsenek)
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'guest_token') THEN
        EXECUTE 'UPDATE users SET guest_token = NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'last_login_ip') THEN
        EXECUTE 'UPDATE users SET last_login_ip = NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'address') THEN
        EXECUTE 'UPDATE users SET address = NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'failed_login_attempts') THEN
        EXECUTE 'UPDATE users SET failed_login_attempts = 0, locked_until = NULL';
    END IF;
END $$;

-- 4. FIZETESI ADATOK
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'partners') THEN
        EXECUTE 'UPDATE partners SET
            company_name = ''Teszt Ceg '' || id,
            tax_number = NULL,
            billing_postal_code = ''1011'',
            billing_city = ''Budapest'',
            billing_address = ''Teszt utca '' || id,
            phone = NULL,
            stripe_customer_id = NULL,
            stripe_subscription_id = NULL';
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'tablo_partners') THEN
        -- Stripe mezo csak ha letezik
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'tablo_partners' AND column_name = 'payment_stripe_public_key') THEN
            EXECUTE 'UPDATE tablo_partners SET
                payment_stripe_public_key = NULL,
                payment_stripe_secret_key = NULL,
                payment_stripe_webhook_secret = NULL';
        END IF;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'stripe_settings') THEN
        EXECUTE 'UPDATE stripe_settings SET secret_key = NULL, public_key = NULL, webhook_secret = NULL';
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'invoicing_providers') THEN
        EXECUTE 'UPDATE invoicing_providers SET api_key = NULL, agent_key = NULL, api_v3_key = NULL, settings = NULL';
    END IF;
END $$;

-- 5. MEGRENDELESEK
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'orders') THEN
        EXECUTE 'UPDATE orders SET
            guest_name = NULL,
            guest_email = NULL,
            guest_phone = NULL,
            guest_address = NULL,
            stripe_pi = NULL';
    END IF;
END $$;

-- 6. BIOMETRIKUS ADATOK
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'photos' AND column_name = 'face_subject') THEN
        EXECUTE 'UPDATE photos SET face_subject = NULL WHERE face_subject IS NOT NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'face_group_photo') THEN
        EXECUTE 'DELETE FROM face_group_photo';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'face_groups') THEN
        EXECUTE 'DELETE FROM face_groups';
    END IF;
END $$;

-- 7. AUDIT LOGOK
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'login_audits') THEN
        EXECUTE 'TRUNCATE login_audits';
    END IF;
END $$;

-- 8. TANARI ARCHIVUM
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'teacher_archive') THEN
        EXECUTE 'UPDATE teacher_archive SET canonical_name = ''Tanar '' || id, title_prefix = NULL, notes = NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'teacher_aliases') THEN
        EXECUTE 'UPDATE teacher_aliases SET alias_name = ''Tanar Alias '' || id';
    END IF;
END $$;

-- Osszefoglalas
DO $$
BEGIN
    RAISE NOTICE '=== Szanitizalas KESZ! ===';
    RAISE NOTICE 'Admin bejelentkezes: admin@dev.test / password';
    RAISE NOTICE 'Tobbi user: userN@dev.test / password';
END $$;

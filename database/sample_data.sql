-- Select your application database before importing this file in phpMyAdmin.
-- Demo password: Demo@1234 (only its bcrypt hash is inserted).
-- Existing administrators and real records are never updated or deleted.
-- A repeat run preserves demo progress; conflicting/partial demo accounts abort.
DELIMITER $$
SET time_zone = '+05:30'$$
DROP PROCEDURE IF EXISTS community_food_demo_seed_v1$$
CREATE PROCEDURE community_food_demo_seed_v1()
seed: BEGIN
    DECLARE account_count INT DEFAULT 0;
    DECLARE valid_count INT DEFAULT 0;
    DECLARE admin_id INT UNSIGNED;
    DECLARE donor_id INT UNSIGNED;
    DECLARE recipient_id INT UNSIGNED;
    DECLARE volunteer_id INT UNSIGNED;
    DECLARE organization_id INT UNSIGNED;
    DECLARE food_id INT UNSIGNED;
    DECLARE request_id INT UNSIGNED;
    DECLARE acquired INT DEFAULT 0;
    DECLARE stamp DATETIME;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        IF acquired=1 THEN DO RELEASE_LOCK('community_food_demo_seed_v1'); END IF;
        RESIGNAL;
    END;
    SELECT GET_LOCK('community_food_demo_seed_v1',10) INTO acquired;
    IF acquired IS NULL OR acquired<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Demo setup is already running. Try again later.'; END IF;
    START TRANSACTION;
    SELECT COUNT(*) INTO account_count FROM users WHERE email IN ('donor.demo@food.test','organization.demo@food.test','volunteer.demo@food.test');
    IF account_count>0 THEN
        SELECT COUNT(*) INTO valid_count FROM users WHERE
            (email='donor.demo@food.test' AND role='donor' AND full_name='[Demo] Food Donor') OR
            (email='organization.demo@food.test' AND role='organization' AND full_name='[Demo] Organization Contact') OR
            (email='volunteer.demo@food.test' AND role='volunteer' AND full_name='[Demo] Collection Volunteer');
        IF account_count<>3 OR valid_count<>3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Demo email collision or partial setup. No accounts were changed.'; END IF;
        COMMIT;
        DO RELEASE_LOCK('community_food_demo_seed_v1');
        SELECT 'Demo data already exists. Accounts, passwords and workflow progress were preserved.' AS result;
        LEAVE seed;
    END IF;
    SELECT id INTO admin_id FROM users WHERE role='admin' AND status='active' ORDER BY (email='admin@gmail.com') DESC,id LIMIT 1;
    IF admin_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Create an active administrator before loading demo data.'; END IF;
    SET stamp=NOW();
    INSERT INTO users (full_name,email,password_hash,phone,address,role,status,created_at) VALUES
        ('[Demo] Food Donor','donor.demo@food.test','$2y$10$6ZgfmNIYq3iZKg7wPXaF1.TIG2JDDUAY0aLRkLtaTL72UFBDq5uHe','0770000001','Demo University Canteen, Matara','donor','active',stamp-INTERVAL 2 DAY);
    SET donor_id=LAST_INSERT_ID();
    INSERT INTO users (full_name,email,password_hash,phone,address,role,status,created_at) VALUES
        ('[Demo] Organization Contact','organization.demo@food.test','$2y$10$6ZgfmNIYq3iZKg7wPXaF1.TIG2JDDUAY0aLRkLtaTL72UFBDq5uHe','0770000002','Demo Community Kitchen, Matara','organization','active',stamp-INTERVAL 2 DAY);
    SET recipient_id=LAST_INSERT_ID();
    INSERT INTO organizations (user_id,organization_name,description,created_at) VALUES
        (recipient_id,'[Demo] Campus Community Kitchen','Fictional university demonstration organization.',stamp-INTERVAL 2 DAY);
    SET organization_id=LAST_INSERT_ID();
    INSERT INTO users (full_name,email,password_hash,phone,address,role,status,created_at) VALUES
        ('[Demo] Collection Volunteer','volunteer.demo@food.test','$2y$10$6ZgfmNIYq3iZKg7wPXaF1.TIG2JDDUAY0aLRkLtaTL72UFBDq5uHe','0770000003','Demo Volunteer Centre, Matara','volunteer','active',stamp-INTERVAL 2 DAY);
    SET volunteer_id=LAST_INSERT_ID();
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at) VALUES
        (donor_id,'[Demo] Fresh vegetable meal packs','cooked_meals','Available example for the full live donation walkthrough.',20,'packs','Demo University Canteen reception',stamp-INTERVAL 1 HOUR,stamp+INTERVAL 2 DAY,'available',stamp-INTERVAL 1 HOUR);
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at) VALUES
        (donor_id,'[Demo] Bakery bread rolls','bakery','Pending organization request for donor review.',30,'items','Demo University Bakery counter',stamp-INTERVAL 2 HOUR,stamp+INTERVAL 2 DAY,'requested',stamp-INTERVAL 2 HOUR);
    SET food_id=LAST_INSERT_ID();
    INSERT INTO donation_requests (donation_id,organization_id,message,delivery_address,status,created_at) VALUES
        (food_id,organization_id,'Please review this demonstration request.','Demo Community Kitchen reception','pending',stamp-INTERVAL 90 MINUTE);
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at) VALUES
        (donor_id,'[Demo] Fresh garden vegetables','produce','Approved request ready for administrator assignment.',8,'kg','Demo University garden office',stamp-INTERVAL 2 HOUR,stamp+INTERVAL 2 DAY,'requested',stamp-INTERVAL 2 HOUR);
    SET food_id=LAST_INSERT_ID();
    INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,created_at,decided_at) VALUES
        (food_id,organization_id,'Demo Community Kitchen reception','approved',stamp-INTERVAL 90 MINUTE,stamp-INTERVAL 80 MINUTE);
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at) VALUES
        (donor_id,'[Demo] Dry rice bags','dry_goods','Assigned task ready for volunteer acceptance.',5,'kg','Demo University Canteen stores',stamp-INTERVAL 2 HOUR,stamp+INTERVAL 2 DAY,'assigned',stamp-INTERVAL 2 HOUR);
    SET food_id=LAST_INSERT_ID();
    INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,created_at,decided_at) VALUES
        (food_id,organization_id,'Demo Community Kitchen reception','approved',stamp-INTERVAL 90 MINUTE,stamp-INTERVAL 80 MINUTE);
    SET request_id=LAST_INSERT_ID();
    INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at) VALUES
        (request_id,volunteer_id,admin_id,'assigned',stamp-INTERVAL 70 MINUTE);
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at) VALUES
        (donor_id,'[Demo] Delivered fruit baskets','produce','Delivery recorded; organization can confirm receipt.',12,'packs','Demo University Canteen reception',stamp-INTERVAL 3 HOUR,stamp+INTERVAL 1 DAY,'delivered',stamp-INTERVAL 3 HOUR);
    SET food_id=LAST_INSERT_ID();
    INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,created_at,decided_at) VALUES
        (food_id,organization_id,'Demo Community Kitchen reception','approved',stamp-INTERVAL 150 MINUTE,stamp-INTERVAL 140 MINUTE);
    SET request_id=LAST_INSERT_ID();
    INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES
        (request_id,volunteer_id,admin_id,'delivered',stamp-INTERVAL 130 MINUTE,stamp-INTERVAL 120 MINUTE,stamp-INTERVAL 90 MINUTE,stamp-INTERVAL 60 MINUTE);
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at,completed_at) VALUES
        (donor_id,'[Demo] Completed community lunch','cooked_meals','Completed example for history and receipt reports.',40,'portions','Demo University Canteen reception',stamp-INTERVAL 4 HOUR,stamp+INTERVAL 1 DAY,'completed',stamp-INTERVAL 4 HOUR,stamp-INTERVAL 10 MINUTE);
    SET food_id=LAST_INSERT_ID();
    INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,created_at,decided_at) VALUES
        (food_id,organization_id,'Demo Community Kitchen reception','approved',stamp-INTERVAL 200 MINUTE,stamp-INTERVAL 180 MINUTE);
    SET request_id=LAST_INSERT_ID();
    INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES
        (request_id,volunteer_id,admin_id,'delivered',stamp-INTERVAL 170 MINUTE,stamp-INTERVAL 150 MINUTE,stamp-INTERVAL 100 MINUTE,stamp-INTERVAL 30 MINUTE);
    INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at) VALUES
        (donor_id,'[Demo] Expired dairy example','dairy','Past pickup window: visible as Expired and unavailable for requests.',3,'litres','Demo University Canteen reception',stamp-INTERVAL 4 HOUR,stamp-INTERVAL 1 HOUR,'available',stamp-INTERVAL 4 HOUR);
    COMMIT;
    DO RELEASE_LOCK('community_food_demo_seed_v1');
    SELECT 'Created 3 demo accounts, 1 organization, 7 donations, 5 requests and 3 tasks. Existing data was preserved.' AS result;
END$$
CALL community_food_demo_seed_v1()$$
DROP PROCEDURE community_food_demo_seed_v1$$
DELIMITER ;

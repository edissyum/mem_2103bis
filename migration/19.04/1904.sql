-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 18.10 to 19.04                               --
--                                                                          --
--                                                                          --
-- *************************************************************************--
UPDATE parameters SET param_value_string = '19.04.17' WHERE id = 'database_version';

DELETE FROM parameters WHERE id = 'QrCodePrefix';
INSERT INTO parameters (id, description, param_value_int) VALUES ('QrCodePrefix', 'Si activé (1), ajoute "Maarch_" dans le contenu des QrCode générés. (Utilisable avec MaarchCapture >= 1.4)', 0);

DROP VIEW IF EXISTS res_view_letterbox;
DROP VIEW IF EXISTS view_contacts;
DROP VIEW IF EXISTS res_view_attachments;

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS external_signatory_book_id;
ALTER TABLE res_letterbox ADD COLUMN external_signatory_book_id integer;

ALTER TABLE users DROP COLUMN IF EXISTS external_id;
ALTER TABLE users ADD COLUMN external_id jsonb DEFAULT '{}';

/* Redirected Baskets */
DO $$ BEGIN
  IF (SELECT count(TABLE_NAME)  FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'user_abs') = 1 THEN
    DROP TABLE IF EXISTS redirected_baskets;
    CREATE TABLE redirected_baskets
    (
    id serial NOT NULL,
    actual_user_id INTEGER NOT NULL,
    owner_user_id INTEGER NOT NULL,
    basket_id character varying(255) NOT NULL,
    group_id INTEGER NOT NULL,
    CONSTRAINT redirected_baskets_pkey PRIMARY KEY (id),
    CONSTRAINT redirected_baskets_unique_key UNIQUE (owner_user_id, basket_id, group_id)
    )
    WITH (OIDS=FALSE);

	  INSERT INTO redirected_baskets (owner_user_id, actual_user_id, basket_id, group_id)
      SELECT users.id, us.id, user_abs.basket_id, usergroups.id FROM usergroups, usergroup_content, user_abs, groupbasket, users, users us
      WHERE usergroup_content.group_id = usergroups.group_id 
        and usergroup_content.user_id = user_abs.basket_owner
        and users.user_id = user_abs.basket_owner
        and us.user_id = user_abs.new_user
        and groupbasket.group_id = usergroup_content.group_id
        and groupbasket.basket_id = user_abs.basket_id
      ON CONFLICT DO NOTHING;

    DROP TABLE IF EXISTS user_abs;
  END IF;
END$$;

UPDATE history SET table_name = 'redirected_baskets' WHERE table_name = 'user_abs';

/* CONFIGURATIONS */
DROP TABLE IF EXISTS configurations;
CREATE TABLE configurations
(
id serial NOT NULL,
service character varying(64) NOT NULL,
value json DEFAULT '{}' NOT NULL,
CONSTRAINT configuration_pkey PRIMARY KEY (id),
CONSTRAINT configuration_unique_key UNIQUE (service)
)
WITH (OIDS=FALSE);
INSERT INTO configurations (service, value) VALUES ('admin_email_server', '{"type" : "smtp", "host" : "smtp.gmail.com", "port" : 465, "user" : "", "password" : "", "auth" : true, "secure" : "ssl", "from" : "notifications@maarch.org", "charset" : "utf-8"}');

/* EMAILS */
DROP TABLE IF EXISTS emails;
CREATE TABLE emails
(
id serial NOT NULL,
user_id INTEGER NOT NULL,
sender json DEFAULT '{}' NOT NULL,
recipients json DEFAULT '[]' NOT NULL,
cc json DEFAULT '[]' NOT NULL,
cci json DEFAULT '[]' NOT NULL,
object character varying(256),
body text,
document json,
is_html boolean NOT NULL DEFAULT TRUE,
status character varying(16) NOT NULL,
message_exchange_id text,
creation_date timestamp without time zone NOT NULL,
send_date timestamp without time zone,
CONSTRAINT emails_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

/* SHIPPING TEMPLATES */
DROP TABLE IF EXISTS shipping_templates;
CREATE TABLE shipping_templates
(
id serial NOT NULL,
label character varying(64) NOT NULL,
description character varying(255) NOT NULL,
options json DEFAULT '{}',
fee json DEFAULT '{}',
entities jsonb DEFAULT '{}',
account json DEFAULT '{}',
CONSTRAINT shipping_templates_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);
DROP TABLE IF EXISTS shippings;
CREATE TABLE shippings
(
id serial NOT NULL,
user_id INTEGER NOT NULL,
attachment_id INTEGER NOT NULL,
is_version boolean NOT NULL,
options json DEFAULT '{}',
fee FLOAT NOT NULL,
recipient_entity_id INTEGER NOT NULL,
account_id character varying(64) NOT NULL,
creation_date timestamp without time zone NOT NULL,
CONSTRAINT shippings_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

/* SERVICES */
DO $$ BEGIN
  IF (SELECT count(group_id) FROM usergroups_services WHERE service_id IN ('edit_recipient_in_process', 'edit_recipient_outside_process')) = 0 THEN
    INSERT INTO usergroups_services (group_id, service_id) 
    SELECT usergroups.group_id, 'edit_recipient_in_process' FROM usergroups
    LEFT JOIN usergroups_services ON usergroups.group_id = usergroups_services.group_id AND usergroups_services.service_id = 'add_copy_in_process'
    WHERE service_id is null;

    INSERT INTO usergroups_services (group_id, service_id)
    SELECT usergroups.group_id, 'edit_recipient_outside_process' FROM usergroups
    LEFT JOIN usergroups_services ON usergroups.group_id = usergroups_services.group_id AND usergroups_services.service_id = 'add_copy_in_indexing_validation'
    WHERE service_id is null;

    DELETE FROM usergroups_services WHERE service_id in ('add_copy_in_process', 'add_copy_in_indexing_validation');
  END IF;
END$$;

DROP TABLE IF EXISTS exports_templates;
CREATE TABLE exports_templates
(
id serial NOT NULL,
user_id INTEGER NOT NULL,
format character varying(3) NOT NULL,
delimiter character varying(3),
data json DEFAULT '[]' NOT NULL,
CONSTRAINT exports_templates_pkey PRIMARY KEY (id),
CONSTRAINT exports_templates_unique_key UNIQUE (user_id, format)
)
WITH (OIDS=FALSE);

ALTER TABLE baskets DROP COLUMN IF EXISTS id;
ALTER TABLE baskets ADD COLUMN id serial;
ALTER TABLE baskets ADD UNIQUE (id);

ALTER TABLE groupbasket DROP COLUMN IF EXISTS id;
ALTER TABLE groupbasket ADD COLUMN id serial;
ALTER TABLE groupbasket ADD UNIQUE (id);
ALTER TABLE groupbasket DROP COLUMN IF EXISTS list_display;
ALTER TABLE groupbasket ADD COLUMN list_display json DEFAULT '[]';

DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'mlb_coll_ext') AND attname = 'recommendation_limit_date') = 1 THEN
    ALTER TABLE res_letterbox ADD COLUMN opinion_limit_date TIMESTAMP without TIME ZONE DEFAULT NULL;
    UPDATE res_letterbox SET opinion_limit_date = mlb_coll_ext.recommendation_limit_date 
    FROM mlb_coll_ext
    WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND recommendation_limit_date IS NOT NULL;
    ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS recommendation_limit_date;
  END IF;
END$$;

/* Replace occurence in basket_clause */
UPDATE baskets SET basket_clause = regexp_replace(basket_clause,'recommendation_limit_date','opinion_limit_date','g');
UPDATE baskets SET basket_res_order = regexp_replace(basket_res_order,'recommendation_limit_date','opinion_limit_date','g');

/* PARAM LIST DISPLAY */
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'groupbasket') AND attname = 'result_page') THEN
    UPDATE groupbasket SET list_display = '[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]' WHERE result_page = 'list_with_attachments' OR result_page = 'list_copies';
    UPDATE groupbasket SET list_display = '[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]' WHERE result_page = 'list_with_avis';
    UPDATE groupbasket SET list_display = '[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getVisaWorkflow","cssClasses":[],"icon":"fa-list-ol"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]' WHERE result_page = 'list_with_signatory';

    ALTER TABLE groupbasket DROP COLUMN IF EXISTS result_page;
  END IF;
END $$;

/* ACTIONS */
ALTER TABLE actions DROP COLUMN IF EXISTS component;
ALTER TABLE actions ADD COLUMN component CHARACTER VARYING (128);
UPDATE actions SET component = 'v1Action' WHERE action_page IN ('put_in_copy', 'process', 'index_mlb', 'validate_mail', 'sendFileWS', 'sendDataWS', 'close_mail_and_index', 'close_mail_with_attachment', 'send_attachments_to_contact', 'send_to_contact_with_mandatory_attachment', 'visa_workflow', 'interrupt_visa', 'rejection_visa_redactor', 'rejection_visa_previous', 'redirect_visa_entity', 'send_to_visa', 'send_signed_docs', 'send_docs_to_recommendation', 'validate_recommendation', 'send_to_avis', 'avis_workflow', 'avis_workflow_simple', 'export_seda', 'check_acknowledgement', 'check_reply', 'purge_letter', 'reset_letter');
UPDATE actions SET component = 'confirmAction' WHERE action_page = 'confirm_status' OR action_page is null OR action_page = '';
UPDATE actions SET component = 'updateDepartureDateAction' WHERE action_page = 'confirm_status_with_update_date';
UPDATE actions SET component = 'viewDoc' WHERE action_page = 'view';
UPDATE actions SET component = 'closeMailAction' WHERE action_page = 'close_mail';
UPDATE actions SET component = 'enabledBasketPersistenceAction' WHERE action_page = 'set_persistent_mode_on';
UPDATE actions SET component = 'disabledBasketPersistenceAction' WHERE action_page = 'set_persistent_mode_off';
UPDATE actions SET component = 'resMarkAsReadAction' WHERE action_page = 'mark_as_read';
UPDATE actions SET component = 'signatureBookAction' WHERE action_page = 'visa_mail';
UPDATE actions SET component = 'redirectAction' WHERE action_page = 'redirect';
UPDATE actions SET component = 'sendExternalSignatoryBookAction' WHERE action_page = 'sendToExternalSignatureBook';

/*SHIPPING*/
ALTER TABLE res_attachments DROP COLUMN IF EXISTS in_send_attach;
ALTER TABLE res_attachments ADD COLUMN in_send_attach boolean NOT NULL DEFAULT false;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS in_send_attach;
ALTER TABLE res_version_attachments ADD COLUMN in_send_attach boolean NOT NULL DEFAULT false;

/* Acknowledgement Receipts */
DROP TABLE IF EXISTS acknowledgement_receipts;
CREATE TABLE acknowledgement_receipts
(
id serial NOT NULL,
res_id INTEGER NOT NULL,
type CHARACTER VARYING(16) NOT NULL,
format CHARACTER VARYING(8) NOT NULL,
user_id INTEGER NOT NULL,
contact_address_id INTEGER NOT NULL,
creation_date timestamp without time zone NOT NULL,
send_date timestamp without time zone,
docserver_id CHARACTER VARYING(128) NOT NULL,
path CHARACTER VARYING(256) NOT NULL,
filename CHARACTER VARYING(256) NOT NULL,
fingerprint CHARACTER VARYING(256) NOT NULL,
CONSTRAINT acknowledgement_receipts_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);
DELETE FROM docserver_types WHERE docserver_type_id = 'ACKNOWLEDGEMENT_RECEIPTS';
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled) VALUES ('ACKNOWLEDGEMENT_RECEIPTS', 'Accusés de réception', 'Y');
DELETE FROM docservers WHERE docserver_id = 'ACKNOWLEDGEMENT_RECEIPTS';
INSERT INTO docservers (docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id)
VALUES ('ACKNOWLEDGEMENT_RECEIPTS', 'ACKNOWLEDGEMENT_RECEIPTS', 'Dépôt des AR', 'N', 50000000000, 0, '/opt/maarch/docservers/acknowledgment_receipts/', '2019-04-19 22:22:22.201904', 'letterbox_coll');

DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_letterbox') AND attname = 'sve_start_date') = 1 THEN
    INSERT INTO acknowledgement_receipts (res_id, type, format, user_id, contact_address_id, creation_date, send_date, docserver_id, path, filename, fingerprint) 
    SELECT res_id, 'simple', 'html', 0, 0, sve_start_date, sve_start_date, 0, 0, 0, 0 FROM res_letterbox WHERE sve_start_date is not null;
    ALTER TABLE res_letterbox DROP COLUMN IF EXISTS sve_start_date;
  END IF;
END$$;

/* Foldertypes */
UPDATE foldertypes SET coll_id = 'letterbox_coll' WHERE coll_id <> 'letterbox_coll';

/* REFACTORING */
ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS flag_notif;
UPDATE res_letterbox SET locker_user_id = NULL;
ALTER TABLE res_letterbox ALTER COLUMN locker_user_id DROP DEFAULT;
ALTER TABLE res_letterbox ALTER COLUMN locker_user_id TYPE INTEGER USING locker_user_id::integer;
ALTER TABLE res_letterbox ALTER COLUMN locker_user_id SET DEFAULT NULL;
ALTER TABLE notes DROP COLUMN IF EXISTS tablename;
ALTER TABLE notes DROP COLUMN IF EXISTS coll_id;
ALTER TABLE notes DROP COLUMN IF EXISTS type;
ALTER TABLE notes ADD COLUMN type CHARACTER VARYING (32) DEFAULT 'resource' NOT NULL;
ALTER TABLE users DROP COLUMN IF EXISTS thumbprint;
ALTER TABLE entities DROP COLUMN IF EXISTS entity_path;

DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'notes') AND attname = 'date_note') = 1 THEN
	  ALTER TABLE notes RENAME COLUMN date_note TO creation_date;
	  ALTER sequence notes_seq RENAME TO notes_id_seq;
  END IF;
END$$;
ALTER TABLE res_mark_as_read DROP COLUMN IF EXISTS coll_id;

UPDATE listinstance SET added_by_entity = 'superadmin' WHERE added_by_user = 'superadmin';
UPDATE listinstance SET added_by_entity = 'superadmin' WHERE listinstance_id IN 
	(SELECT listinstance_id FROM listinstance LEFT JOIN entities ON listinstance.added_by_entity = entities.entity_id WHERE entities.entity_id IS null);

UPDATE listinstance SET added_by_entity = 'superadmin' WHERE listinstance_id IN 
	(SELECT listinstance_id 
	 FROM listinstance 
	 LEFT JOIN users_entities ON listinstance.added_by_user = users_entities.user_id 
	 LEFT JOIN entities ON users_entities.entity_id = entities.entity_id 
	 WHERE primary_entity = 'Y' AND entities.entity_id IS NULL);

UPDATE listinstance SET added_by_entity =
    (SELECT entity_id FROM users_entities WHERE users_entities.user_id = listinstance.added_by_user AND primary_entity = 'Y')
WHERE added_by_entity IS NULL OR added_by_entity = '';

DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'listinstance_history') AND attname = 'updated_by_user') THEN
    ALTER TABLE listinstance_history DROP COLUMN IF EXISTS user_id;
    ALTER TABLE listinstance_history ADD COLUMN user_id integer;
    UPDATE listinstance_history set user_id = (select id FROM users where users.user_id = listinstance_history.updated_by_user);
    UPDATE listinstance_history set user_id = 0 WHERE user_id IS NULL;
    ALTER TABLE listinstance_history ALTER COLUMN user_id set not null;
    ALTER TABLE listinstance_history DROP COLUMN IF EXISTS updated_by_user;
  END IF;
END$$;
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'contact_addresses') AND attname = 'external_contact_id') THEN
    ALTER TABLE contact_addresses DROP COLUMN IF EXISTS external_id;
    ALTER TABLE contact_addresses ADD COLUMN external_id jsonb DEFAULT '{}';
    UPDATE contact_addresses SET external_id = json_build_object('m2m', external_contact_id);
    ALTER TABLE contact_addresses DROP COLUMN IF EXISTS external_contact_id;
  END IF;
END$$;
DO $$ BEGIN
  IF (SELECT count(column_name) from information_schema.columns where table_name = 'res_attachments' and column_name = 'external_id' and data_type != 'jsonb') THEN
    ALTER TABLE res_attachments DROP COLUMN IF EXISTS external_id_tmp;
    ALTER TABLE res_attachments ADD COLUMN external_id_tmp jsonb DEFAULT '{}';
    UPDATE res_attachments SET external_id_tmp = json_build_object('signatureBookId', external_id);
    ALTER TABLE res_attachments DROP COLUMN IF EXISTS external_id;
	  ALTER TABLE res_attachments RENAME COLUMN external_id_tmp TO external_id;
  END IF;
END$$;
DO $$ BEGIN
  IF (SELECT count(column_name) from information_schema.columns where table_name = 'res_version_attachments' and column_name = 'external_id' and data_type != 'jsonb') THEN
    ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS external_id_tmp;
    ALTER TABLE res_version_attachments ADD COLUMN external_id_tmp jsonb DEFAULT '{}';
    UPDATE res_version_attachments SET external_id_tmp = json_build_object('signatureBookId', external_id);
    ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS external_id;
	  ALTER TABLE res_version_attachments RENAME COLUMN external_id_tmp TO external_id;
  END IF;
END$$;
DO $$ BEGIN
  IF (SELECT count(column_name) from information_schema.columns where table_name = 'res_letterbox' and column_name = 'external_id' and data_type != 'jsonb') THEN
	  ALTER TABLE res_letterbox RENAME COLUMN external_id TO external_reference;
    ALTER TABLE res_letterbox ADD COLUMN external_id jsonb DEFAULT '{}';
    UPDATE res_letterbox SET external_id = json_build_object('publikId', external_reference) WHERE external_link is not NULL;
    UPDATE res_letterbox SET external_reference = NULL WHERE external_link is not NULL;
  END IF;
END$$;
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_letterbox') AND attname = 'scan_date') = 0 THEN
    ALTER TABLE res_letterbox ADD COLUMN scan_date timestamp without time zone;
    ALTER TABLE res_letterbox ADD COLUMN scan_user CHARACTER VARYING (50) DEFAULT NULL::character varying;
    ALTER TABLE res_letterbox ADD COLUMN scan_location CHARACTER VARYING (255) DEFAULT NULL::character varying;
    ALTER TABLE res_letterbox ADD COLUMN scan_wkstation CHARACTER VARYING (255) DEFAULT NULL::character varying;
    ALTER TABLE res_letterbox ADD COLUMN scan_batch CHARACTER VARYING (50) DEFAULT NULL::character varying;
    ALTER TABLE res_letterbox ADD COLUMN scan_postmark CHARACTER VARYING (50) DEFAULT NULL::character varying;
  END IF;
END$$;

DELETE FROM groupbasket where group_id in (select group_id from usergroups where enabled = 'N');
DELETE FROM groupbasket_redirect where group_id in (select group_id from usergroups where enabled = 'N');
DELETE FROM usergroup_content where group_id in (select group_id from usergroups where enabled = 'N');
DELETE FROM usergroups_reports where group_id in (select group_id from usergroups where enabled = 'N');
DELETE FROM usergroups_services where group_id in (select group_id from usergroups where enabled = 'N');
DELETE FROM usergroups WHERE enabled = 'N';

/* RE-CREATE VIEW*/
CREATE OR REPLACE VIEW res_view_letterbox AS
 SELECT r.tablename,
    r.is_multi_docservers,
    r.res_id,
    r.type_id,
    r.policy_id,
    r.cycle_id,
    d.description AS type_label,
    d.doctypes_first_level_id,
    dfl.doctypes_first_level_label,
    dfl.css_style AS doctype_first_level_style,
    d.doctypes_second_level_id,
    dsl.doctypes_second_level_label,
    dsl.css_style AS doctype_second_level_style,
    r.format,
    r.typist,
    r.creation_date,
    r.modification_date,
    r.relation,
    r.docserver_id,
    r.folders_system_id,
    f.folder_id,
    f.destination AS folder_destination,
    f.is_frozen AS folder_is_frozen,
    r.path,
    r.filename,
    r.fingerprint,
    r.offset_doc,
    r.filesize,
    r.status,
    r.work_batch,
    r.doc_date,
    r.description,
    r.source,
    r.author,
    r.reference_number,
    r.external_reference,
    r.external_id,
    r.external_link,
    r.departure_date,
    r.opinion_limit_date,
    r.department_number_id,
    r.barcode,
    r.external_signatory_book_id,
    r.custom_t1 AS doc_custom_t1,
    r.custom_t2 AS doc_custom_t2,
    r.custom_t3 AS doc_custom_t3,
    r.custom_t4 AS doc_custom_t4,
    r.custom_t5 AS doc_custom_t5,
    r.custom_t6 AS doc_custom_t6,
    r.custom_t7 AS doc_custom_t7,
    r.custom_t8 AS doc_custom_t8,
    r.custom_t9 AS doc_custom_t9,
    r.custom_t10 AS doc_custom_t10,
    r.custom_t11 AS doc_custom_t11,
    r.custom_t12 AS doc_custom_t12,
    r.custom_t13 AS doc_custom_t13,
    r.custom_t14 AS doc_custom_t14,
    r.custom_t15 AS doc_custom_t15,
    r.custom_d1 AS doc_custom_d1,
    r.custom_d2 AS doc_custom_d2,
    r.custom_d3 AS doc_custom_d3,
    r.custom_d4 AS doc_custom_d4,
    r.custom_d5 AS doc_custom_d5,
    r.custom_d6 AS doc_custom_d6,
    r.custom_d7 AS doc_custom_d7,
    r.custom_d8 AS doc_custom_d8,
    r.custom_d9 AS doc_custom_d9,
    r.custom_d10 AS doc_custom_d10,
    r.custom_n1 AS doc_custom_n1,
    r.custom_n2 AS doc_custom_n2,
    r.custom_n3 AS doc_custom_n3,
    r.custom_n4 AS doc_custom_n4,
    r.custom_n5 AS doc_custom_n5,
    r.custom_f1 AS doc_custom_f1,
    r.custom_f2 AS doc_custom_f2,
    r.custom_f3 AS doc_custom_f3,
    r.custom_f4 AS doc_custom_f4,
    r.custom_f5 AS doc_custom_f5,
    r.scan_date,
    r.scan_user,
    r.scan_location,
    r.scan_wkstation,
    r.scan_batch,
    r.scan_postmark,
    f.foldertype_id,
    ft.foldertype_label,
    f.custom_t1 AS fold_custom_t1,
    f.custom_t2 AS fold_custom_t2,
    f.custom_t3 AS fold_custom_t3,
    f.custom_t4 AS fold_custom_t4,
    f.custom_t5 AS fold_custom_t5,
    f.custom_t6 AS fold_custom_t6,
    f.custom_t7 AS fold_custom_t7,
    f.custom_t8 AS fold_custom_t8,
    f.custom_t9 AS fold_custom_t9,
    f.custom_t10 AS fold_custom_t10,
    f.custom_t11 AS fold_custom_t11,
    f.custom_t12 AS fold_custom_t12,
    f.custom_t13 AS fold_custom_t13,
    f.custom_t14 AS fold_custom_t14,
    f.custom_t15 AS fold_custom_t15,
    f.custom_d1 AS fold_custom_d1,
    f.custom_d2 AS fold_custom_d2,
    f.custom_d3 AS fold_custom_d3,
    f.custom_d4 AS fold_custom_d4,
    f.custom_d5 AS fold_custom_d5,
    f.custom_d6 AS fold_custom_d6,
    f.custom_d7 AS fold_custom_d7,
    f.custom_d8 AS fold_custom_d8,
    f.custom_d9 AS fold_custom_d9,
    f.custom_d10 AS fold_custom_d10,
    f.custom_n1 AS fold_custom_n1,
    f.custom_n2 AS fold_custom_n2,
    f.custom_n3 AS fold_custom_n3,
    f.custom_n4 AS fold_custom_n4,
    f.custom_n5 AS fold_custom_n5,
    f.custom_f1 AS fold_custom_f1,
    f.custom_f2 AS fold_custom_f2,
    f.custom_f3 AS fold_custom_f3,
    f.custom_f4 AS fold_custom_f4,
    f.custom_f5 AS fold_custom_f5,
    f.is_complete AS fold_complete,
    f.status AS fold_status,
    f.subject AS fold_subject,
    f.parent_id AS fold_parent_id,
    f.folder_level,
    f.folder_name,
    f.creation_date AS fold_creation_date,
    r.initiator,
    r.destination,
    r.dest_user,
    r.confidentiality,
    mlb.category_id,
    mlb.exp_contact_id,
    mlb.exp_user_id,
    mlb.dest_user_id,
    mlb.dest_contact_id,
    mlb.address_id,
    mlb.nature_id,
    mlb.alt_identifier,
    mlb.admission_date,
    mlb.process_limit_date,
    mlb.closing_date,
    mlb.alarm1_date,
    mlb.alarm2_date,
    mlb.flag_alarm1,
    mlb.flag_alarm2,
    mlb.is_multicontacts,
    r.subject,
    r.identifier,
    r.title,
    r.priority,
    r.locker_user_id,
    r.locker_time,
    ca.case_id,
    ca.case_label,
    ca.case_description,
    en.entity_label,
    en.entity_type AS entitytype,
    cont.contact_id,
    cont.firstname AS contact_firstname,
    cont.lastname AS contact_lastname,
    cont.society AS contact_society,
    u.lastname AS user_lastname,
    u.firstname AS user_firstname
   FROM doctypes d,
    doctypes_first_level dfl,
    doctypes_second_level dsl,
    res_letterbox r
     LEFT JOIN entities en ON r.destination::text = en.entity_id::text
     LEFT JOIN folders f ON r.folders_system_id = f.folders_system_id
     LEFT JOIN cases_res cr ON r.res_id = cr.res_id
     LEFT JOIN mlb_coll_ext mlb ON mlb.res_id = r.res_id
     LEFT JOIN foldertypes ft ON f.foldertype_id = ft.foldertype_id AND f.status::text <> 'DEL'::text
     LEFT JOIN cases ca ON cr.case_id = ca.case_id
     LEFT JOIN contacts_v2 cont ON mlb.exp_contact_id = cont.contact_id OR mlb.dest_contact_id = cont.contact_id
     LEFT JOIN users u ON mlb.exp_user_id::text = u.user_id::text OR mlb.dest_user_id::text = u.user_id::text
  WHERE r.type_id = d.type_id AND d.doctypes_first_level_id = dfl.doctypes_first_level_id AND d.doctypes_second_level_id = dsl.doctypes_second_level_id;

DROP VIEW IF EXISTS view_contacts;
CREATE OR REPLACE VIEW view_contacts AS
 SELECT c.contact_id, c.contact_type, c.is_corporate_person, c.society, c.society_short, c.firstname AS contact_firstname
, c.lastname AS contact_lastname, c.title AS contact_title, c.function AS contact_function, c.other_data AS contact_other_data
, c.user_id AS contact_user_id, c.entity_id AS contact_entity_id, c.creation_date, c.update_date, c.enabled AS contact_enabled, ca.id AS ca_id
, ca.contact_purpose_id, ca.departement, ca.firstname, ca.lastname, ca.title, ca.function, ca.occupancy
, ca.address_num, ca.address_street, ca.address_complement, ca.address_town, ca.address_postal_code, ca.address_country
, ca.phone, ca.email, ca.website, ca.salutation_header, ca.salutation_footer, ca.other_data, ca.user_id, ca.entity_id, ca.is_private, ca.enabled, ca.external_id
, cp.label as contact_purpose_label, ct.label as contact_type_label
   FROM contacts_v2 c
   RIGHT JOIN contact_addresses ca ON c.contact_id = ca.contact_id
   LEFT JOIN contact_purposes cp ON ca.contact_purpose_id = cp.id
   LEFT JOIN contact_types ct ON c.contact_type = ct.id;

DROP VIEW IF EXISTS res_view_attachments;
CREATE OR REPLACE VIEW res_view_attachments AS
  SELECT '0' as res_id, res_id as res_id_version, title, subject, description, type_id, format, typist,
  creation_date, fulltext_result, author, identifier, source, relation, doc_date, docserver_id, folders_system_id, path,
  filename, offset_doc, fingerprint, filesize, status, destination, validation_date, effective_date, origin, priority, initiator, dest_user, external_id,
  coll_id, dest_contact_id, dest_address_id, updated_by, is_multicontacts, is_multi_docservers, res_id_master, attachment_type, attachment_id_master, in_signature_book, in_send_attach, signatory_user_serial_id
  FROM res_version_attachments
  UNION ALL
  SELECT res_id, '0' as res_id_version, title, subject, description, type_id, format, typist,
  creation_date, fulltext_result, author, identifier, source, relation, doc_date, docserver_id, folders_system_id, path,
  filename, offset_doc, fingerprint, filesize, status, destination, validation_date, effective_date, origin, priority, initiator, dest_user, external_id,
  coll_id, dest_contact_id, dest_address_id, updated_by, is_multicontacts, is_multi_docservers, res_id_master, attachment_type, '0', in_signature_book, in_send_attach, signatory_user_serial_id
  FROM res_attachments;

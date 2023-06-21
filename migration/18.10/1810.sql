-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 18.04 to 18.10                                  --
--                                                                          --
--                                                                          --
-- *************************************************************************--

DELETE FROM parameters where id = 'database_version';
INSERT INTO parameters (id, param_value_string, param_value_int, param_value_date) VALUES ('database_version', '18.10.1', NULL, NULL);

DROP VIEW IF EXISTS res_view_letterbox;
DROP VIEW IF EXISTS res_view_attachments;

UPDATE actions_groupbaskets SET used_in_basketlist = 'Y', used_in_action_page = 'Y' WHERE default_action_list = 'Y';
UPDATE actions_groupbaskets SET used_in_action_page = 'Y' WHERE used_in_basketlist = 'N' AND used_in_action_page = 'N';
DELETE FROM usergroups_services WHERE service_id = 'view_baskets';
ALTER TABLE groupbasket_status DROP COLUMN IF EXISTS "order";
ALTER TABLE groupbasket_status ADD COLUMN "order" integer;
UPDATE groupbasket_status SET "order" = 0 WHERE status_id = 'NEW';
UPDATE groupbasket_status SET "order" = 1 WHERE status_id != 'NEW';
UPDATE baskets SET basket_res_order = 'res_id desc' WHERE basket_res_order IS NULL;
ALTER TABLE baskets ALTER COLUMN basket_res_order SET NOT NULL;
ALTER TABLE baskets ALTER COLUMN basket_res_order SET DEFAULT 'res_id desc';
ALTER TABLE groupbasket_status ALTER COLUMN "order" SET NOT NULL;
DO $$ BEGIN
  IF (SELECT count(TABLE_NAME)  FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'users_baskets') = 1 THEN
    UPDATE users_baskets_preferences set color =
    (
      SELECT color FROM users_baskets
      WHERE users_baskets_preferences.user_serial_id = users_baskets.user_serial_id
      AND users_baskets_preferences.basket_id = users_baskets.basket_id
      AND users_baskets.group_id = (select group_id from usergroups where users_baskets_preferences.group_serial_id = usergroups.id)
    );
    DROP TABLE IF EXISTS users_baskets;
  END IF;
END$$;

/* Custom To Standard*/
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS departure_date;
ALTER TABLE res_letterbox ADD COLUMN departure_date timestamp without time zone;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS department_number_id;
ALTER TABLE res_letterbox ADD COLUMN department_number_id text;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS barcode;
ALTER TABLE res_letterbox ADD COLUMN barcode text;

/* Contact Groups*/
DROP TABLE IF EXISTS contacts_groups;
CREATE TABLE contacts_groups
(
  id serial,
  label character varying(32) NOT NULL,
  description character varying(255) NOT NULL,
  public boolean NOT NULL,
  owner integer NOT NULL,
  entity_owner character varying(32) NOT NULL,
  CONSTRAINT contacts_groups_pkey PRIMARY KEY (id),
  CONSTRAINT contacts_groups_key UNIQUE (label, owner)
)
WITH (OIDS=FALSE);
DROP TABLE IF EXISTS contacts_groups_lists;
CREATE TABLE contacts_groups_lists
(
  id serial,
  contacts_groups_id integer NOT NULL,
  contact_addresses_id integer NOT NULL,
  CONSTRAINT contacts_groups_lists_pkey PRIMARY KEY (id),
  CONSTRAINT contacts_groups_lists_key UNIQUE (contacts_groups_id, contact_addresses_id)
)
WITH (OIDS=FALSE);

/* Docservers */
ALTER TABLE docservers DROP COLUMN IF EXISTS docserver_location_id;
ALTER TABLE docservers DROP COLUMN IF EXISTS ext_docserver_info;
ALTER TABLE docservers DROP COLUMN IF EXISTS chain_before;
ALTER TABLE docservers DROP COLUMN IF EXISTS chain_after;
ALTER TABLE docservers DROP COLUMN IF EXISTS closing_date;
ALTER TABLE docservers DROP COLUMN IF EXISTS enabled;
ALTER TABLE docservers DROP COLUMN IF EXISTS adr_priority_number;
ALTER TABLE docservers DROP COLUMN IF EXISTS priority_number;
ALTER TABLE docservers DROP COLUMN IF EXISTS id;
ALTER TABLE docservers ADD COLUMN id serial;
ALTER TABLE docservers ADD UNIQUE (id);
ALTER TABLE docserver_types DROP COLUMN IF EXISTS is_container;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS container_max_number;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS is_compressed;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS compression_mode;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS is_meta;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS meta_template;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS is_logged;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS log_template;
ALTER TABLE docserver_types DROP COLUMN IF EXISTS is_signed;
DROP TABLE IF EXISTS docserver_locations;
UPDATE docservers set is_readonly = 'Y' WHERE docserver_id = 'FASTHD_AI';

/* Templates */
ALTER TABLE templates_association DROP COLUMN IF EXISTS system_id;
ALTER TABLE templates_association DROP COLUMN IF EXISTS what;
ALTER TABLE templates_association DROP COLUMN IF EXISTS maarch_module;
ALTER TABLE templates_association DROP COLUMN IF EXISTS id;
ALTER TABLE templates_association ADD COLUMN id serial;
ALTER TABLE templates_association ADD UNIQUE (id);
UPDATE templates SET template_content = REPLACE(template_content, '###', ';');
UPDATE templates SET template_content = REPLACE(template_content, '___', '--');

/* Password Management */
DROP TABLE IF EXISTS password_rules;
CREATE TABLE password_rules
(
  id serial,
  label character varying(64) NOT NULL,
  "value" INTEGER NOT NULL,
  enabled boolean DEFAULT FALSE NOT NULL,
  CONSTRAINT password_rules_pkey PRIMARY KEY (id),
  CONSTRAINT password_rules_label_key UNIQUE (label)
)
WITH (OIDS=FALSE);
DROP TABLE IF EXISTS password_history;
CREATE TABLE password_history
(
  id serial,
  user_serial_id INTEGER NOT NULL,
  password character varying(255) NOT NULL,
  CONSTRAINT password_history_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);
INSERT INTO password_rules (label, "value", enabled) VALUES ('minLength', 6, true);
INSERT INTO password_rules (label, "value") VALUES ('complexityUpper', 0);
INSERT INTO password_rules (label, "value") VALUES ('complexityNumber', 0);
INSERT INTO password_rules (label, "value") VALUES ('complexitySpecial', 0);
INSERT INTO password_rules (label, "value") VALUES ('lockAttempts', 3);
INSERT INTO password_rules (label, "value") VALUES ('lockTime', 5);
INSERT INTO password_rules (label, "value") VALUES ('historyLastUse', 2);
INSERT INTO password_rules (label, "value") VALUES ('renewal', 90);
ALTER TABLE users DROP COLUMN IF EXISTS password_modification_date;
ALTER TABLE users ADD COLUMN password_modification_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users DROP COLUMN IF EXISTS failed_authentication;
ALTER TABLE users ADD COLUMN failed_authentication INTEGER DEFAULT 0;
ALTER TABLE users DROP COLUMN IF EXISTS locked_until;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP without time zone;

/* Signature Books*/
ALTER TABLE res_attachments DROP COLUMN IF EXISTS external_id;
ALTER TABLE res_attachments ADD COLUMN external_id character varying(255) DEFAULT NULL::character varying;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS external_id;
ALTER TABLE res_version_attachments ADD COLUMN external_id character varying(255) DEFAULT NULL::character varying;

/* Convert */
DROP TABLE IF EXISTS adr_letterbox;
CREATE TABLE adr_letterbox
(
  id serial NOT NULL,
  res_id bigint NOT NULL,
  type character varying(32) NOT NULL,
  docserver_id character varying(32) NOT NULL,
  path character varying(255) NOT NULL,
  filename character varying(255) NOT NULL,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  CONSTRAINT adr_letterbox_pkey PRIMARY KEY (id),
  CONSTRAINT adr_letterbox_unique_key UNIQUE (res_id, type)
)
WITH (OIDS=FALSE);
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_letterbox') AND attname = 'tnl_path') = 1 THEN
    INSERT INTO adr_letterbox (res_id, type, docserver_id, path, filename) SELECT res_id, 'TNL', 'TNL_MLB', tnl_path, tnl_filename FROM res_letterbox WHERE tnl_path IS NOT NULL AND tnl_path != 'ERR';
    ALTER TABLE res_letterbox DROP COLUMN IF EXISTS tnl_path;
    ALTER TABLE res_letterbox DROP COLUMN IF EXISTS tnl_filename;
  END IF;
END$$;
DELETE FROM parameters WHERE id = 'thumbnailsSize';
INSERT INTO parameters (id, description, param_value_string) VALUES ('thumbnailsSize', 'Taille des imagettes', '750x900');

DROP TABLE IF EXISTS adr_attachments;
CREATE TABLE adr_attachments
(
  id serial NOT NULL,
  res_id bigint NOT NULL,
  type character varying(32) NOT NULL,
  docserver_id character varying(32) NOT NULL,
  path character varying(255) NOT NULL,
  filename character varying(255) NOT NULL,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  CONSTRAINT adr_attachments_pkey PRIMARY KEY (id),
  CONSTRAINT adr_attachments_unique_key UNIQUE (res_id, type)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS adr_attachments_version;
CREATE TABLE adr_attachments_version
(
  id serial NOT NULL,
  res_id bigint NOT NULL,
  type character varying(32) NOT NULL,
  docserver_id character varying(32) NOT NULL,
  path character varying(255) NOT NULL,
  filename character varying(255) NOT NULL,
  fingerprint character varying(255) DEFAULT NULL::character varying,
  CONSTRAINT adr_attachments_version_pkey PRIMARY KEY (id),
  CONSTRAINT adr_attachments_version_unique_key UNIQUE (res_id, type)
)
WITH (OIDS=FALSE);

/* Refactoring */
DROP VIEW IF EXISTS af_view_customer_target_view;
DROP VIEW IF EXISTS af_view_customer_view;
DROP VIEW IF EXISTS af_view_year_target_view;
DROP VIEW IF EXISTS af_view_year_view;
DROP TABLE IF EXISTS allowed_ip;
DROP TABLE IF EXISTS af_security;
DROP TABLE IF EXISTS af_view_customer_target;
DROP TABLE IF EXISTS af_view_year_target;
DROP VIEW IF EXISTS res_view;
DROP TABLE IF EXISTS res_x;
DROP TABLE IF EXISTS res_version_x;
DROP TABLE IF EXISTS adr_x;
DROP TABLE IF EXISTS res_version_letterbox;
ALTER TABLE baskets DROP COLUMN IF EXISTS is_generic;
ALTER TABLE baskets DROP COLUMN IF EXISTS except_notif;
ALTER TABLE baskets DROP COLUMN IF EXISTS is_folder_basket;
ALTER TABLE actions DROP COLUMN IF EXISTS is_folder_action;
ALTER TABLE status DROP COLUMN IF EXISTS is_folder_status;
ALTER TABLE security DROP COLUMN IF EXISTS can_insert;
ALTER TABLE security DROP COLUMN IF EXISTS can_update;
ALTER TABLE security DROP COLUMN IF EXISTS can_delete;
ALTER TABLE security DROP COLUMN IF EXISTS rights_bitmask;
ALTER TABLE security DROP COLUMN IF EXISTS mr_start_date;
ALTER TABLE security DROP COLUMN IF EXISTS mr_stop_date;
ALTER TABLE security DROP COLUMN IF EXISTS where_target;
ALTER TABLE users DROP COLUMN IF EXISTS ra_code;
ALTER TABLE users DROP COLUMN IF EXISTS ra_expiration_date;
ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS answer_type_bitmask;
ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS other_answer_desc;
ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS process_notes;
ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS sve_identifier;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS publisher;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS publisher;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS publisher;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS contributor;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS contributor;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS contributor;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS author_name;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS author_name;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS author_name;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS doc_language;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS doc_language;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS doc_language;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS arbox_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS arbox_id;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS arbox_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS logical_adr;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS logical_adr;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS logical_adr;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS is_paper;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS is_paper;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS is_paper;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS page_count;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS page_count;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS page_count;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_date;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS scan_date;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS scan_date;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_user;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS scan_user;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS scan_user;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_location;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS scan_location;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS scan_location;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_wkstation;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS scan_wkstation;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS scan_wkstation;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_batch;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS scan_batch;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS scan_batch;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS burn_batch;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS burn_batch;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS burn_batch;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_postmark;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS scan_postmark;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS scan_postmark;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS envelop_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS envelop_id;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS envelop_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS approver;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS approver;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS approver;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS is_ingoing;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS is_ingoing;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS is_ingoing;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS arbatch_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS arbatch_id;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS arbatch_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS cycle_date;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS cycle_date;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS cycle_date;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS is_frozen;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS is_frozen;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS is_frozen;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS video_batch;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS video_batch;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS video_batch;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS video_time;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS video_time;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS video_time;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS video_user;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS video_user;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS video_user;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS video_date;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS video_date;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS video_date;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS ocr_result;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS ocr_result;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS ocr_result;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS coverage;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS coverage;
ALTER TABLE res_version_attachments DROP COLUMN IF EXISTS coverage;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS esign_proof_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS esign_proof_content;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS esign_content;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS esign_date;
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'mlb_coll_ext') AND attname = 'sve_start_date') = 1 THEN
    ALTER TABLE res_letterbox ADD COLUMN sve_start_date TIMESTAMP without time zone;
    UPDATE res_letterbox set sve_start_date =
    (
      SELECT sve_start_date FROM mlb_coll_ext
      WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND sve_start_date is not null
    );
    ALTER TABLE mlb_coll_ext DROP COLUMN IF EXISTS sve_start_date;
  END IF;
END$$;


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
    r.external_id,
    r.external_link,
    r.departure_date,
    r.department_number_id,
    r.barcode,
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
    mlb.recommendation_limit_date,
    mlb.closing_date,
    mlb.alarm1_date,
    mlb.alarm2_date,
    mlb.flag_notif,
    mlb.flag_alarm1,
    mlb.flag_alarm2,
    mlb.is_multicontacts,
    r.sve_start_date,
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

CREATE VIEW res_view_attachments AS
  SELECT '0' as res_id, res_id as res_id_version, title, subject, description, type_id, format, typist,
  creation_date, fulltext_result, author, identifier, source, relation, doc_date, docserver_id, folders_system_id, path,
  filename, offset_doc, fingerprint, filesize, status, destination, validation_date, effective_date, origin, priority, initiator, dest_user, external_id,
  coll_id, dest_contact_id, dest_address_id, updated_by, is_multicontacts, is_multi_docservers, res_id_master, attachment_type, attachment_id_master, in_signature_book, signatory_user_serial_id
  FROM res_version_attachments
  UNION ALL
  SELECT res_id, '0' as res_id_version, title, subject, description, type_id, format, typist,
  creation_date, fulltext_result, author, identifier, source, relation, doc_date, docserver_id, folders_system_id, path,
  filename, offset_doc, fingerprint, filesize, status, destination, validation_date, effective_date, origin, priority, initiator, dest_user, external_id,
  coll_id, dest_contact_id, dest_address_id, updated_by, is_multicontacts, is_multi_docservers, res_id_master, attachment_type, '0', in_signature_book, signatory_user_serial_id
  FROM res_attachments;

DELETE FROM status WHERE id = 'A_TRA';
INSERT INTO status (id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES ('A_TRA', 'PJ à traiter', 'Y', 'fa-question', 'apps', 'Y', 'Y');

DELETE FROM status_images WHERE image_name = 'fa-question';
INSERT INTO status_images (image_name) VALUES ('fa-question');

DELETE FROM status WHERE id = 'TRA';
INSERT INTO status (id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES ('TRA', 'PJ traitée', 'Y', 'fa-check', 'apps', 'Y', 'Y');

DELETE FROM status_images WHERE image_name = 'fa-check';
INSERT INTO status_images (image_name) VALUES ('fa-check');

DELETE FROM status WHERE id = 'FRZ';
INSERT INTO status (id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES ('FRZ', 'PJ gelée', 'Y', 'fa-pause', 'apps', 'Y', 'Y');

DELETE FROM status_images WHERE image_name = 'fa-pause';
INSERT INTO status_images (image_name) VALUES ('fa-pause');

DELETE FROM status WHERE id = 'SEND_MASS';
INSERT INTO status (id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES ('SEND_MASS', 'Pour publipostage', 'Y', 'fa-mail-bulk', 'apps', 'Y', 'Y');

DELETE FROM status_images WHERE image_name = 'fa-mail-bulk';
INSERT INTO status_images (image_name) VALUES ('fa-mail-bulk');

DELETE FROM status WHERE id = 'SIGN';
INSERT INTO status (id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES ('SIGN', 'PJ signée', 'Y', 'fa-check', 'apps', 'Y', 'Y');

DELETE FROM parameters WHERE id = 'homepage_message';
INSERT INTO parameters (id, description, param_value_string) VALUES ('homepage_message', 'Texte apparaissant dans la bannière sur la page d''accueil, mettre un espace pour supprimer la bannière.', 'Bienvenue dans votre <b>G</b>estion <b>E</b>lectronique du <b>C</b>ourrier.');

ALTER TABLE parameters ALTER COLUMN param_value_string TYPE TEXT;

DROP TABLE IF EXISTS contacts_filling;
CREATE TABLE contacts_filling
(
  id serial NOT NULL,
  enable boolean NOT NULL,
  rating_columns text NOT NULL,
  first_threshold int NOT NULL,
  second_threshold int NOT NULL,
  CONSTRAINT contacts_filling_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);
INSERT INTO contacts_filling (enable, rating_columns, first_threshold, second_threshold) VALUES (FALSE, '{}', 33, 66);

ALTER TABLE entities DROP COLUMN IF EXISTS folder_import;
ALTER TABLE entities ADD COLUMN folder_import character varying(64);
ALTER TABLE entities ADD UNIQUE (folder_import);
ALTER TABLE user_abs DROP COLUMN IF EXISTS group_id;
ALTER TABLE user_abs ADD COLUMN group_id int;

/* Sender/Recipient */
DROP TABLE IF EXISTS resource_contacts;
CREATE TABLE resource_contacts
(
  id serial NOT NULL,
  res_id int NOT NULL,
  item_id int NOT NULL,
  type character varying(32) NOT NULL,
  mode character varying(32) NOT NULL,
  CONSTRAINT resource_contacts_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);
ALTER TABLE entities DROP COLUMN IF EXISTS id;
ALTER TABLE entities ADD COLUMN id serial;
ALTER TABLE entities ADD UNIQUE (id);

UPDATE notifications set event_id = 'userModification' where event_id = 'usersup';
UPDATE notifications set event_id = 'user%' where event_id = 'users%';

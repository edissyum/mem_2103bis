-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 19.04 to 20.03 (Run this file after migrate.sh) --
--                                                                          --
--                                                                          --
-- *************************************************************************--

DROP VIEW IF EXISTS res_view_letterbox;
DROP VIEW IF EXISTS view_contacts;

DROP TABLE IF EXISTS cases;
DROP TABLE IF EXISTS cases_res;

DELETE FROM contacts_groups_lists WHERE contact_id IS NULL;

DELETE FROM resources_folders WHERE res_id NOT IN (SELECT res_id FROM res_letterbox);

DELETE FROM res_attachments WHERE attachment_type in ('converted_pdf', 'print_folder');

DROP TABLE IF EXISTS contacts_res;
DROP TABLE IF EXISTS contact_addresses;
DROP TABLE IF EXISTS contact_communication;
DROP TABLE IF EXISTS contact_purposes;
DROP TABLE IF EXISTS contact_types;
DROP TABLE IF EXISTS contacts_v2;

UPDATE CONTACTS SET civility = null WHERE civility = '';
UPDATE CONTACTS SET firstname = null WHERE firstname = '';
UPDATE CONTACTS SET lastname = null WHERE lastname = '';
UPDATE CONTACTS SET company = null WHERE company = '';
UPDATE CONTACTS SET department = null WHERE department = '';
UPDATE CONTACTS SET function = null WHERE function = '';
UPDATE CONTACTS SET address_number = null WHERE address_number = '';
UPDATE CONTACTS SET address_street = null WHERE address_street = '';
UPDATE CONTACTS SET address_additional1 = null WHERE address_additional1 = '';
UPDATE CONTACTS SET address_additional2 = null WHERE address_additional2 = '';
UPDATE CONTACTS SET address_postcode = null WHERE address_postcode = '';
UPDATE CONTACTS SET address_town = null WHERE address_town = '';
UPDATE CONTACTS SET address_country = null WHERE address_country = '';
UPDATE CONTACTS SET email = null WHERE email = '';
UPDATE CONTACTS SET phone = null WHERE phone = '';
UPDATE CONTACTS SET notes = null WHERE notes = '';

UPDATE res_attachments SET attachment_type = 'response_project' WHERE attachment_type = 'outgoing_mail';
UPDATE res_attachments SET attachment_type = 'signed_response' WHERE attachment_type = 'outgoing_mail_signed';
UPDATE res_attachments SET attachment_type = 'simple_attachment' WHERE attachment_type = 'document_with_notes';

ALTER TABLE acknowledgement_receipts ALTER COLUMN contact_id set not null;
ALTER TABLE acknowledgement_receipts DROP COLUMN IF EXISTS contact_address_id;
ALTER TABLE contacts_groups_lists ALTER COLUMN contact_id set not null;
ALTER TABLE contacts_groups_lists DROP COLUMN IF EXISTS contact_addresses_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS dest_contact_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS dest_address_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS dest_user;
ALTER TABLE contacts_filling DROP COLUMN IF EXISTS rating_columns;

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS exp_contact_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS exp_user_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS dest_contact_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS dest_user_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS address_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS is_multicontacts;

DROP TABLE IF EXISTS fp_fileplan;
DROP TABLE IF EXISTS fp_fileplan_positions;
DROP TABLE IF EXISTS fp_res_fileplan_positions;

DROP TABLE IF EXISTS folder_tmp;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS folders_system_id;

DROP TABLE IF EXISTS groupbasket_status;
DROP TABLE IF EXISTS indexingmodels;

DROP TABLE IF EXISTS mlb_coll_ext;
DROP TABLE IF EXISTS res_version_attachments;
DROP TABLE IF EXISTS adr_attachments_version;
ALTER TABLE shippings DROP COLUMN IF EXISTS is_version;

ALTER TABLE priorities DROP COLUMN IF EXISTS default_priority;

DROP TABLE IF EXISTS doctypes_indexes;

DROP TABLE IF EXISTS listmodels;

DROP TABLE IF EXISTS res_linked;

DROP TABLE IF EXISTS thesaurus;
DROP TABLE IF EXISTS thesaurus_res;
DROP SEQUENCE IF EXISTS thesaurus_id_seq;

SELECT setval('tags_id_seq', (SELECT max(id)+1 FROM tags), false);
SELECT setval('contacts_id_seq', (SELECT max(id)+1 FROM contacts), false);

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t1;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t2;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t3;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t4;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t5;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t6;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t7;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t8;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t9;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t10;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t11;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t12;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t13;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t14;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_t15;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d1;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d2;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d3;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d4;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d5;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d6;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d7;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d8;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d9;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_d10;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_n1;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_n2;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_n3;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_n4;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_n5;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_f1;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_f2;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_f3;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_f4;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_f5;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_date;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_user;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_location;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_wkstation;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_batch;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS scan_postmark;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS description;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS author;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS reference_number;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS external_reference;


/* RE CREATE VIEWS */
CREATE OR REPLACE VIEW res_view_letterbox AS
SELECT r.res_id,
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
       r.docserver_id,
       r.path,
       r.filename,
       r.fingerprint,
       r.filesize,
       r.status,
       r.work_batch,
       r.doc_date,
       r.external_id,
       r.departure_date,
       r.opinion_limit_date,
       r.barcode,
       r.initiator,
       r.destination,
       r.dest_user,
       r.confidentiality,
       r.category_id,
       r.alt_identifier,
       r.admission_date,
       r.process_limit_date,
       r.closing_date,
       r.alarm1_date,
       r.alarm2_date,
       r.flag_alarm1,
       r.flag_alarm2,
       r.subject,
       r.priority,
       r.locker_user_id,
       r.locker_time,
       r.custom_fields,
       en.entity_label,
       en.entity_type AS entitytype
FROM doctypes d,
     doctypes_first_level dfl,
     doctypes_second_level dsl,
     res_letterbox r
    LEFT JOIN entities en ON r.destination::text = en.entity_id::text
WHERE r.type_id = d.type_id AND d.doctypes_first_level_id = dfl.doctypes_first_level_id AND d.doctypes_second_level_id = dsl.doctypes_second_level_id;

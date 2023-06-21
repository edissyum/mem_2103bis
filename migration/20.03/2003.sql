-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 19.04 to 20.03                                  --
--                                                                          --
--                                                                          --
-- *************************************************************************--
UPDATE parameters SET param_value_string = '20.03.9' WHERE id = 'database_version';

UPDATE parameters SET description = 'Département par défaut sélectionné dans les autocomplétions de la Base Adresse Nationale' WHERE id = 'defaultDepartment';

/* VIEWS */
DROP VIEW IF EXISTS res_view_letterbox;
DROP VIEW IF EXISTS res_view_attachments;
DROP VIEW IF EXISTS view_folders;
DROP VIEW IF EXISTS res_view_business;

/*USERS*/
ALTER TABLE users DROP COLUMN IF EXISTS reset_token;
ALTER TABLE users DROP COLUMN IF EXISTS change_password;
ALTER TABLE users ADD COLUMN reset_token text;
ALTER TABLE users DROP COLUMN IF EXISTS preferences;
ALTER TABLE users ADD COLUMN preferences jsonb NOT NULL DEFAULT '{"documentEdition" : "java"}';

/* FULL TEXT */
DELETE FROM docservers where docserver_type_id = 'FULLTEXT';
INSERT INTO docservers (docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id)
VALUES ('FULLTEXT_DOCUMENT', 'FULLTEXT', 'Full text indexes for documents', 'N', 50000000000, 0, '/opt/maarch/docservers/indexes/documents/', '2019-11-01 12:00:00.123456', 'letterbox_coll');
INSERT INTO docservers (docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id)
VALUES ('FULLTEXT_ATTACHMENT', 'FULLTEXT', 'Full text indexes for attachments', 'N', 50000000000, 0, '/opt/maarch/docservers/indexes/attachments/', '2019-11-01 12:00:00.123456', 'attachments_coll');
UPDATE docserver_types SET fingerprint_mode = NULL WHERE docserver_type_id = 'FULLTEXT';
UPDATE res_letterbox SET fulltext_result = 'SUCCESS' WHERE fulltext_result = '1' OR fulltext_result = '2';
UPDATE res_letterbox SET fulltext_result = 'ERROR' WHERE fulltext_result = '-1' OR fulltext_result = '-2';
UPDATE res_attachments SET fulltext_result = 'SUCCESS' WHERE fulltext_result = '1' OR fulltext_result = '2';
UPDATE res_attachments SET fulltext_result = 'ERROR' WHERE fulltext_result = '-1' OR fulltext_result = '-2';

/* GROUPS INDEXING */
ALTER TABLE usergroups ALTER COLUMN group_desc DROP DEFAULT;
ALTER TABLE usergroups DROP COLUMN IF EXISTS can_index;
ALTER TABLE usergroups ADD COLUMN can_index boolean NOT NULL DEFAULT FALSE;
ALTER TABLE usergroups DROP COLUMN IF EXISTS indexation_parameters;
ALTER TABLE usergroups ADD COLUMN indexation_parameters jsonb NOT NULL DEFAULT '{"actions" : [], "entities" : [], "keywords" : []}';


/* BASKETS LIST EVENT */
ALTER TABLE groupbasket DROP COLUMN IF EXISTS list_event;
ALTER TABLE groupbasket ADD COLUMN list_event character varying(255) DEFAULT 'documentDetails' NOT NULL;
UPDATE groupbasket SET list_event = 'processDocument'
FROM (
       SELECT basket_id, group_id
       FROM actions_groupbaskets ag
         LEFT JOIN actions a ON ag.id_action = a.id
       WHERE ag.default_action_list = 'Y' AND a.action_page in ('validate_mail', 'process')
     ) AS subquery
WHERE groupbasket.basket_id = subquery.basket_id AND groupbasket.group_id = subquery.group_id;
UPDATE groupbasket SET list_event = 'viewDoc'
FROM (
       SELECT basket_id, group_id
       FROM actions_groupbaskets ag
         LEFT JOIN actions a ON ag.id_action = a.id
       WHERE ag.default_action_list = 'Y' AND a.component = 'viewDoc'
     ) AS subquery
WHERE groupbasket.basket_id = subquery.basket_id AND groupbasket.group_id = subquery.group_id;
UPDATE groupbasket SET list_event = 'signatureBookAction'
FROM (
       SELECT basket_id, group_id
       FROM actions_groupbaskets ag
         LEFT JOIN actions a ON ag.id_action = a.id
       WHERE ag.default_action_list = 'Y' AND a.action_page in ('visa_mail')
     ) AS subquery
WHERE groupbasket.basket_id = subquery.basket_id AND groupbasket.group_id = subquery.group_id;
ALTER TABLE groupbasket DROP COLUMN IF EXISTS list_event_data;
ALTER TABLE groupbasket ADD COLUMN list_event_data jsonb;

update groupbasket set list_event_data = '{"canUpdate":true,"defaultTab":"info"}'
where group_id in (
    select group_id
    from actions_groupbaskets
    where id_action in (
        select id
        from actions
        where action_page = 'validate_mail'
    ) and groupbasket.basket_id = actions_groupbaskets.basket_id
);

UPDATE groupbasket SET list_event_data = '{"defaultTab":"info"}'
WHERE list_event = 'processDocument' AND (list_event_data IS NULL OR list_event_data::text = '');

-- /!\ Do not move : update actions AFTER all updates on groupbasket
UPDATE actions SET component = 'confirmAction', action_page = null  WHERE action_page in ('validate_mail', 'process', 'visa_mail');

DELETE FROM actions_categories WHERE action_id in (SELECT id FROM actions WHERE component = 'viewDoc' OR action_page in ('view'));
DELETE FROM actions_groupbaskets WHERE id_action in (SELECT id FROM actions WHERE component = 'viewDoc' OR action_page in ('view'));
DELETE FROM groupbasket_redirect WHERE action_id in (SELECT id FROM actions WHERE component = 'viewDoc' OR action_page in ('view'));
DELETE FROM actions WHERE component = 'viewDoc' OR action_page in ('view');

ALTER TABLE actions DROP COLUMN IF EXISTS parameters;
ALTER TABLE actions ADD COLUMN parameters jsonb NOT NULL DEFAULT '{}';

UPDATE actions SET component = 'rejectVisaBackToPreviousAction' WHERE action_page = 'rejection_visa_previous';
UPDATE actions SET component = 'redirectInitiatorEntityAction' WHERE action_page = 'redirect_visa_entity';
UPDATE actions SET component = 'rejectVisaBackToPreviousAction' WHERE action_page = 'rejection_visa_previous';
UPDATE actions SET component = 'resetVisaAction' WHERE action_page = 'rejection_visa_redactor';
UPDATE actions SET component = 'interruptVisaAction' WHERE action_page = 'interrupt_visa';
UPDATE actions SET component = 'sendSignatureBookAction' WHERE action_page IN ('send_to_visa', 'send_signed_docs');
UPDATE actions SET component = 'continueVisaCircuitAction' WHERE action_page = 'visa_workflow';
UPDATE actions SET component = 'closeMailWithAttachmentsOrNotesAction' WHERE action_page = 'close_mail_with_attachment';
UPDATE actions SET component = 'sendToOpinionCircuitAction' WHERE action_page = 'send_to_avis';
UPDATE actions SET component = 'continueOpinionCircuitAction' WHERE action_page = 'avis_workflow';
UPDATE actions SET component = 'giveOpinionParallelAction' WHERE action_page = 'avis_workflow_simple';
UPDATE actions SET component = 'sendToParallelOpinion' WHERE action_page = 'send_docs_to_recommendation';
UPDATE actions SET component = 'validateParallelOpinionDiffusionAction' WHERE action_page = 'validate_recommendation';
UPDATE actions SET component = 'createAcknowledgementReceiptsAction', parameters = '{"mode": "manual"}' WHERE action_page in ('send_attachments_to_contact', 'send_to_contact_with_mandatory_attachment');

DELETE FROM actions_groupbaskets WHERE id_action IN (SELECT id FROM actions WHERE action_page = 'put_in_copy');
DELETE FROM actions_categories WHERE action_id IN (SELECT id FROM actions WHERE action_page = 'put_in_copy');
DELETE FROM actions WHERE action_page = 'put_in_copy';

/* FOLDERS */
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'folders') AND attname = 'folders_system_id') THEN
    ALTER TABLE folders RENAME TO folder_tmp;
    ALTER TABLE folder_tmp RENAME CONSTRAINT folders_pkey to folders_tmp_pkey;
  END IF;
END$$;

DROP TABLE IF EXISTS folders;
CREATE TABLE folders
(
  id serial NOT NULL,
  label character varying(255) NOT NULL,
  public boolean NOT NULL,
  user_id INTEGER NOT NULL,
  parent_id INTEGER,
  level INTEGER NOT NULL,
  CONSTRAINT folders_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS resources_folders;
CREATE TABLE resources_folders
(
  id serial NOT NULL,
  folder_id INTEGER NOT NULL,
  res_id INTEGER NOT NULL,
  CONSTRAINT resources_folders_pkey PRIMARY KEY (id),
  CONSTRAINT resources_folders_unique_key UNIQUE (folder_id, res_id)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS entities_folders;
CREATE TABLE entities_folders
(
  id serial NOT NULL,
  folder_id INTEGER NOT NULL,
  entity_id INTEGER,
  edition boolean NOT NULL,
  keyword character varying(255),
  CONSTRAINT entities_folders_pkey PRIMARY KEY (id),
  CONSTRAINT entities_folders_unique_key UNIQUE (folder_id, entity_id, keyword)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS users_pinned_folders;
CREATE TABLE users_pinned_folders
(
  id serial NOT NULL,
  folder_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  CONSTRAINT users_pinned_folders_pkey PRIMARY KEY (id),
  CONSTRAINT users_pinned_folders_unique_key UNIQUE (folder_id, user_id)
)
WITH (OIDS=FALSE);


/* CUSTOM FIELDS */
DROP TABLE IF EXISTS custom_fields;
CREATE TABLE custom_fields
(
  id serial NOT NULL,
  label character varying(256) NOT NULL,
  type character varying(256) NOT NULL,
  values jsonb,
  CONSTRAINT custom_fields_pkey PRIMARY KEY (id),
  CONSTRAINT custom_fields_unique_key UNIQUE (label)
)
WITH (OIDS=FALSE);

/* CONTACTS CUSTOM FIELDS */
DROP TABLE IF EXISTS contacts_custom_fields_list;
CREATE TABLE contacts_custom_fields_list
(
  id serial NOT NULL,
  label character varying(256) NOT NULL,
  type character varying(256) NOT NULL,
  values jsonb,
  CONSTRAINT contacts_custom_fields_list_pkey PRIMARY KEY (id),
  CONSTRAINT contacts_custom_fields_list_unique_key UNIQUE (label)
)
WITH (OIDS=FALSE);


/* INDEXING MODELS */
DROP TABLE IF EXISTS indexing_models;
CREATE TABLE indexing_models
(
  id SERIAL NOT NULL,
  label character varying(256) NOT NULL,
  category character varying(256) NOT NULL,
  "default" BOOLEAN NOT NULL,
  owner INTEGER NOT NULL,
  private BOOLEAN NOT NULL,
  master INTEGER DEFAULT NULL,
  enabled BOOLEAN DEFAULT TRUE NOT NULL,
  CONSTRAINT indexing_models_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS indexing_models_fields;
CREATE TABLE indexing_models_fields
(
  id SERIAL NOT NULL,
  model_id INTEGER NOT NULL,
  identifier text NOT NULL,
  mandatory BOOLEAN NOT NULL,
  default_value json,
  unit text NOT NULL,
  CONSTRAINT indexing_models_fields_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);


/* TAGS */
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'tags') AND attname = 'tag_label') = 1 THEN
	  ALTER TABLE tags RENAME COLUMN tag_label TO label;
	  ALTER TABLE tags DROP COLUMN IF EXISTS coll_id;
	  ALTER TABLE tags ADD COLUMN id serial NOT NULL;
	  UPDATE tags SET id = tag_id;
      ALTER TABLE tags DROP COLUMN IF EXISTS tag_id;
      ALTER TABLE tags DROP COLUMN IF EXISTS entity_id_owner;
      ALTER TABLE tags DROP COLUMN IF EXISTS description;
	  ALTER TABLE tags ADD COLUMN description TEXT;
      ALTER TABLE tags DROP COLUMN IF EXISTS parent_id;
      ALTER TABLE tags ADD COLUMN parent_id INT;
      ALTER TABLE tags DROP COLUMN IF EXISTS creation_date;
      ALTER TABLE tags ADD COLUMN creation_date TIMESTAMP DEFAULT NOW();
      ALTER TABLE tags DROP COLUMN IF EXISTS links;
      ALTER TABLE tags ADD COLUMN links jsonb DEFAULT '[]';
      ALTER TABLE tags DROP COLUMN IF EXISTS usage;
      ALTER TABLE tags ADD COLUMN usage TEXT;

      ALTER TABLE tags ADD CONSTRAINT tags_id_pkey PRIMARY KEY (id);

      DROP TABLE IF EXISTS resources_tags;
      ALTER TABLE tag_res ADD COLUMN id serial NOT NULL;
      ALTER TABLE tag_res RENAME TO resources_tags;
  END IF;
END$$;

SELECT setval('tags_id_seq', (SELECT MAX(id) from tags));

DROP TABLE IF EXISTS tags_entities;


/* DOCTYPES */
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'mlb_doctype_ext')) > 0 THEN
	  ALTER TABLE doctypes ADD COLUMN process_delay INTEGER;
	  ALTER TABLE doctypes ADD COLUMN delay1 INTEGER;
	  ALTER TABLE doctypes ADD COLUMN delay2 INTEGER;
	  ALTER TABLE doctypes ADD COLUMN process_mode CHARACTER VARYING(256);
	  UPDATE doctypes SET process_delay = (SELECT process_delay FROM mlb_doctype_ext where doctypes.type_id = mlb_doctype_ext.type_id);
	  UPDATE doctypes SET process_delay = 30 WHERE process_delay is null;
	  UPDATE doctypes SET delay1 = (SELECT delay1 FROM mlb_doctype_ext where doctypes.type_id = mlb_doctype_ext.type_id);
    UPDATE doctypes SET delay1 = 14 WHERE delay1 is null;
	  UPDATE doctypes SET delay2 = (SELECT delay2 FROM mlb_doctype_ext where doctypes.type_id = mlb_doctype_ext.type_id);
    UPDATE doctypes SET delay2 = 1 WHERE delay2 is null;
	  UPDATE doctypes SET process_mode = (SELECT process_mode FROM mlb_doctype_ext where doctypes.type_id = mlb_doctype_ext.type_id);
    UPDATE doctypes SET process_mode = 'NORMAL' WHERE process_mode is null;
	  ALTER TABLE doctypes ALTER COLUMN process_delay SET NOT NULL;
	  ALTER TABLE doctypes ALTER COLUMN delay1 SET NOT NULL;
	  ALTER TABLE doctypes ALTER COLUMN delay2 SET NOT NULL;
	  ALTER TABLE doctypes ALTER COLUMN process_mode SET NOT NULL;
  END IF;
END$$;


/* NOTES */
DO $$ BEGIN
    IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'notes') AND attname = 'type') THEN
        ALTER TABLE notes ADD COLUMN user_tmp_id integer;
        UPDATE notes set user_tmp_id = (select id FROM users where users.user_id = notes.user_id);
        UPDATE notes set user_tmp_id = 0 WHERE user_tmp_id IS NULL;
        ALTER TABLE notes ALTER COLUMN user_tmp_id set not null;
        ALTER TABLE notes DROP COLUMN IF EXISTS user_id;
        ALTER TABLE notes RENAME COLUMN user_tmp_id TO user_id;
        ALTER TABLE notes DROP COLUMN IF EXISTS type;
    END IF;
END$$;


/* ATTACHMENTS */
ALTER TABLE res_attachments DROP COLUMN IF EXISTS origin_id;
ALTER TABLE res_attachments ADD COLUMN origin_id INTEGER;
DO $$ BEGIN
    IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_attachments') AND attname = 'doc_date') THEN
        ALTER TABLE res_attachments RENAME COLUMN doc_date TO modification_date;
        ALTER TABLE res_attachments ALTER COLUMN modification_date set DEFAULT NOW();
    END IF;
END$$;
DO $$ BEGIN
    IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_attachments') AND attname = 'updated_by') THEN
        ALTER TABLE res_attachments ADD COLUMN modified_by integer;
        UPDATE res_attachments set modified_by = (select id FROM users where users.user_id = res_attachments.updated_by);
        ALTER TABLE res_attachments DROP COLUMN IF EXISTS updated_by;
    END IF;
END$$;


/* DOCSERVERS */
UPDATE docservers SET coll_id = 'attachments_coll', is_readonly = 'Y' WHERE coll_id = 'attachments_version_coll' AND docserver_type_id = 'CONVERT';
UPDATE docservers SET coll_id = 'attachments_coll', is_readonly = 'Y' WHERE coll_id = 'attachments_version_coll' AND docserver_type_id = 'FASTHD';
UPDATE docservers SET coll_id = 'attachments_coll', is_readonly = 'Y' WHERE coll_id = 'attachments_version_coll' AND docserver_type_id = 'FULLTEXT';
UPDATE docservers SET coll_id = 'attachments_coll', is_readonly = 'Y' WHERE coll_id = 'attachments_version_coll' AND docserver_type_id = 'TNL';
UPDATE docservers SET docserver_type_id = 'DOC' WHERE coll_id = 'attachments_coll' AND docserver_type_id = 'FASTHD';


/* MLB COLL EXT */
DO $$ BEGIN
    IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_letterbox') AND attname = 'category_id') = 0 THEN
        ALTER TABLE res_letterbox ADD COLUMN category_id character varying(32);
        UPDATE res_letterbox SET category_id = mlb_coll_ext.category_id FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;
        UPDATE res_letterbox set category_id = 'incoming' WHERE category_id IS NULL;
        ALTER TABLE res_letterbox ALTER COLUMN category_id set not null;

        ALTER TABLE res_letterbox ADD COLUMN exp_contact_id integer;
        UPDATE res_letterbox SET exp_contact_id = mlb_coll_ext.exp_contact_id FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN exp_user_id character varying(128);
        UPDATE res_letterbox SET exp_user_id = mlb_coll_ext.exp_user_id FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN dest_contact_id integer;
        UPDATE res_letterbox SET dest_contact_id = mlb_coll_ext.dest_contact_id FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN dest_user_id character varying(128);
        UPDATE res_letterbox SET dest_user_id = mlb_coll_ext.dest_user_id FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN alt_identifier character varying(256);
        UPDATE res_letterbox SET alt_identifier = mlb_coll_ext.alt_identifier FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN admission_date timestamp without time zone;
        UPDATE res_letterbox SET admission_date = mlb_coll_ext.admission_date FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN process_limit_date timestamp without time zone;
        UPDATE res_letterbox SET process_limit_date = mlb_coll_ext.process_limit_date FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN closing_date timestamp without time zone;
        UPDATE res_letterbox SET closing_date = mlb_coll_ext.closing_date FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN flag_alarm1 character(1) DEFAULT 'N'::character varying;
        UPDATE res_letterbox SET flag_alarm1 = mlb_coll_ext.flag_alarm1 FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN flag_alarm2 character(1) DEFAULT 'N'::character varying;
        UPDATE res_letterbox SET flag_alarm2 = mlb_coll_ext.flag_alarm2 FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN is_multicontacts character(1);
        UPDATE res_letterbox SET is_multicontacts = mlb_coll_ext.is_multicontacts FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN address_id INTEGER;
        UPDATE res_letterbox SET address_id = mlb_coll_ext.address_id FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN alarm1_date timestamp without time zone;
        UPDATE res_letterbox SET alarm1_date = mlb_coll_ext.alarm1_date FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;

        ALTER TABLE res_letterbox ADD COLUMN alarm2_date timestamp without time zone;
        UPDATE res_letterbox SET alarm2_date = mlb_coll_ext.alarm2_date FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id;
    END IF;
END$$;

UPDATE res_letterbox SET exp_user_id = NULL WHERE exp_user_id = '0';
UPDATE res_letterbox SET exp_contact_id = NULL WHERE exp_contact_id = 0;
UPDATE res_letterbox SET dest_user_id = NULL WHERE dest_user_id = '0';
UPDATE res_letterbox SET dest_contact_id = NULL WHERE dest_contact_id = 0;

DO $$ BEGIN
    IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'res_letterbox') AND attname = 'external_signatory_book_id') = 1 THEN
      UPDATE res_letterbox SET external_id = jsonb_set(external_id, '{signatureBookId}', external_signatory_book_id::text::jsonb) WHERE external_signatory_book_id IS NOT NULL;
      ALTER TABLE res_letterbox DROP COLUMN IF EXISTS external_signatory_book_id;
    END IF;
END$$;

/* RES_LETTERBOX */
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS department_number_id;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS external_link;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS model_id;
ALTER TABLE res_letterbox ADD COLUMN model_id INTEGER;
UPDATE res_letterbox set model_id = 2 WHERE category_id = 'outgoing';
UPDATE res_letterbox set model_id = 3 WHERE category_id = 'internal';
UPDATE res_letterbox set model_id = 4 WHERE category_id = 'ged_doc';
UPDATE res_letterbox set model_id = 1 WHERE model_id IS NULL;
ALTER TABLE res_letterbox ALTER COLUMN model_id set not null;
DO $$ BEGIN
    IF (SELECT count(column_name) from information_schema.columns where table_name = 'res_letterbox' and column_name = 'typist' and data_type != 'integer') THEN
        ALTER TABLE res_letterbox ADD COLUMN typist_tmp integer;
        UPDATE res_letterbox set typist_tmp = (select id FROM users where users.user_id = res_letterbox.typist);
        UPDATE res_letterbox set typist_tmp = 0 WHERE typist_tmp IS NULL;
        ALTER TABLE res_letterbox ALTER COLUMN typist_tmp set not null;
        ALTER TABLE res_letterbox DROP COLUMN IF EXISTS typist;
        ALTER TABLE res_letterbox RENAME COLUMN typist_tmp TO typist;
        UPDATE baskets SET basket_clause = REGEXP_REPLACE(basket_clause, 'typist(\s*)=(\s*)@user', 'typist = @user_id', 'gmi');
        UPDATE security SET where_clause = REGEXP_REPLACE(where_clause, 'typist(\s*)=(\s*)@user', 'typist = @user_id', 'gmi');
    END IF;
END$$;
ALTER TABLE res_letterbox ADD COLUMN IF NOT EXISTS scan_date timestamp without time zone;
ALTER TABLE res_letterbox ADD COLUMN IF NOT EXISTS scan_user CHARACTER VARYING (50) DEFAULT NULL::character varying;
ALTER TABLE res_letterbox ADD COLUMN IF NOT EXISTS scan_location CHARACTER VARYING (255) DEFAULT NULL::character varying;
ALTER TABLE res_letterbox ADD COLUMN IF NOT EXISTS scan_wkstation CHARACTER VARYING (255) DEFAULT NULL::character varying;
ALTER TABLE res_letterbox ADD COLUMN IF NOT EXISTS scan_batch CHARACTER VARYING (50) DEFAULT NULL::character varying;
ALTER TABLE res_letterbox ADD COLUMN IF NOT EXISTS scan_postmark CHARACTER VARYING (50) DEFAULT NULL::character varying;

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS custom_fields;
ALTER TABLE res_letterbox ADD COLUMN custom_fields jsonb DEFAULT '{}';

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS linked_resources;
ALTER TABLE res_letterbox ADD COLUMN linked_resources jsonb NOT NULL DEFAULT '[]';


/* USERGROUP_CONTENT */
DO $$ BEGIN
    IF (SELECT count(column_name) from information_schema.columns where table_name = 'usergroup_content' and column_name = 'user_id' and data_type != 'integer') THEN
        ALTER TABLE usergroup_content ADD COLUMN user_id_tmp integer;
        UPDATE usergroup_content set user_id_tmp = (select id FROM users where users.user_id = usergroup_content.user_id);
        DELETE FROM usergroup_content WHERE user_id_tmp IS NULL;
        ALTER TABLE usergroup_content ALTER COLUMN user_id_tmp set not null;
        ALTER TABLE usergroup_content DROP COLUMN IF EXISTS user_id;
        ALTER TABLE usergroup_content RENAME COLUMN user_id_tmp TO user_id;
    END IF;
END$$;
DO $$ BEGIN
    IF (SELECT count(column_name) from information_schema.columns where table_name = 'usergroup_content' and column_name = 'group_id' and data_type != 'integer') THEN
        ALTER TABLE usergroup_content ADD COLUMN group_id_tmp integer;
        UPDATE usergroup_content set group_id_tmp = (select id FROM usergroups where usergroups.group_id = usergroup_content.group_id);
        DELETE FROM usergroup_content WHERE group_id_tmp IS NULL;
        ALTER TABLE usergroup_content ALTER COLUMN group_id_tmp set not null;
        ALTER TABLE usergroup_content DROP COLUMN IF EXISTS group_id;
        ALTER TABLE usergroup_content RENAME COLUMN group_id_tmp TO group_id;
    END IF;
END$$;


/* CONTACTS */
DROP TABLE IF EXISTS contacts;
CREATE TABLE contacts
(
    id SERIAL NOT NULL,
    civility CHARACTER VARYING(256),
    firstname CHARACTER VARYING(256),
    lastname CHARACTER VARYING(256),
    company CHARACTER VARYING(256),
    department CHARACTER VARYING(256),F
    function CHARACTER VARYING(256),
    address_number CHARACTER VARYING(256),
    address_street CHARACTER VARYING(256),
    address_additional1 CHARACTER VARYING(256),
    address_additional2 CHARACTER VARYING(256),
    address_postcode CHARACTER VARYING(256),
    address_town CHARACTER VARYING(256),
    address_country CHARACTER VARYING(256),
    email CHARACTER VARYING(256),
    phone CHARACTER VARYING(256),
    communication_means jsonb,
    notes text,
    creator INTEGER NOT NULL,
    creation_date TIMESTAMP without time zone NOT NULL DEFAULT NOW(),
    modification_date TIMESTAMP without time zone,
    enabled boolean NOT NULL DEFAULT TRUE,
    custom_fields jsonb,
    external_id jsonb DEFAULT '{}',
    CONSTRAINT contacts_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS contacts_parameters;
CREATE TABLE contacts_parameters
(
    id SERIAL NOT NULL,
    identifier text NOT NULL,
    mandatory boolean NOT NULL DEFAULT FALSE,
    filling boolean NOT NULL DEFAULT FALSE,
    searchable boolean NOT NULL DEFAULT FALSE,
    displayable boolean NOT NULL DEFAULT FALSE,
    CONSTRAINT contacts_parameters_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

ALTER TABLE acknowledgement_receipts DROP COLUMN IF EXISTS contact_id;
ALTER TABLE acknowledgement_receipts ADD COLUMN contact_id integer;
ALTER TABLE contacts_groups_lists DROP COLUMN IF EXISTS contact_id;
ALTER TABLE contacts_groups_lists ADD COLUMN contact_id integer;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS recipient_type;
ALTER TABLE res_attachments ADD COLUMN recipient_type character varying(256);
ALTER TABLE res_attachments DROP COLUMN IF EXISTS recipient_id;
ALTER TABLE res_attachments ADD COLUMN recipient_id integer;

ALTER TABLE adr_letterbox DROP COLUMN IF EXISTS version;
ALTER TABLE adr_letterbox ADD COLUMN version integer;
UPDATE adr_letterbox SET version = 1;
ALTER TABLE adr_letterbox ALTER COLUMN version SET NOT NULL;
ALTER TABLE adr_letterbox DROP CONSTRAINT IF EXISTS adr_letterbox_unique_key;
ALTER TABLE adr_letterbox ADD CONSTRAINT adr_letterbox_unique_key UNIQUE (res_id, type, version);

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS version;
ALTER TABLE res_letterbox ADD COLUMN version integer;
UPDATE res_letterbox SET version = 1;
ALTER TABLE res_letterbox ALTER COLUMN version SET NOT NULL;

ALTER TABLE res_letterbox DROP COLUMN IF EXISTS integrations;
ALTER TABLE res_letterbox ADD COLUMN integrations jsonb DEFAULT '{}' NOT NULL;

ALTER TABLE entities DROP COLUMN IF EXISTS external_id;
ALTER TABLE entities ADD COLUMN external_id jsonb DEFAULT '{}';

/* REFACTORING DATA */
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'usergroups') AND attname = 'enabled') THEN
    DELETE FROM usergroup_content WHERE group_id in (SELECT id FROM usergroups WHERE enabled = 'N');
    DELETE FROM usergroups_reports WHERE group_id in (SELECT group_id FROM usergroups WHERE enabled = 'N');
    DELETE FROM usergroups_services WHERE group_id in (SELECT group_id FROM usergroups WHERE enabled = 'N');
    DELETE FROM security WHERE group_id in (SELECT group_id FROM usergroups WHERE enabled = 'N');
    DELETE FROM groupbasket WHERE group_id in (SELECT group_id FROM usergroups WHERE enabled = 'N');
    DELETE FROM groupbasket_redirect WHERE group_id in (SELECT group_id FROM usergroups WHERE enabled = 'N');
    DELETE FROM groupbasket_status WHERE group_id in (SELECT group_id FROM usergroups WHERE enabled = 'N');
    DELETE FROM users_baskets_preferences WHERE group_serial_id in (SELECT id FROM usergroups WHERE enabled = 'N');
    DELETE FROM usergroups WHERE enabled = 'N';
  END IF;
END$$;

DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'actions') AND attname = 'enabled') THEN
    DELETE FROM actions_categories WHERE action_id in (SELECT id FROM actions WHERE enabled = 'N');
    DELETE FROM actions_groupbaskets WHERE id_action in (SELECT id FROM actions WHERE enabled = 'N');
    DELETE FROM groupbasket_redirect WHERE action_id in (SELECT id FROM actions WHERE enabled = 'N');
    DELETE FROM actions WHERE enabled = 'N';
  END IF;
END$$;

UPDATE listinstance SET item_mode = 'cc' WHERE item_mode = 'copy';
DELETE FROM usergroups_services WHERE service_id = 'admin_fileplan';
DELETE FROM usergroups_services WHERE service_id = 'put_doc_in_fileplan';
DELETE FROM usergroups_services WHERE service_id = 'fileplan';
DELETE FROM usergroups_services WHERE service_id = 'update_case';
DELETE FROM usergroups_services WHERE service_id = 'join_res_case';
DELETE FROM usergroups_services WHERE service_id = 'join_res_case_in_process';
DELETE FROM usergroups_services WHERE service_id = 'close_case';
DELETE FROM usergroups_services WHERE service_id = 'add_cases';
DELETE FROM usergroups_services WHERE service_id IN ('folder_search', 'view_folder_tree', 'select_folder', 'show_history_folder', 'modify_folder', 'associate_folder', 'delete_folder', 'admin_foldertypes', 'create_folder', 'folder_freeze', 'close_folder');
DELETE FROM usergroups_services WHERE service_id = 'add_tag_to_res';
DELETE FROM usergroups_services WHERE service_id = 'tag_view';
DELETE FROM usergroups_services WHERE service_id = 'thesaurus_view';
DELETE FROM usergroups_services WHERE service_id = 'add_thesaurus_to_res';
UPDATE usergroups_services SET service_id = 'manage_tags_application' WHERE service_id = 'create_tag';
UPDATE usergroups_services SET service_id = 'update_status_mail' WHERE service_id = 'reopen_mail';
DELETE FROM usergroups_services WHERE service_id = 'quicklaunch';
DELETE FROM usergroups_services WHERE service_id = 'put_in_validation';
DELETE FROM usergroups_services WHERE service_id = 'print_details';
DELETE FROM usergroups_services WHERE service_id = 'print_doc_details_from_list';
DELETE FROM usergroups_services WHERE service_id = 'view_attachments';
DELETE FROM usergroups_services WHERE service_id = 'index_attachment';
DELETE FROM usergroups_services WHERE service_id = 'display_basket_list';
DELETE FROM usergroups_services WHERE service_id = 'choose_entity';
DELETE FROM usergroups_services WHERE service_id = 'manage_notes_doc';
DELETE FROM usergroups_services WHERE service_id = 'notes_restriction';
DELETE FROM usergroups_services WHERE service_id = 'graphics_reports';
DELETE FROM usergroups_services WHERE service_id = 'show_reports';
DELETE FROM usergroups_services WHERE service_id = 'param_templates_doctypes';
DELETE FROM usergroups_services WHERE service_id = 'doctype_template_use';
DELETE FROM usergroups_services WHERE service_id = 'search_contacts';
DELETE FROM usergroups_services WHERE service_id = 'use_date_in_signBlock';
DELETE FROM usergroups_services WHERE service_id = 'delete_document_in_detail';
UPDATE usergroups_services SET service_id = 'manage_numeric_package' WHERE service_id = 'save_numeric_package';
INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'manage_numeric_package'
FROM usergroups_services WHERE group_id IN (
    SELECT group_id FROM usergroups_services
    WHERE service_id = 'sendmail' AND group_id not in (SELECT group_id FROM usergroups_services WHERE service_id = 'manage_numeric_package')
);

DELETE FROM usergroups_services WHERE service_id = 'include_folders_and_followed_resources_perimeter';

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'include_folders_and_followed_resources_perimeter' FROM usergroups_services;

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'update_diffusion_indexing'
FROM usergroups_services WHERE service_id = 'edit_recipient_outside_process';

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'update_diffusion_details'
FROM usergroups_services WHERE service_id = 'edit_recipient_outside_process';

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'update_diffusion_except_recipient_details'
FROM usergroups_services WHERE service_id = 'update_list_diff_in_details';

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'update_diffusion_process'
FROM usergroups_services WHERE service_id = 'edit_recipient_in_process';

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'update_diffusion_except_recipient_process'
FROM usergroups_services us WHERE group_id NOT IN (SELECT distinct(group_id) FROM usergroups_services WHERE service_id = 'edit_recipient_in_process')
AND group_id NOT IN (SELECT group_id FROM usergroups_services us2 WHERE us2.group_id = us.group_id and service_id = 'update_diffusion_except_recipient_process');

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'update_diffusion_except_recipient_indexing'
FROM usergroups_services WHERE group_id NOT IN (
SELECT group_id FROM usergroups_services
WHERE service_id = 'edit_recipient_outside_process' OR service_id = 'update_diffusion_indexing' OR service_id = 'update_diffusion_except_recipient_indexing'
);
DELETE FROM usergroups_services WHERE service_id = 'edit_recipient_outside_process';
DELETE FROM usergroups_services WHERE service_id = 'update_list_diff_in_details';
DELETE FROM usergroups_services WHERE service_id = 'edit_recipient_in_process';
UPDATE usergroups_services SET service_id = 'edit_resource' WHERE service_id = 'edit_document_in_detail';

DELETE FROM usergroups_services WHERE service_id = 'edit_attachments_from_detail';
INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'manage_attachments'
FROM usergroups_services WHERE group_id IN (
    SELECT group_id FROM usergroups_services
    WHERE service_id = 'modify_attachments' OR service_id = 'delete_attachments'
);
DELETE FROM usergroups_services WHERE service_id = 'modify_attachments';
DELETE FROM usergroups_services WHERE service_id = 'delete_attachments';

ALTER TABLE usergroups_services DROP COLUMN IF EXISTS parameters;
ALTER TABLE usergroups_services ADD parameters jsonb;
UPDATE usergroups_services SET parameters = (
    cast('{"groups": [' || (
        SELECT string_agg(cast(id AS VARCHAR), ', ' ORDER BY id) FROM usergroups
    ) || ']}' AS jsonb)
    )
WHERE service_id = 'admin_users';

UPDATE groupbasket SET list_event_data = '{"canUpdateDocument":true}'
WHERE list_event = 'signatureBookAction' AND group_id in (
    select distinct(group_id)
    from usergroups_services
    where service_id = 'manage_attachments'
);

DELETE FROM usergroups_services WHERE service_id = 'view_personal_data' or service_id = 'manage_personal_data';
INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'view_personal_data'
FROM usergroups_services WHERE group_id IN (
    SELECT group_id FROM usergroups_services
    WHERE service_id = 'admin_users'
);
INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'manage_personal_data'
FROM usergroups_services WHERE group_id IN (
    SELECT group_id FROM usergroups_services
    WHERE service_id = 'admin_users'
);
INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'admin_tag'
FROM usergroups_services WHERE group_id IN (
    SELECT group_id FROM usergroups_services
    WHERE service_id = 'admin_thesaurus'
) AND group_id NOT IN (
    SELECT group_id FROM usergroups_services
    WHERE service_id = 'admin_tag'
);
DELETE FROM usergroups_services WHERE service_id = 'admin_thesaurus';

UPDATE history SET event_type = 'PRE' where event_type = 'RET';


DO $$
  BEGIN
    IF EXISTS
      (select 1 from information_schema.tables where table_name = 'listmodels')
    THEN
      UPDATE listmodels SET title = object_id WHERE title IS NULL;
    END IF;
  END
$$ ;
UPDATE baskets SET basket_clause = REGEXP_REPLACE(basket_clause, 'coll_id(\s*)=(\s*)''letterbox_coll''(\s*)AND', '', 'gmi') WHERE basket_id in ('CopyMailBasket', 'DdeAvisBasket');
UPDATE baskets SET basket_clause = REGEXP_REPLACE(basket_clause, 'coll_id(\s*)=(\s*)''letterbox_coll''(\s*)and', '', 'gmi') WHERE basket_id in ('CopyMailBasket', 'DdeAvisBasket');


UPDATE templates SET template_target = 'attachments' WHERE (template_target = '' OR template_target is null) AND template_type = 'OFFICE';
UPDATE templates SET template_target = 'notes' WHERE (template_target = '' OR template_target is null) AND template_type = 'TXT';
DELETE FROM templates WHERE template_target = '' OR template_target is null;

/* ListTemplates */
DROP TABLE IF EXISTS list_templates;
CREATE TABLE list_templates
(
    id SERIAL NOT NULL,
    title text NOT NULL,
    description text,
    type CHARACTER VARYING(32) NOT NULL,
    entity_id INTEGER,
    owner INTEGER DEFAULT NULL,
    CONSTRAINT list_templates_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

DROP TABLE IF EXISTS list_templates_items;
CREATE TABLE list_templates_items
(
    id SERIAL NOT NULL,
    list_template_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    item_type CHARACTER VARYING(32) NOT NULL,
    item_mode CHARACTER VARYING(64) NOT NULL,
    sequence INTEGER NOT NULL,
    CONSTRAINT list_templates_items_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);


/* REFACTORING MODIFICATION */
ALTER TABLE notif_email_stack ALTER COLUMN attachments TYPE text;
ALTER TABLE tags ALTER COLUMN label TYPE character varying(128);
UPDATE priorities SET delays = 30 WHERE delays IS NULL;
ALTER TABLE priorities ALTER COLUMN delays SET NOT NULL;
ALTER TABLE res_letterbox ALTER COLUMN status DROP NOT NULL;
ALTER TABLE res_letterbox ALTER COLUMN docserver_id DROP NOT NULL;
ALTER TABLE res_letterbox ALTER COLUMN format DROP NOT NULL;
ALTER TABLE notif_email_stack ALTER COLUMN recipient TYPE text;
ALTER TABLE notif_email_stack ALTER COLUMN cc TYPE text;
ALTER TABLE notif_email_stack ALTER COLUMN bcc TYPE text;


/* REFACTORING SUPPRESSION */
DO $$ BEGIN
  IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'users') AND attname = 'enabled') THEN
    UPDATE users SET status = 'SPD' WHERE enabled = 'N' and (status = 'OK' or status = 'ABS');
    ALTER TABLE users DROP COLUMN IF EXISTS enabled;
  END IF;
END$$;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS converter_result;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS convert_result;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS convert_result;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS convert_attempts;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS convert_attempts;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS fulltext_attempts;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS fulltext_attempts;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS tnl_attempts;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS tnl_attempts;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS tnl_result;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS tnl_result;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS coll_id;
ALTER TABLE usergroups DROP COLUMN IF EXISTS enabled;
ALTER TABLE actions DROP COLUMN IF EXISTS enabled;
ALTER TABLE actions DROP COLUMN IF EXISTS origin;
ALTER TABLE actions DROP COLUMN IF EXISTS create_id;
ALTER TABLE actions DROP COLUMN IF EXISTS category_id;
DROP VIEW IF EXISTS fp_view_fileplan;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS folders_system_id;
DROP TABLE IF EXISTS foldertypes;
DROP TABLE IF EXISTS foldertypes_doctypes;
DROP TABLE IF EXISTS foldertypes_doctypes_level1;
DROP TABLE IF EXISTS foldertypes_indexes;
ALTER TABLE doctypes DROP COLUMN IF EXISTS coll_id;
DROP TABLE IF EXISTS mlb_doctype_ext;
ALTER TABLE priorities DROP COLUMN IF EXISTS working_days;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS title;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS identifier;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS source;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS relation;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS offset_doc;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS is_multi_docservers;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS tablename;
ALTER TABLE res_letterbox DROP COLUMN IF EXISTS validation_date;
ALTER TABLE listinstance DROP COLUMN IF EXISTS added_by_entity;
ALTER TABLE listinstance DROP COLUMN IF EXISTS coll_id;
ALTER TABLE listinstance DROP COLUMN IF EXISTS listinstance_type;
ALTER TABLE listinstance DROP COLUMN IF EXISTS visible;
ALTER TABLE listinstance_history_details DROP COLUMN IF EXISTS added_by_entity;
ALTER TABLE usergroup_content DROP COLUMN IF EXISTS primary_group;
ALTER TABLE emails ALTER COLUMN document type jsonb;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS subject;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS description;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS type_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS author;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS source;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS folders_system_id;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS offset_doc;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS destination;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS priority;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS initiator;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS is_multicontacts;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS is_multi_docservers;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS tnl_path;
ALTER TABLE res_attachments DROP COLUMN IF EXISTS tnl_filename;
ALTER TABLE users DROP COLUMN IF EXISTS custom_t1;
ALTER TABLE users DROP COLUMN IF EXISTS custom_t2;
ALTER TABLE users DROP COLUMN IF EXISTS custom_t3;
DROP TABLE IF EXISTS templates_doctype_ext;
DROP TABLE IF EXISTS notif_rss_stack;

/* M2M */
DO $$ BEGIN
  IF (SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'mlb_coll_ext')) THEN
    UPDATE res_letterbox SET external_id = json_build_object('m2m', reference_number), reference_number = null FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND mlb_coll_ext.nature_id = 'message_exchange';
    UPDATE mlb_coll_ext SET nature_id = null WHERE nature_id = 'message_exchange';
  END IF;
END$$;

/* DATA */
TRUNCATE TABLE custom_fields;
INSERT INTO custom_fields (id, label, type, values) VALUES (1, 'Nature', 'select', '["Courrier simple", "Courriel", "Courrier suivi", "Courrier avec AR", "Autre"]');
SELECT setval('custom_fields_id_seq', (select max(id)+1 from custom_fields), false);

DO $$ BEGIN
  IF (SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'mlb_coll_ext')) THEN
    UPDATE res_letterbox SET custom_fields = json_build_object('1', 'Courrier simple') FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND mlb_coll_ext.nature_id = 'simple_mail';
    UPDATE res_letterbox SET custom_fields = json_build_object('1', 'Courriel') FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND mlb_coll_ext.nature_id = 'email';
    UPDATE res_letterbox SET custom_fields = json_build_object('1', 'Autre') FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND mlb_coll_ext.nature_id in ('fax', 'other', 'courier');
    UPDATE res_letterbox SET custom_fields = json_build_object('1', 'Courrier suivi') FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND mlb_coll_ext.nature_id in ('chronopost', 'fedex');
    UPDATE res_letterbox SET custom_fields = json_build_object('1', 'Courrier avec AR') FROM mlb_coll_ext WHERE res_letterbox.res_id = mlb_coll_ext.res_id AND mlb_coll_ext.nature_id = 'registered_mail';
  END IF;
END$$;
UPDATE baskets set basket_clause = replace(basket_clause, 'nature_id' , 'custom_fields->>''1''');

UPDATE baskets SET basket_clause = replace(basket_clause, 's.attachment_id = r.res_id', 's.document_id = r.res_id AND s.document_type = ''attachment''')
WHERE basket_clause ILIKE '%s.attachment_id = r.res_id%';

UPDATE baskets SET basket_clause = replace(basket_clause, 'attachment_id', 'document_id')
WHERE basket_clause ILIKE '%attachment_id%';


UPDATE history SET user_id = (select user_id from users order by user_id='superadmin' desc limit 1) where user_id = '';

/* users followed resources */
DROP TABLE IF EXISTS users_followed_resources;
CREATE TABLE users_followed_resources
(
    id serial NOT NULL,
    res_id int NOT NULL,
    user_id int NOT NULL,
    CONSTRAINT users_followed_resources_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

/* shipping */
DO $$ BEGIN
    IF (SELECT count(attname) FROM pg_attribute WHERE attrelid = (SELECT oid FROM pg_class WHERE relname = 'shippings') AND attname = 'attachment_id') = 1 THEN
        ALTER TABLE shippings DROP COLUMN IF EXISTS document_type;
        ALTER TABLE shippings ADD COLUMN document_type character varying(255);
        ALTER TABLE shippings RENAME COLUMN attachment_id TO document_id;
        UPDATE shippings SET document_type = 'attachment';
        ALTER TABLE shippings ALTER COLUMN document_type SET NOT NULL;
    END IF;
END$$;
ALTER TABLE shippings DROP COLUMN IF EXISTS recipients;
ALTER TABLE shippings ADD COLUMN recipients jsonb DEFAULT '[]';

TRUNCATE TABLE indexing_models;
INSERT INTO indexing_models (id, category, label, "default", owner, private) VALUES (1, 'incoming', 'Courrier arrivée', TRUE, 23, FALSE);
INSERT INTO indexing_models (id, category, label, "default", owner, private) VALUES (2, 'outgoing', 'Courrier départ', FALSE, 23, FALSE);
INSERT INTO indexing_models (id, category, label, "default", owner, private) VALUES (3, 'internal', 'Courrier interne', FALSE, 23, FALSE);
INSERT INTO indexing_models (id, category, label, "default", owner, private) VALUES (4, 'ged_doc', 'Document ged', FALSE, 23, FALSE);
Select setval('indexing_models_id_seq', (select max(id)+1 from indexing_models), false);

TRUNCATE TABLE indexing_models_fields;
/* Arrivée */
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'doctype', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'priority', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'confidentiality', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'documentDate', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'arrivalDate', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'subject', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'indexingCustomField_1', FALSE, '"Courrier simple"', 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'senders', TRUE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'recipients', FALSE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'initiator', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'destination', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'processLimitDate', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'folders', FALSE, null, 'classifying');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (1, 'tags', FALSE, null, 'classifying');

/* Départ */
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'doctype', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'priority', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'confidentiality', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'documentDate', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'departureDate', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'subject', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'indexingCustomField_1', FALSE, '"Courrier simple"', 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'senders', FALSE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'recipients', TRUE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'initiator', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'destination', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'processLimitDate', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'folders', FALSE, null, 'classifying');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (2, 'tags', FALSE, null, 'classifying');

/* Interne */
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'doctype', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'priority', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'confidentiality', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'documentDate', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'subject', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'indexingCustomField_1', FALSE, '"Courrier simple"', 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'senders', FALSE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'recipients', FALSE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'initiator', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'destination', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'processLimitDate', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'folders', FALSE, null, 'classifying');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (3, 'tags', FALSE, null, 'classifying');

/* GED */
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'doctype', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'confidentiality', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'documentDate', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'subject', TRUE, null, 'mail');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'senders', FALSE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'recipients', FALSE, null, 'contact');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'initiator', TRUE, null, 'process');
INSERT INTO indexing_models_fields (model_id, identifier, mandatory, default_value, unit) VALUES (4, 'destination', TRUE, null, 'process');

-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.5 to 21.03.14                             --
--                                                                          --
--                                                                          --
-- *************************************************************************--
--DATABASE_BACKUP|shippings|shipping_templates|attachment_types

ALTER TABLE shippings DROP COLUMN IF EXISTS history;
ALTER TABLE shippings ADD COLUMN history jsonb DEFAULT '[]'::jsonb;
ALTER TABLE shippings DROP COLUMN IF EXISTS sending_id;
ALTER TABLE shippings ADD COLUMN sending_id CHARACTER VARYING(40);
ALTER TABLE shippings DROP COLUMN IF EXISTS attachments;
ALTER TABLE shippings ADD COLUMN attachments jsonb DEFAULT '[]'::jsonb;
ALTER TABLE shippings DROP COLUMN IF EXISTS action_id;
ALTER TABLE shippings ADD COLUMN action_id INTEGER;

ALTER TABLE shipping_templates DROP COLUMN IF EXISTS subscriptions;
ALTER TABLE shipping_templates ADD COLUMN subscriptions jsonb DEFAULT '[]'::jsonb;
ALTER TABLE shipping_templates DROP COLUMN IF EXISTS token_min_iat;
ALTER TABLE shipping_templates ADD COLUMN token_min_iat TIMESTAMP WITHOUT TIME ZONE DEFAULT now();

DELETE FROM attachment_types WHERE type_id IN ('shipping_deposit_proof', 'shipping_acknowledgement_of_receipt');
INSERT INTO attachment_types (type_id, label, visible, email_link, signable, icon, chrono, version_enabled, new_version_default) VALUES ('shipping_deposit_proof', 'Preuve de dépôt Maileva', false, false, false, 'M', false, false, false);
INSERT INTO attachment_types (type_id, label, visible, email_link, signable, icon, chrono, version_enabled, new_version_default) VALUES ('shipping_acknowledgement_of_receipt', 'Accusé de réception Maileva', false, false, false, 'M', false, false, false);

UPDATE parameters SET param_value_string = '21.03.14' WHERE id = 'database_version';

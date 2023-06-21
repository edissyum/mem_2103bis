-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.4 to 21.03.5                              --
--                                                                          --
--                                                                          --
-- *************************************************************************--
--DATABASE_BACKUP|entities

DROP TABLE IF EXISTS address_sectors;
CREATE TABLE address_sectors
(
    id SERIAL NOT NULL,
    address_number CHARACTER VARYING(256),
    address_street CHARACTER VARYING(256),
    address_postcode CHARACTER VARYING(256),
    address_town CHARACTER VARYING(256),
    label CHARACTER VARYING(256),
    ban_id CHARACTER VARYING(256),
    CONSTRAINT address_sectors_key UNIQUE (address_number, address_street, address_postcode, address_town),
    CONSTRAINT address_sectors_pkey PRIMARY KEY (id)
)
WITH (OIDS=FALSE);

ALTER TABLE contacts DROP COLUMN IF EXISTS sector;
ALTER TABLE contacts ADD COLUMN sector CHARACTER VARYING(256);

DELETE FROM contacts_parameters WHERE identifier = 'sector';
INSERT INTO contacts_parameters (identifier, mandatory, filling, searchable, displayable) VALUES ('sector', false, false, false, false);

UPDATE entities SET external_id = '{}' WHERE external_id::text='null';
UPDATE entities SET external_id = external_id - 'fastParapheurSubscriberId';
UPDATE entities SET external_id = jsonb_set(external_id, '{fastParapheurSubscriberId}', to_jsonb(REPLACE(business_id, '/', '' ))) WHERE business_id IS NOT NULL;

UPDATE history SET event_id = 'userlogin' WHERE event_id = 'login';

UPDATE parameters SET param_value_string = '21.03.5' WHERE id = 'database_version';

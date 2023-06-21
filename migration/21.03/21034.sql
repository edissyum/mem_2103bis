-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.3 to 21.03.4                              --
--                                                                          --
--                                                                          --
-- *************************************************************************--

ALTER TABLE templates DROP COLUMN IF EXISTS options;
ALTER TABLE templates ADD COLUMN options JSONB DEFAULT '{}';
UPDATE templates SET options = '{"acknowledgementReceiptFrom": "destination"}' WHERE template_target = 'acknowledgementReceipt';

ALTER TABLE acknowledgement_receipts DROP COLUMN IF EXISTS cc;
ALTER TABLE acknowledgement_receipts ADD COLUMN cc JSONB DEFAULT '[]';
ALTER TABLE acknowledgement_receipts DROP COLUMN IF EXISTS cci;
ALTER TABLE acknowledgement_receipts ADD COLUMN cci JSONB DEFAULT '[]';

UPDATE parameters SET param_value_string = '21.03.4' WHERE id = 'database_version';

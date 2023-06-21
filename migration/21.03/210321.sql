-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.20 to 21.03.21                            --
--                                                                          --
--                                                                          --
-- *************************************************************************--

ALTER TABLE attachment_types DROP COLUMN IF EXISTS signed_by_default;
ALTER TABLE attachment_types ADD COLUMN signed_by_default BOOLEAN DEFAULT FALSE;


UPDATE indexing_models_fields SET default_value = NULL WHERE identifier = 'processLimitDate' AND enabled = true;


DROP TABLE IF EXISTS blacklist CASCADE;
CREATE TABLE blacklist
(
  id SERIAL PRIMARY KEY,
  term CHARACTER VARYING(128) UNIQUE NOT NULL
)
WITH (OIDS=FALSE);

CREATE OR REPLACE VIEW bad_notes AS
  SELECT *
  FROM notes
  WHERE unaccent(note_text) ~* concat('\m(', array_to_string(array((select unaccent(term) from blacklist)), '|', ''), ')\M');


-- Create a sequence and set value for each chronos found in parameters table
DO $$
DECLARE
  last_chrono text;
  chrono record;
  chrono_seq_name text;
BEGIN	
  -- Loop through each chrono (of current year) found in parameters table
	FOR chrono IN (SELECT id, param_value_int as value FROM parameters WHERE id LIKE 'chrono%' || EXTRACT(YEAR FROM CURRENT_DATE)) LOOP
		chrono_seq_name := CONCAT(chrono.id, '_seq');
		
    -- Check if sequence exist, if not create
		IF NOT EXISTS (SELECT 0 FROM pg_class where relname = chrono_seq_name ) THEN
		  EXECUTE 'CREATE SEQUENCE "' || chrono_seq_name || '" INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;';
		END IF;
    -- Set sequence value
		EXECUTE 'SELECT setVal(''"' || chrono_seq_name ||'"'',' || chrono.value ||',false)';
   	END LOOP;
END
$$;

-- Create a sequence for chronos and update value in parameters table
CREATE OR REPLACE FUNCTION public.increase_chrono(chrono_seq_name text, chrono_id_name text) returns table (chrono_id bigint) as $$
DECLARE
    retval bigint;
BEGIN
    -- Check if sequence exist, if not create
	IF NOT EXISTS (SELECT 0 FROM pg_class where relname = chrono_seq_name ) THEN
      EXECUTE 'CREATE SEQUENCE "' || chrono_seq_name || '" INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;';
    END IF;
    -- Check if chrono exist in parameters table, if not create
    IF NOT EXISTS (SELECT 0 FROM parameters where id = chrono_id_name ) THEN
      EXECUTE 'INSERT INTO parameters (id, param_value_int) VALUES ( ''' || chrono_id_name || ''', 1)';
    END IF;
    -- Get next value of sequence, update the value in parameters table before returning the value
    SELECT nextval(chrono_seq_name) INTO retval;
	  UPDATE parameters set param_value_int = retval WHERE id =  chrono_id_name;
	  RETURN QUERY SELECT retval;
END;
$$ LANGUAGE plpgsql;

UPDATE parameters SET param_value_string = '21.03.21' WHERE id = 'database_version';

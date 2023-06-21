INSERT INTO "parameters" ("id", "description", "param_value_string", "param_value_int", "param_value_date")
VALUES ('ecitiz_script', 'Changement de status dans l''application e-Citiz', 'bash "/var/www/html/maarch_courrier/bin/external/ecitiz/update_demande.sh"', NULL, NULL);

DROP FUNCTION IF EXISTS public.fct_res_letterbox_update_ecitiz() CASCADE;
DROP TRIGGER IF EXISTS update_ecitiz ON public.res_letterbox;
DROP FUNCTION IF EXISTS public.fcn_maarch_get_parameter(character varying);

CREATE OR REPLACE FUNCTION public.fcn_maarch_get_parameter(
    parameter_key character varying,
    OUT retour_string character varying,
    OUT retour_int integer,
    OUT retour_date timestamp without time zone)
    RETURNS record
    LANGUAGE 'sql'

    COST 100
    VOLATILE
AS $BODY$SELECT param_value_string, param_value_int, param_value_date FROM public.parameters
         WHERE id = $1;
$BODY$;

CREATE FUNCTION public.fct_res_letterbox_update_ecitiz()
    RETURNS trigger
    LANGUAGE 'plpgsql'
    COST 100
    VOLATILE NOT LEAKPROOF
AS $BODY$DECLARE
    cmd_exec character varying DEFAULT NULL;
    ecitiz_script character varying DEFAULT NULL;
BEGIN
    -- Récupération script Bash
    cmd_exec = 'SELECT retour_string FROM fcn_maarch_get_parameter(''ecitiz_script'')';
    EXECUTE cmd_exec INTO ecitiz_script;

    -- Exécution du script bash
    EXECUTE format('COPY (SELECT 1) TO PROGRAM ''%s''', ecitiz_script || ' ' || NEW.status || ' ' || NEW.res_id);
    RETURN NEW;
END
$BODY$;

COMMENT ON FUNCTION public.fct_res_letterbox_update_ecitiz()
    IS 'Changement de status dans l''application e-Citiz';

CREATE TRIGGER update_ecitiz
    AFTER UPDATE OF status
    ON public.res_letterbox
    FOR EACH ROW
EXECUTE PROCEDURE public.fct_res_letterbox_update_ecitiz();

COMMENT ON TRIGGER update_ecitiz ON public.res_letterbox
    IS 'Changement de status dans l''application e-Citiz';

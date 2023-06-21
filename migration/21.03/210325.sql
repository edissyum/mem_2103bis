-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.24 to 21.03.25                            --
--                                                                          --
--                                                                          --
-- *************************************************************************--

do $$
declare
    cfid text;
begin
    for cfid in (select id::text from custom_fields where type = 'date') loop
        update res_letterbox set custom_fields = custom_fields || concat('{"', cfid, '": null}')::jsonb where custom_fields->>cfid::text = '';
    end loop;
end $$;

UPDATE parameters SET param_value_string = '21.03.25' WHERE id = 'database_version';
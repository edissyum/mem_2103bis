-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.14 to 21.03.20                            --
--                                                                          --
--                                                                          --
-- *************************************************************************--
--DATABASE_BACKUP|parameters

UPDATE parameters SET (param_value_string, param_value_int) = (lpad(param_value_int::text, 2, '0'), NULL) WHERE id = 'defaultDepartment';

UPDATE parameters SET param_value_string = '21.03.20' WHERE id = 'database_version';

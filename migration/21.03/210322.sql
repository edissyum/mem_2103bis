-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.21 to 21.03.22                            --
--                                                                          --
--                                                                          --
-- *************************************************************************--

DELETE FROM parameters WHERE id = 'suggest_links_n_days_ago';
INSERT INTO parameters (id, description, param_value_int) VALUES ('suggest_links_n_days_ago', 'Le nombre de jours sur lequel sont cherchés les courriers à lier', 0);

UPDATE parameters SET param_value_string = '21.03.22' WHERE id = 'database_version';
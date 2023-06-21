-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 21.03.22 to 21.03.24                            --
--                                                                          --
--                                                                          --
-- *************************************************************************--

UPDATE indexing_models_fields SET default_value = NULL WHERE identifier = 'processLimitDate';

update parameters set (id, description) = ('workflowSignatoryRole', 'Rôle de signataire dans le circuit') where id = 'workflowEndBySignatory';
update parameters set (param_value_string, param_value_int) = ('mandatory', null) where param_value_int = 0 and id = 'workflowSignatoryRole';
update parameters set (param_value_string, param_value_int) = ('mandatory_final', null) where param_value_int = 1 and id = 'workflowSignatoryRole';

DELETE FROM parameters WHERE id = 'useSectorsForAddresses';
INSERT INTO parameters (id, description, param_value_int) VALUES ('useSectorsForAddresses', 'Utilisation de la table address_sectors pour autocomplétion des adresses ; la BAN est ignorée (valeur = 1)', 0);

UPDATE parameters SET param_value_string = '21.03.24' WHERE id = 'database_version';

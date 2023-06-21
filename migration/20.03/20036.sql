-- *************************************************************************--
--                                                                          --
--                                                                          --
-- Model migration script - 20.03.5 to 20.03.6                              --
--                                                                          --
--                                                                          --
-- *************************************************************************--
--DATABASE_BACKUP|usergroups_services|groupbasket

UPDATE parameters SET param_value_string = '20.03.6' WHERE id = 'database_version';

DELETE FROM usergroups_services WHERE service_id = 'include_folders_and_followed_resources_perimeter';

INSERT INTO usergroups_services (group_id, service_id)
SELECT distinct(group_id), 'include_folders_and_followed_resources_perimeter' FROM usergroups_services;

UPDATE groupbasket SET list_event_data = '{"canUpdateDocument":true}'
WHERE list_event = 'signatureBookAction' AND group_id in (
    select distinct(group_id)
    from usergroups_services
    where service_id = 'manage_attachments'
);

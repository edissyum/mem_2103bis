Installation :
A noter qu'il faut avoir préalablement installé les paquets nécessaires au bon fonctionnement de Maarch : https://docs.maarch.org/gitbook/html/MaarchCourrier/21.03/guat/guat_installation/debian.html

	git clone https://gitlab.com/edissyum/mem/21.03 /var/www/mem_courrier/
    cd /var/www/mem_courrier/
	git checkout tags/$(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1) -b $(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1)

Procédure pour la mise à jour chez un client (déjà installé en MEM) :

    cp -r /var/www/mem_courrier/ /var/www/mem_courrier.bck/
    pg_dump -Fp --column-inserts -f dump.psql maarch
    cd /var/www/mem_courrier/
    git fetch origin --tags --force (À N'UTILISER QUE POUR MÀJ UN TAG MIS À JOUR SUR LE GITLAB)
    git pull
    git stash
    git checkout tags/$(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1) -b $(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1)
    (si des fichiers gênent, ne pas hésitez à supprimer le fichier en question)

Procédure pour la mise à jour chez un client (non installé en MEM) :

    cp -r /var/www/mem_courrier/ /var/www/mem_courrier.bck/
    pg_dump -Fp --column-inserts -f dump.psql maarch
    cd /var/www/mem_courrier/
    rm -rf node_modules/

    Si nécessaire, supprimer les fichiers qui bloque manuellement

    git remote set-url origin https://gitlab.com/edissyum/mem/21.03
    git pull
    git stash
    git checkout tags/$(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1) -b $(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1)
    (si des fichiers gênent, ne pas hésitez à supprimer le fichier en question)


TECHNIQUE :

Convention de développement MEM :

    Noter dans le README le libellé du dev, ainsi que les fichiers modifiés et/ou créés avec, entre parenthèses, le trigramme
    
    - Amélioration de la gestion des contacts (NCH01)
        - src/frontend/app/administration/contact/page/form/contacts-test.component.ts (Nouveau fichier)
        - src/frontend/app/administration/contact/page/form/contacts-form.component.ts
    
    Dans le(s) fichier(s) modifié(s), mettez en commentaire les informations suivantes :
    
    - EDISSYUM - TRIGRAMME LIBELLÉ_DU_DEV (e.g :  // EDISSYUM - NCH01 Amélioration de la gestion des contacts )
        - Si modification d'un code déjà existant sur une ligne, rajoutez simplement le commentaire en début ou en fin de ligne
        - Si rajout d'un bloc de code faire comme tel :
    // EDISSYUM - NCH01 Amélioration de la gestion des contacts
        ....
        ....
    // END EDISSYUM - NCH01

    Si besoin de rajouter une informations sur la modification (souvent le cas sur une modification d'une ligne), rajouter un commentaire en fin de ligne :
        // EDISSYUM - NCH01 Amélioration de la gestion des contacts | Commenter cette ligne

Procédure de mise à jour de MEM :

    git remote set-url origin https://labs.maarch.org/maarch/MaarchCourrier
    git pull
    git stash
    git checkout tags/$(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1) -b $(git tag --sort=v:refname | grep -E '21.03.+([0-9])$' | tail -1)
    git remote add origin2 https://gitlab.com/edissyum/mem/21.03
    git push origin2 21.03.XX:dev_2103XX
    git remote set-url origin https://gitlab.com/edissyum/mem/21.03
    git remote set-url origin https://gitlab.com/edissyum/mem/21.03
    git remote remove origin2


En dehors des modules Edissyum, vérifiez les commits du dernier TAG MEM afin de reporter les correctifs
Pensez à reporter les spécifiques indiquant que nous sommes sur une installation MEM

PARAMETRES À RAJOUTER :

    - Afin d'utiliser la séparation des QR Codes dans Open-Capture For MEM : 
        - Modifier le paramètre "QrCodePrefix"
        - Mettre la valeur à 1

    - Pour faire la recherche par typist, "Agent qualifiant" dans l'ajout de critères il vous faut :
        - Rajouter un paramètres Maarch : 
            - Identifiant : ActionQualifID
            - Description : Identifiants des actions d'envoi en qualification
            - Type : Chaine de caractères
            - Valeur : Liste des actions d'envoi en qualification, séparée par une virgule (e.g : ACTION#20,ACTION#21) 

    - Afin d'augmenter le nombre de résultats lors de la recherche d'expéditeur ou de destinataire il vous faut :
        - Rajouter un paramètres Maarch : 
            - Identifiant : CorrespondantMaxItems
            - Description : Nombre de résultats maximums pour la recherche de contacts
            - Type : Entier
            - Valeur example : 500

    - Ajout d'un paramètre pour gérer le nombre max lors d'un publipostage :
        INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('PublipostageMaxItems', 'Nombre de résultats maximums pour le publipostage', NULL, 500, NULL);

    - Filtre des utilisateurs pour le module user_quota :
        - Rajouter un paramètres Maarch : 
            - Identifiant : user_quota_filtered
            - Description : Filtre des utilisateur du module user_quota
            - Type : Chaîne de caractères
            - Valeur example : 'superadmin', 'edissyum', 'adminfnc'

    - Paramètrage de la confidentialité des contacts (Dans paramétrage des contacts, cocher "Affichable dans l'autocompletion" :
        - Rajouter un champ personnalisé contacts :
            - Libellé : Coordonnées confidentielles
            - Type : Liste à choix unique
            - Valeurs : 
                - Oui
                - Non

        - Rajouter un champ personnalisé contacts :
            - Libellé : Coordonnées confidentielles avancée
            - Type : Liste à choix unique
            - Valeurs : 
                - Oui
                - Non

        - Rajouter la configuration suivante. À adapater selon vos besoins en fonction des identifiants des champs personnalisés :
            - Valeur possible pour entitiesAllowed : vide, *, entity_id (CAB par exemple) séparé par une virgule. Si entitiesAllowed est vide ou *, tous les utilisateurs avec le bon privilèges pourront visualiser
            - Valeur possible pour hiddenFields : email, phone, address, annotations, custom_ID
            - customId (VALEUR OBLIGATOIRE) : celui du champ personnalisé correspondant (par exemple si l'id du champ custom "Coordonnées confidentielles" est 1 alors il faut mettre 1, idem pour "Coordonnées confidentielles avancée"

            INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('contactsConfidentiality', 'Gestion de la confidentialité des contacts','{"basic": {"customId": "", "entitiesAllowed": "", "hiddenFields": "email, phone, address"}, "advanced": {"customId": "", "entitiesAllowed": "", "hiddenFields": "email, phone, address, annotations"}}', NULL, NULL);

    - Paramétrage de l'envoi de PJ via Nextcloud :
        - Rajout de la ligne suivante dans la table configurations
            INSERT INTO configurations (privilege, value) VALUES ('admin_attachments_hosts', '{"nextcloud": {"byDefault": "", "username": "", "password": "", "url": "", "urlExpirationDate": "", "textAddedAboveURLS": ""}}');

    - Paramétrage de la taille minimale du numéro de chrono :
        - apps/maarch_entreprise/xml/chrono.xml ou custom/CUSTOM_ID/apps/maarch_entreprise/xml/chrono.xml : 
            - Modifier la variable <length></length> : Par défaut elle est de 6

    - Nouvelles variables de fusions :
        - Dans le fichier modules/entities/xml/typentity.xml, passez la variable fusion à true pour les types d'entités 
          que vous souhaitez récupérer dans vos modèles de documents
        - Dans vos modèles de documents utiliser la nouvelle variable de fusion comme telle : 
            - destinationEntityType_ENTITY_TYPE_ID.entity_label 
                - Exemple : destinationEntityType_DGA.entity_label
                - /!\ L'ENTITY_TYPE_ID est sensible à la casse
            - Les variables disponible sont les mêmes que pour l'entité traitante.

    - Paramétrage du script autofoldering :
        - Rajouter le custom dans le fichier bin/autofoldering/autofoldering.sh. 
        - Dans le fichier apps/maarch_entreprise/xml/autofoldering.json, configurez les paramètres suivants :
            "enabled": true, // Il faut mettre enable toujours à true 
            "treeSetup": {
                "userAutoFoldering": "", => Le user_id ou identifiant technique de l'utilisateur qui va lancer le script 
                "edition": false, => Droit de modification / suppression du dossier (par defaut mettre à false)
                "visibility": {  => Paramètres sur la visibilité des dossiers (Laisser les valeurs par defaut)
                    "public": true,
                    "entities": "ALL_ENTITIES"
                },
                "levels": "1", => Le nombre de niveaux que vous souhaitez avoir (le chiffre renseigné doit correspondre au nombre de blocs des niveaux configurés dans le paramètre qui suit)
                "nodes" : [ => Pour chaque niveau il faut dupliquer le groupe de paramètres suivant  
                    {
                        "nodeTargetTable" : "res_view_letterbox", => La table cible
                        "nodeTargetColumn" : "creation_date", => La colonne ciblé dans la table
                        "nodeDataType": "date", => Le type de donnée de la colonne cible (uniquement si c'est une date, mettez date)
                        "dateFormat": "yyyy/mm/dd", => Les formats de dates possibles sont : yyyy => année , yyyy/mm => année/mois , yyyy/mm/dd => année/mois/jour.
                        "nodeClause": "creation_date is not null and creation_date between '2010-01-01' and '2022-12-20'" , => la clause sur la colonne ciblé UNIQUEMENT!!!.
                        "nodeOrderBy": null, => L'orde de classement
                        "level": 0, => Le numéro du niveau courant.  
                        "displayDocs": false => Possibilité de classer des documents dans les dossiers qui seront crées pour ce niveau.  
                        "displayDocsClause": null => La clause spécifie les documents qui seront affichés et qui sera appliquée à tous les nœuds créés. Ce paramétre est fonctionnel uniquement quand  displayDocs est à true.
                        }
                ]
            }
        - Ajout d'une fenêtre pour administrer la recherche des dossiers:
            INSERT INTO configurations (privilege, value) VALUES ('admin_search_folders', '{"listEvent": {"defaultTab": "dashboard"}, "listDisplay": {"subInfos": [{"icon": "fa-traffic-light", "value": "getPriority", "cssClasses": ["align_leftData"]}, {"icon": "fa-calendar", "value": "getCreationAndProcessLimitDates", "cssClasses": ["align_leftData"]}, {"icon": "fa-sitemap", "value": "getAssignee", "cssClasses": ["align_leftData"]}, {"icon": "fa-suitcase", "value": "getDoctype", "cssClasses": ["align_leftData"]}, {"icon": "fa-user", "value": "getRecipients", "cssClasses": ["align_leftData"]}, {"icon": "fa-book", "value": "getSenders", "cssClasses": ["align_leftData"]}], "templateColumns": 6}}');
            INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('showFoldersByDefault', 'Afficher le panel des dossiers par défaut', NULL, 0, NULL); 

    - Paramétrage du module eCitiz : 
        - Copier/Coller les scripts et le fichier de config dans le dossier bin/external/ecitiz du custom
        - Modifier les scripts pour verifier si le chemin de MEM est bon, ainsi que le nom du custom également
        - Modifier le fichier de config pour renseigner les paramètres de connexion à l'applicaiton eCitiz (URL et token)
        - Lancer les lignes suivantes pour créer les champs personnalisés. Modifier ensuite le fichier de config avec les bons identifiants
            INSERT INTO custom_fields (label, type, mode, values) VALUES ('Autres informations', 'string', 'form', '[]');
            INSERT INTO custom_fields (label, type, mode, values) VALUES ('Domaine', 'string', 'form', '[]');
            INSERT INTO custom_fields (label, type, mode, values) VALUES ('Thème', 'string', 'form', '[]');
            INSERT INTO custom_fields (label, type, mode, values) VALUES ('Sous thème', 'string', 'form', '[]');
            INSERT INTO custom_fields (label, type, mode, values) VALUES ('Libellé du sous thème', 'string', 'form', '[]');
            INSERT INTO contacts_custom_fields_list (label, type, values) VALUES ('Titre', 'string', '[]');
            INSERT INTO contacts_custom_fields_list (label, type, values) VALUES ('Service', 'string', '[]');
            INSERT INTO contacts_custom_fields_list (label, type, values) VALUES ('SIRET', 'string', '[]');
            INSERT INTO contacts_custom_fields_list (label, type, values) VALUES ('RNA', 'string', '[]');
            
        - Rajouter également les champs nouveaux champs personnalisé dans le modèle de document

    - Paramétrage d'envoi de mail :
        - Rajout de la ligne suivante dans la table configurations
            INSERT INTO public.parameters (id, description, param_value_int) VALUES ('force_admin_mail_from', 'Forcer la valeur de mailfrom à l''identifiant du compte d''envoi de l''email  (1 pour activer, 0 pour désactiver)', 1);

    - Fenetre de recherche de contacts :
        ALTER TABLE contacts_parameters ADD COLUMN filtrable BOOLEAN DEFAULT FALSE;
        CREATE TABLE contacts_search_templates (
            id serial,
            user_id integer NOT NULL,
            label character varying(255) NOT NULL,
            creation_date timestamp without time zone NOT NULL,
            query json NOT NULL,
            CONSTRAINT contacts_search_templates_pkey PRIMARY KEY (id)
            ) WITH (OIDS=FALSE);
        INSERT INTO contacts_search_templates (user_id, label, creation_date, query) VALUES (23, 'Tous les contacts', '2021-03-25 11:54:30.273871', '[]'); -- EDISSYUM - NCH01 Fenetre de recherche de contacts
        INSERT INTO configurations ( privilege, value) VALUES ('admin_search_contacts', '{"listDisplay": {"subInfos": [{"icon": "fa-user", "label": "Civilité", "value": "getCivility", "cssClasses": ["align_leftData"]}, {"icon": "fa-calendar", "label": "Date de création", "value": "getCreationDate", "cssClasses": ["align_leftData"]}, {"icon": "fa-user", "label": "Courriel", "value": "getEmail", "cssClasses": ["align_leftData"]}, {"icon": "fa-user", "label": "Téléphone", "value": "getPhone", "cssClasses": ["align_leftData"]}, {"icon": "fa-map-marker-alt", "label": "Numéro de rue", "value": "getAddressNumber", "cssClasses": ["align_leftData"]}, {"icon": "fa-map-marker-alt", "label": "Voie", "value": "getAddressStreet", "cssClasses": ["align_leftData"]}], "templateColumns": 6}}'); -- EDISSYUM NCH01 - Fenetre de recherche de contacts

	- Ajout d'éléments paramétrables dans le connecteur Pastell (PYB01). À rajouter dans remoteSignatoryBooks.xml si le tag < 21.03.27-2
```xml
        <pastellType>document-a-signer</pastellType>
        <preActions>
            <action>send-iparapheur</action>
            <!--<action>orientation</action>-->
        </preActions>
        <postActions>
            <!--<action>orientation</action>-->
        </postActions>
```
    - Paramètres script purge Nextcloud
        - Dupliquer config.xml.default en config.xml dans le répertoire custom/bin/external/nextcloud
        - Éditer config.xml en définissant une durée de péremption des pièces jointes (en nombre de jour).
        - Éditer nextcloud.sh en définissant le custom_id de l'installation.
        - Mettre en place une tâche CRON pour lancer le script .sh à l'intervalle voulu.

    - Ajout d'un paramètre pour obliger ou non la présente de la fiche de liaison lors de l'impression en masse
        INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('summarySheetMandatory', 'Fiche de liaison obligatoire lors d''une impression en masse', NULL, 1, NULL);

    - Ajout d'un paramètre pour gérer le nombre max de contacts à afficher dans l'écran de dédoublonnage:
        INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('ContactsDuplicateMaxItems', 'Nombre de résultats maximums pour le dédoublonnage des contacts', NULL, 500, NULL);

Fix effectués :

    - Module export PESV2
        - src/frontend/app/app.module.ts
        - src/frontend/service/actionPages.service.ts
        - src/frontend/app/actions/actions.service.ts
        - src/app/action/controllers/ActionMethodController.php
        - src/frontend/app/actions/export-pesv2-action/export-pesv2-action.component.ts (Nouveau fichier)
        - src/frontend/app/actions/export-pesv2-action/export-pesv2-action.component.css (Nouveau fichier)
        - src/frontend/app/actions/export-pesv2-action/export-pesv2-action.component.html (Nouveau fichier)

    - Module formulaire SVE
        - rest/index.php
        - src/app/contact/controllers/ContactController.php
        - src/app/doctype/controllers/DoctypeController.php

    - Module opencaptureformem
        - rest/index.php
        - src/app/contact/models/ContactModel.php
        - src/app/contact/controllers/ContactController.php
        - src/app/attachment/controllers/ReconciliationController.php (Nouveau fichier)

    - Rajouter la possibilité de spécifier l'application quand on ajoute un id externe à un courrier (PYB01)
        - src/app/resource/controllers/ResController.php
        - Dans l'appel WS PUT /res/externalInfos, il sera possible de rajouter un argument optionnel 'app' 
            "externalInfos" => [
                [
                    "app" => "APPLICATION_X",
                    "external_id => 123,
                    "res_id" => 100,
                    "external_link" => ""
                ]
            ],
            "status" => "VAL"

	- Fix création de doublon de contact (NCH01)
        - src/app/contact/controllers/ContactController.php

	- Rajout de la spécificité MEM dans l'affichage des versions (NCH01)
        - src/app/versionUpdate/controllers/VersionUpdateController.php
		- src/frontend/app/about-us.component.html

    - Patch pour fonctionner sur les VPS ou pour fonctionner avec un custom mentionné dans le vhost : setEnv CUSTOM_MAARCH cs_maarch (PYB01) 
        - src/core/models/CoreConfigModel.php

    - Ajout du typist dans la recherche (NCH01)
        - src/frontend/service/indexing-fields.service.ts
        - src/app/search/controllers/SearchController.php
        - src/frontend/app/search/result-list/search-result-list.component.ts

    - Fix pour permettre à l'utilisateur apache de lancer la commande convert depuis OnlyOffice (PYB01)
        - src/app/convert/controllers/ConvertThumbnailController.php

    - Rajout d'extensions personnalisées (NCH01)
        - apps/maarch_entreprise/xml/extensions.xml

    - Afficher les modèles d'enregistrement désactivés dans la fiche détaillé (NCH01)
        - src/frontend/app/indexation/select-indexing-model/select-indexing-model.component.ts

    - Fix Réconciliation manuelle non fonctionnelle si le document cible n'a pas de doc (NCH01)
        - src/app/action/controllers/ActionMethodController.php

    - Fix de la génération de thumbnails à la volée (NCH01)
        - src/app/convert/controllers/ConvertThumbnailController.php

    - Fix pour la génération des thumbnails depuis un fichier HTML (NCH01)
        - src/app/convert/controllers/ConvertThumbnailController.php

    - Rajout de tables manquantes pour la RAZ des données (NCH01)
        - sql/delete_all_ressources.sql

    - Fix pour éviter une erreur lors de l'envoi de mail (NCH01)
        - src/frontend/plugins/mail-editor/mail-editor.component.ts
    
    - Fix pour permettre la modification d'un courrier départ si le typist est égal à l'utilisateur connecté (NCH01)
        - src/app/resource/controllers/ResController.php
        - src/frontend/app/process/process.component.html
        - src/frontend/app/viewer/document-viewer.component.ts

    - Fix pour permettre au superadmin de voir les emails (NCH01)
        - src/app/email/controllers/EmailController.php
    
    - Add closing_date if status == 'END' (NCH01)
        - src/app/resource/controllers/ResController.php

    - Fix docservers rights for VPS (PYB01)
        - src/app/docserver/controllers/DocserverController.php
    
    - Ajout de la variable de langue userINACT (NCH01)
        - src/lang/lang-fr.json

    - Fix pour éviter l'activation d'une action après le refus d'enregistrer depuis les courriers à qualifier (NCH01)
        - src/frontend/app/process/process.component.ts

    - Ajout d'une option pour restreindre les annotations à notre entités (NCH01)
        - src/frontend/service/privileges.service.ts
        - src/frontend/app/notes/note-editor.component.ts

    - Ajout des scripts de migrations manquant (NCH01)
        - migration/21.03/migrate.sh
        - migration/21.03/migrateActions.php
        - migration/21.03/migrateTemplates.php
        - migration/21.03/migrateAttachmentTypes.php
        - migration/21.03/create_contacts_civilities.sql

    - Augmentation du nombre de résultats pour les destinataires / expéditeurs (NCH01)
        - src/core/controllers/AutoCompleteController.php

    - Améliorations fichier temporaire lors de la planification de notifications (NCH01)
        - src/app/notification/models/NotificationScheduleModelAbstract.php

    - Ajout d'un filtre sur le userquota (NCH01)
        - src/app/user/controllers/UserController.php
 
    - Rajout de la confidentialité des contacts (NCH01)
        - sql/data_fr.sql
        - src/frontend/app/administration/contact/page/form/contacts-form.component.ts
        - src/frontend/app/contact/autocomplete/contact-autocomplete.component.ts
        - src/frontend/app/contact/autocomplete/contact-autocomplete.component.html
        - src/frontend/app/contact/contact-detail/contact-detail.component.ts
        - src/frontend/app/contact/contact-detail/contact-detail.component.html
        - src/frontend/app/contact/contact-resource/contact-resource.component.ts
        - src/frontend/service/privileges.service.ts
        - src/app/contact/controllers/ContactController.php
        - src/frontend/app/administration/contact/list/contacts-list-administration.component.ts
        - src/frontend/app/administration/contact/list/contacts-list-administration.component.html
        - src/core/controllers/AutoCompleteController.php
        - src/frontend/app/administration/contact/modal/contact-modal.component.ts
        - src/frontend/app/administration/contact/modal/contact-modal.component.html
        - src/app/parameter/controllers/ParameterController.php

    - Fix pour gérer les sous-requetes dans les champs custom (OBR01)
        - src/app/customField/controllers/CustomFieldController.php

    - Suppression fonction PHP CAS (NCH01)
        - src/core/controllers/AuthenticationController.php

    - Correction envoi d'AR (NCH01)
        - src/frontend/app/actions/create-acknowledgement-receipt-action/create-acknowledgement-receipt-action.component.ts
        - src/frontend/app/actions/create-acknowledgement-receipt-action/create-acknowledgement-receipt-action.component.html

    - Ajout d'un accusé de lecture (NCH01)
        - src/app/email/scripts/sendEmail.php
        - src/app/email/controllers/EmailController.php
        - src/frontend/plugins/mail-editor/mail-editor.component.ts
        - src/frontend/plugins/mail-editor/mail-editor.component.html
        - src/lang/lang-fr.json
            - Ajout de la variable ask_read_receipt

    - Fix pour éviter une erreur lors de la migration des emails (NCH01)
        - migration/19.04/migrateSendmail.php

    - Amélioration de la deconnexion CAS (EME01)
        - apps/maarch_entreprise/xml/cas_config.xml
        - src/core/controllers/AuthenticationController.php

    - Rajout de l'export PESv2 dans la recherche (NCH01)
        - rest/index.php
        - src/frontend/app/list/tools/tools-list.component.ts
        - src/frontend/app/list/tools/tools-list.component.html
        - src/app/action/controllers/ActionMethodController.php
        - src/frontend/app/actions/confirm-action/confirm-action.component.ts
        - src/frontend/app/actions/confirm-action/confirm-action.component.html

    - Rajout d'une option -> mettre PJ dans mail via liens éphémères (AMO01)
        - src/app/email/scripts/sendEmail.php
        - src/app/parameter/controllers/ParameterController.php
        - src/frontend/app/administration/parameter/other/other-parameters.component.ts
        - src/frontend/app/administration/parameter/other/other-parameters.component.html
        - src/lang/lang-fr.json (après les variables de langues PESV2 de "attachementsHosts" à "interconnectionNextcloudSuccess"
        - src/lang/lang-en.json (après les variables de langues PESV2 de "attachementsHosts" à "interconnectionNextcloudSuccess"
        - src/frontend/plugins/mail-editor/mail-editor.component.ts
        - src/frontend/plugins/mail-editor/mail-editor.component.html
        - rest/index.php
        - src/app/email/controllers/EmailController.php
        - src/app/configuration/controllers/ConfigurationController.php

    - Addulact Démarches Simplifiées (NCH01)
        - rest/index.php
        - src/app/resource/controllers/ResController.php
        - apps/maarch_entreprise/xml/demarches_simplifiees.xml

    - Affichage des documents même si aucun mots clés n'est donné (NCH01)
        - src/frontend/app/search/result-list/search-result-list.component.ts
    
    - Fix pour éviter l'activation d'une action si toutes les infos ne sont pas renseignées (NCH01)
        - src/frontend/app/process/process.component.ts
    
    - Forcage des user_id en lowercase pour la synchro LDAP (NCH01)
        - bin/ldap/synchronizationScript.php

    - Fix pour éviter la suppression d'un utilisateur LDAP si présent dans le circuit de visa (NCH01)
        - src/app/user/controllers/UserController.php

    - Fix pour éviter le passage en SPD des utilisateurs INACT lors de la synchro LDAP (NCH01)
        - src/app/user/controllers/UserController.php

    - Sanitize filename pour dossier d'impression (NCH01)
        - src/app/resource/controllers/FolderPrintController.php

    - Modification du footer (NCH01) 
        - src/lang/lang-fr.json --> Modifier la variable applicationVersion

    - Rajout de la taille du fichier dans l'export (NCH01)
        - src/lang/lang-fr.json --> Ajout de la variable 'filesize'

    - Ne pas remplir certaines tables lors de l'installation (NCH01)
        - sql/data_fr.sql

    - Correctif pour la recherche sur les chiffres (OBR01)
        - src/app/search/controllers/SearchController.php

    - Changement des couleurs de Maarch (NCH01)
        - src/frontend/app/home/dashboard/tile/tile-create.component.ts
        - src/frontend/css/vars.scss 
            - Remplacer primary color par #5E952D
            - Remplacer secondary color par #ED8022

        - Remplacer tous les #135F7F par var(--maarch-color-primary) dans les fichiers .scss
        - Remplacer tous les #1a80ab par var(--maarch-color-primary) dans les fichiers .scss
        - Remplacer tous les #F99830 par var(--maarch-color-secondary) dans les fichiers .scss
        - Remplacer tous les #135F7F par #5E952D dans les fichiers .html, .svg, .css, .sql, .ts
        - Remplacer tous les #24B0ED par #5E952D dans les fichiers .html, .css, .scss
        - Remplacer tous les #F99830 par #ED8022 dans les fichiers .html, .svg, .sql, .ts
        - Remplacer tous les #90CAF9 par #A5DA7 dans les fichiers .sql
        - Remplacer tous les #009DC5 par #5E952D dans les fichiers .sql

        - src/frontend/css/engine.scss
        - src/frontend/app/contact/contact-detail/contact-detail.component.scss

    - Changement du logo de l'application (NCH01 & AMO01)
        - src/frontend/assets/logo.svg

    - Possibilité de modifier la longueur minimale du numéro de chrono (NCH01)
        - apps/maarch_entreprise/xml/chrono.xml
        - src/app/resource/models/ChronoModel.php

    - Fix pour ne pas que la case "Tout selectionner" soit cochée si elle ne doit pas l'être (NCH01)
        - src/frontend/app/activate-user.component.ts
        - src/frontend/app/activate-user.component.html
        - src/frontend/app/profile/parameters/baskets/baskets.component.ts
        - src/frontend/app/profile/parameters/baskets/baskets.component.html

    - Fix pour éviter de supprimer un document qui n'existe pas (NCH01)
        - src/app/contentManagement/controllers/MergeController.php

    - Rajout de la possibilité de link un groupement de correspondants dans un autre (NCH01)
        - src/app/contact/models/ContactGroupListModel.php
        - src/app/contact/controllers/ContactGroupController.php
        - src/frontend/app/contact/autocomplete/contact-autocomplete.component.ts
        - src/frontend/app/contact/autocomplete/contact-autocomplete.component.html
        - src/frontend/app/administration/contact/group/form/contacts-group-form.component.ts
        - src/frontend/app/administration/contact/group/form/contacts-group-form.component.html
        - src/frontend/plugins/mail-editor/mail-editor.component.ts

    - Ajout d'une option pour afficher le mot de passe (NCH01)
        - src/frontend/app/login/login.component.ts
        - src/frontend/app/login/login.component.html

    - Récupération des documents liés à un contact pour Open-Capture (NCH01)
         - rest/index.php
         - src/app/contact/controllers/ContactController.php

    - Amélioration de la notification d'erreur (NCH01)
        - src/frontend/service/notification/notification.service.ts

    - Fix pour éviter une erreur si la BAN n'est pas accessible (NCH01)
        - src/app/contact/controllers/ContactController.php

    - Changement du mode de signature IXBUS par défaut (NCH01)
        - src/frontend/app/actions/send-external-signatory-book-action/ixbus-paraph/ixbus-paraph.component.ts

    - Module d'autofoldering (EMEO1)
        - src/app/folder/models/FolderModelAbstract.php
        - bin/autofoldering/autofoldering.sh (fichier complet)
        - bin/autofoldering/autofolderingScript.php (fichier complet)
        - apps/maarch_entreprise/xml/autofoldering.json.default (fichier complet)

    - Afficher les dossiers dans l'ordre de création par le script de l'autofoldering (EME01)
        - src/app/folder/controllers/FolderController.php
    
    -Récupération d'un document par numéro de chrono (NCH01)
        - rest/index.php
        - src/app/resource/controllers/ResController.php

    - IXBUS : Selection automatique des informations si une seule valeur présente (NCH01)
        - src/frontend/app/actions/send-external-signatory-book-action/ixbus-paraph/ixbus-paraph.component.ts

    - Rajout des variables de fusions pour remonter jusqu'au type d'entité choisie (NCH01)
        - modules/entities/xml/typentity.xml
        - src/app/entity/models/EntityModelAbstract.php
        - src/app/contentManagement/controllers/MergeController.php

    - Récupération d'un groupement de contact par libellé (NCH01)
        - rest/index.php
        - src/app/contact/controllers/ContactGroupController.php

    - Fix si l'encypt key contient des ' (OBR01)
        - src/app/email/controllers/EmailController.php

    - Rajout de la possibilité de visualiser les contacts sans droits d'administration (NCH01)
        - src/frontend/service/privileges.service.ts
        - src/app/contact/controllers/ContactController.php
        - src/frontend/app/administration/contact/page/form/contacts-form.component.ts
        - src/frontend/app/administration/contact/page/form/contacts-form.component.html
        - src/frontend/app/administration/contact/page/contacts-page-administration.component.ts
        - src/frontend/app/administration/contact/page/contacts-page-administration.component.html
        - src/frontend/app/administration/contact/list/contacts-list-administration.component.ts
        - src/frontend/app/administration/contact/list/contacts-list-administration.component.html

    - Fix afin d'éviter que la valeur par défaut d'un champs soit mise même après modification par un utilisateur (NCH01)
        - src/app/resource/controllers/StoreController.php

    - LDAP - Ne pas mettre de \ après le prefix (NCH01)
        - modules/ldap/xml/config.xml.default
        - src/core/controllers/AuthenticationController.php

    - Ajout du connecteur Blueway (PYB01)
        - bin/signatureBook/process_mailsFromSignatoryBook.php
        - modules/visa/xml/remoteSignatoryBooks.xml.default
        - src/app/action/controllers/ExternalSignatoryBookTrait.php
        - src/app/action/controllers/PreProcessActionController.php
        - src/app/external/externalSignatoryBook/controllers/BluewayController.php (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/blueway-paraph/blueway-paraph.component.html (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/blueway-paraph/blueway-paraph.component.scss (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/blueway-paraph/blueway-paraph.component.ts (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/send-external-signatory-book-action.component.html
        - src/frontend/app/actions/send-external-signatory-book-action/send-external-signatory-book-action.component.ts
        - src/frontend/app/app.module.ts 

    - IXBUS : Selection automatique du modèle de circuit (NCH01)
        - modules/visa/xml/remoteSignatoryBooks.xml.default
        - src/app/action/controllers/PreProcessActionController.php
        - src/app/external/externalSignatoryBook/controllers/IxbusController.php
        - src/frontend/app/actions/send-external-signatory-book-action/ixbus-paraph/ixbus-paraph.component.ts

    - Fix taille de l'objet d'une PJ (NCH01)
        - sql/structure.sql

    - Fenetre de recherche de contacts (NCH01)
        - sql/data_fr.sql
        - sql/structure.sql
        - src/frontend/app/app.module.ts
        - src/frontend/app/app.component.html
        - src/app/search/models/SearchModel.php
        - src/frontend/app/app-common.module.ts
        - src/frontend/app/app-routing.module.ts
        - src/frontend/service/contact.service.ts
        - src/frontend/service/privileges.service.ts
        - src/app/search/models/SearchTemplateModel.php
        - src/app/search/controllers/SearchController.php
        - src/app/contact/controllers/ContactController.php
        - src/frontend/app/header/header-right.component.ts
        - src/frontend/service/contactsCriteriaSearch.service.ts (Nouveau fichier)
        - src/app/search/controllers/SearchTemplateController.php
        - src/app/resource/controllers/ResourceListController.php
        - src/frontend/app/administration/administration.module.ts
        - src/frontend/app/contact/search/contact-search.component.ts (Nouveau fichier)
        - src/frontend/app/contact/search/contact-search.component.html (Nouveau fichier)
        - src/app/configuration/controllers/ConfigurationController.php
        - src/frontend/app/contact/search/contact-search.component.scss (Nouveau fichier)
        - src/frontend/app/administration/administration-routing.module.ts
        - src/frontend/app/administration/contact/list/export/contact-export.component.ts
        - src/frontend/app/contact/search/criteria-tool/contacts-criteria-tool.component.ts (Nouveau fichier)
        - src/frontend/app/contact/search/criteria-tool/contacts-criteria-tool.component.html (Nouveau fichier)
        - src/frontend/app/contact/search/criteria-tool/contacts-criteria-tool.component.scss (Nouveau fichier)
        - src/frontend/app/contact/search/result-list/contact-search-result-list.component.ts (Nouveau fichier)
        - src/frontend/app/contact/search/result-list/contact-search-result-list.component.html (Nouveau fichier)
        - src/frontend/app/contact/search/result-list/contact-search-result-list.component.scss (Nouveau fichier)
        - src/frontend/app/administration/contact/search/contact-search-administration.component.ts (Nouveau fichier)
        - src/frontend/app/administration/contact/search/contact-search-administration.component.html (Nouveau fichier)
        - src/frontend/app/administration/contact/search/contact-search-administration.component.scss (Nouveau fichier)
        - src/frontend/app/administration/contact/parameter/contacts-parameters-administration.component.ts
        - src/frontend/app/administration/contact/parameter/contacts-parameters-administration.component.html

        - src/lang/lang-fr.json :
            - contactId
            - searchContacts
            - searchContactFilter
            - searchContactAdvanced
            - quickContactSearchTarget
            - searchContactAdministration
            - contactsParameters_address_town
            - contactsParameters_address_number
            - contactsParameters_address_street
            - noAdminSearchContactsConfiguration
            - contactsParameters_address_country
            - contactsParameters_address_postcode
            - getEmail
            - getPhone
            - getAddressNumber
            - getAddressAdditional1
            - getAddressAdditional2
            - getAddressStreet
            - getAddressTown
            - getAddressCountry
            - getAddressPostCode

    - Ajout du connecteur Pastell (PYB01)
        - rest/index.php
        - src/core/models/CurlModel.php
        - controllers/UserController.php
        - src/frontend/app/app.module.ts
        - modules/visa/xml/remoteSignatoryBooks.xml.default
        - bin/signatureBook/process_mailsFromSignatoryBook.php
        - src/app/action/controllers/ExternalSignatoryBookTrait.php
        - src/app/action/controllers/PreProcessActionController.php
        - src/app/external/externalSignatoryBook/controllers/PastellController.php (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/pastell-paraph/pastell-paraph.component.html (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/pastell-paraph/pastell-paraph.component.scss (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/pastell-paraph/pastell-paraph.component.ts (Nouveau fichier)
        - src/frontend/app/actions/send-external-signatory-book-action/send-external-signatory-book-action.component.html
        - src/frontend/app/actions/send-external-signatory-book-action/send-external-signatory-book-action.component.ts

    - Amélioration de l'affichage de version sur l'écran de connexion (NCH01)
        - src/frontend/app/login/login.component.ts
        - src/frontend/app/login/login.component.html

    - Amélioration de l'écran de qualification pour récupérer les données par défaut (NCH01)
        - src/frontend/app/process/process.component.ts
        - src/frontend/app/process/process.component.html
        - src/app/resource/controllers/ResController.php
        - src/app/entity/controllers/ListTemplateController.php
        - src/frontend/app/indexation/indexing-form/indexing-form.component.ts

    - Amélioration d'ESLINT (NCH01)
        - .eslintrc.js
    
    - Changement du QRCodePrefix de MAARCH_ à MEM_ (NCH01)
        - src/app/entity/controllers/EntitySeparatorController.php
        - src/app/contentManagement/controllers/MergeController.php

    - Changement du nom d'application (NCH01)
        - sql/data_fr.sql
        - src/lang/lang-fr.json
        - src/frontend/index.html
        - src/core/lang/lang-fr.php
        - apps/maarch_entreprise/lang/fr.php
        - src/core/models/CoreConfigModel.php
        - apps/maarch_entreprise/xml/config.json.default
        - src/app/entity/controllers/EntitySeparatorController.php

     - Changement du logo d'application (EME01 & NCH01)
        - src/frontend/index.html
        - src/frontend/assets/logo_only.svg
        - src/frontend/app/login/resetPassword/reset-password.component.html
        - src/frontend/app/login/forgotPassword/forgotPassword.component.html

    - Ajout d'un paramètre pour gérer le nombre max lors d'un publipostage (NCH01)
        - src/app/contact/controllers/ContactGroupController.php

    - Fix pour IOS (NCH01)
        - src/frontend/plugins/timeAgo.pipe.ts
        - src/frontend/app/list/basket-list.component.ts
        - src/frontend/app/list/basket-list.component.scss
        - src/frontend/app/list/basket-list.component.html
        - src/frontend/app/indexation/indexing-form/indexing-form.component.ts

    - Fix circuit de visa lors de l'envoi au parapheur externe (NCH01)
        - bin/signatureBook/process_mailsFromSignatoryBook.php
        - src/app/action/controllers/ExternalSignatoryBookTrait.php

    - Correction des URLS dans les pdf si le watermark est activé (NCH01)
        - controllers/WatermarkController.php
        - controllers/TcpdfFpdiCustom.php (Nouveau fichier)

    - Supprimer MAARCH dans le fichier chrono.xml par défaut (NCH01)
        - apps/maarch_entreprise/xml/chrono.xml :
            - Supprimer les balises suivantes : 
                <ELEMENT>
                    <type>text</type>
                    <value>MAARCH</value>
                </ELEMENT>
                <ELEMENT>
                    <type>text</type>
                    <value>/</value>
                </ELEMENT>

    - Rajout de la création des index lors de l'installation (NCH01)
        - sql/index_creation.sql
        - controllers/InstallerController.php

    - Rajout d'une route pour récupérer les informations d'un utilisateur (NCH01)
        - rest/index.php
        - controllers/UserController.php

    - Ajout du numéro de téléphone des contacts dans la fiche de liaison (NCH01)
        - controllers/ContactController.php
        - controllers/SummarySheetController.php

    - Fix pour l'ordre des notes dans la fiche de liaison (NCH01)
        - controllers/SummarySheetController.php

    - Script purge Nextcloud (AMO01)
        - bin/external/nextcloud/NextcloudScriptPurge.php (Nouveau fichier)
        - bin/external/nextcloud/config.xml.default (Nouveau fichier)
        - bin/external/nextcloud/nextcloud.sh (Nouveau fichier)

    - Fix notifications des bannettes désactivées (NCH01)
        - bin/notification/basket_event_stack.php

    - Fix pour afficher les templates de notes en fonction de l'entitié du user et non de la destination du courrier (NCH01)
        - controllers/NoteController.php
        - src/frontend/app/notes/notes-list.component.ts
        - src/frontend/app/list/basket-list.component.ts
        - src/frontend/app/notes/note-editor.component.ts
        - src/frontend/app/process/process.component.html
        - src/frontend/app/notes/notes-list.component.html
        - src/frontend/app/list/panel/panel-list.component.html

    - Changement des libellés Pièces Jointes (NCH01)
        - src/frontend/app/attachments/attachments-page/attachment-page.component.html
        - src/lang/lang-fr.json
            - "attachment": "Pièce jointe"                          --> "attachment": "Document"
            - "attachment_FRZ": "Gelée"                             --> "attachment_FRZ": "Gelé"
            - "signedAlt": "Signée"                                 --> "signedAlt": "Signé"
            - "attachment_SIGN": "Signée"                           --> "attachment_SIGN": "Signé"
            - "attachment_TRA": "Traitée"                           --> "attachment_TRA": "Traité"
            - "attachmentShort": "PJ"                               --> "attachmentShort": "Document"
            - "attachments": "Pièces jointes"                       --> "attachments": "PJ et réponses"
            - "noAttachment": "Aucune pièce jointe"                 --> "noAttachment": "Aucun document"
            - "signedAttachment": "Pièce jointe signée"             --> "signedAttachment": "Document signé"
            - "attachmentType": "Type de pièce jointe"              --> "attachmentType": "Type de document"
            - "addAttachment": "Ajouter une pièce jointe"           --> "addAttachment": "Ajouter un document"
            - "attachmentUpdated": "Pièce jointe modifiée"          --> "attachmentUpdated": "Document modifié"
            - "attachmentAdded": "Pièce(s) jointe(s) créée(s)"      --> "attachmentAdded": "Document(s) créé(s)"
            - "attachmentDeleted": "Pièce jointe supprimée"         --> "attachmentDeleted": "Document supprimé"
            - "attachAttachment": "Attacher une pièce jointe"       --> "attachAttachment": "Attacher un document"
            - "attachmentGenerated": "Pièces jointes générées"      --> "attachmentGenerated": "Documents générés"
            - "attachmentCreation": "Création d'une pièce jointe"   --> "attachmentCreation": "Création d'un document"

    - Fix de type d'argument pour la fonction unaccent (OBR01)
        - src/app/contact/controllers/ContactController.php

    - Ajout d'une balise pour ne pas être indexer par les moteurs de recherche (NCH01)
        - src/frontend/index.html

    - Rajout du paramètre force_admin_mail_from (OBR01)
        - src/app/email/controllers/EmailController.php

    - Fix watermark (tag 21.03.28 Maarch) (NCH01)
        - src/app/resource/controllers/ResController.php

    - Ajout d'une fenêtre pour administrer la recherche des dossiers (EME01)
        - src/frontend/service/privileges.service.ts
        - src/frontend/app/administration/administration-routing.module.ts
        - src/frontend/app/administration/administration.module.ts
        - src/frontend/app/menu/menuNav.component.html
        - sql/data_fr.sql
        - lang-fr.json
            Ajout "foldersAdministration": "Administration dossiers", 
        - src/app/folder/controllers/folderController.php
        - src/app/configuration/controllers/ConfigurationController.php
        - rest/index.php
        - src/frontend/app/process/process.component.ts
        - src/frontend/app/folder/document-list/folder-document-list.component.ts
        - src/frontend/app/folder/document-list/folder-document-list.component.html
        - src/frontend/app/administration/folders/folders-administration.component.html (nouveau fichier)
        - src/frontend/app/administration/folders/folders-administration.component.scss (nouveau fichier)
        - src/frontend/app/administration/folders/folders-administration.component.ts (nouveau fichier)
        - src/frontend/app/folder/panel/panel-folder.component.html
        - src/frontend/app/folder/panel/panel-folder.component.ts
        - src/frontend/app/folder/folder-tree.component.scss
        - src/frontend/app/folder/folder-pinned/folder-pinned.component.scss
        - src/app/folder/models/EntityFolderModelAbstract.php
        - src/app/parameter/controllers/ParameterController.php

    - Fix sur l'affichage du nom du dossier (emplacement fixe) d'un courrier (EME01)
        - src/frontend/app/search/result-list/search-result-list.component.html
        - src/frontend/app/search/result-list/search-result-list.component.ts
        - src/app/search/controllers/SearchController.php

    - Remplacement de l'image de fond par défaut (NCH01)
        - src/frontend/assets/bodylogin.jpg

    - Amélioration de l'écran d'impression en masse (NCH01)
        - sql/data_fr.sql
        - src/app/parameter/controllers/ParameterController.php
        - src/app/resource/controllers/FolderPrintController.php
        - src/frontend/app/printedFolder/printed-folder-modal.component.ts
        - src/frontend/app/printedFolder/printed-folder-modal.component.html
        - lang/lang-fr.json :   
            - Ajout "attachments_signed"

    - Fix création des scripts de notifications (NCH01)
        - src/app/notification/controllers/NotificationScheduleController.php

    - Fix notification USER QUOTA (NCH01)
        - sql/data_fr.sql
    
    - Module e-Citiz (NCH01)
        - bin/external/ecitiz/* (Nouveaux fichiers)
        - src/app/resource/controllers/ResController.php
        - src/app/attachment/controllers/AttachmentController.php

    - Fix de l'action de confirmation si l'éditeur de note n'a pas chargé (NCH01)
        - src/frontend/app/actions/confirm-action/confirm-action.component.ts

    - Réinitialisation des séquences de numéro de chrono (OBR01)
        - sql/delete_all_ressources.sql

    - FIX si contact associé au courrier est de type user dans la fiche détaillée (EME01)
        - src/frontend/app/administration/contact/modal/contact-modal.component.ts

    - Amélioration des webservices utilisateurs (NCH01)
        - src/app/user/controllers/UserController.php

    - Fix Iparapheur sans certificat (PYB01)
        - src/app/external/externalSignatoryBook/controllers/IParapheurController.php

    - Changer les input en textarea pour les champs custom des contacts (NCH01)
        - src/frontend/app/administration/contact/page/form/contacts-form.component.html

    - Ajout d'un paramètre pour gérer le nombre max de contacts afficher dans l'écran de dédoublonnage (NCH01)
        - src/app/contact/controllers/ContactController.php

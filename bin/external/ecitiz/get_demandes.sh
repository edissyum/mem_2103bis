#!/bin/sh
cd /var/www/html/maarch_courrier/bin/external/ecitiz/ || exit
php EcitizScript.php --customId maarch --action get_demandes

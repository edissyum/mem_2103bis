#!/bin/sh
php ./migrateSendmail.php
php ./migrateSendmail_2.php
php ./migrateExport.php
php ./migrateFeature.php
php ./migrateNewNature.php
php ./refactorPriorities.php
php ./refactorRemoteSignatoryBooks.php

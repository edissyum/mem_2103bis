<?php

require '../../vendor/autoload.php';
$currentDir = getcwd();

chdir('../..');

$nonReadableFiles = [];
$customs =  scandir('custom');

foreach ($customs as $custom) {
    $resOK = [];
    $resKO = [];
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();

    $db = new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $query = "SELECT r2.res_id as convert_res_id from res_view_attachments r LEFT JOIN res_view_attachments r2 ON REGEXP_REPLACE(r.filename, '\.(.)*$', '') = REGEXP_REPLACE(r2.filename, '\.(.)*$', '') LEFT JOIN docservers d ON d.docserver_id = r2.docserver_id WHERE r.status in ('DEL', 'OBS', 'TMP') AND r.attachment_type <> 'converted_pdf' AND r2.attachment_type = 'converted_pdf' AND r.res_id <> 0";
    $stmt = $db->query($query, []);
    echo "Suppression de {$stmt->rowCount()} PJ de type 'converted_pdf' des PJ en status 'TMP', 'OBS', 'DEL'...\n";
    if ($stmt->rowCount() > 0) {
        $convertedAttachTodel = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
        $query = "SELECT r2.res_id as convert_res_id from res_view_attachments r LEFT JOIN res_view_attachments r2 ON REGEXP_REPLACE(r.filename, '\.(.)*$', '') = REGEXP_REPLACE(r2.filename, '\.(.)*$', '') LEFT JOIN docservers d ON d.docserver_id = r2.docserver_id WHERE r.status in ('DEL', 'OBS', 'TMP') AND r.attachment_type <> 'converted_pdf' AND r2.attachment_type = 'converted_pdf' AND r.res_id_version <> 0";
        $stmt = $db->query($query, []);
        $convertedAttachVersionTodel = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $convertedAttachTodelFull = array_merge($convertedAttachTodel, $convertedAttachVersionTodel);
        $convertedAttachTodelList = "'".implode("','", $convertedAttachTodelFull)."'";
        $query = "Delete FROM res_attachments WHERE res_id IN ({$convertedAttachTodelList})";
        $db->query($query, []);
    }
    $output[] = "{$stmt->rowCount()} PJ de type 'converted_pdf' des PJ en status 'TMP', 'OBS', 'DEL' supprimé\n";

    $query = "SELECT path_template FROM docservers WHERE docserver_id = 'CONVERT_ATTACH'";
    $stmt = $db->query($query, []);
    $docserverAttach = $stmt->fetchObject();
    $docserverAttachPath = str_replace('#', '/', $docserverAttach->path_template);
    if (empty($docserverAttach)) {
        echo "Le docserver CONVERT_ATTACH n'existe pas ! \n";
        exit();
    } else {
        echo "Nouveau répertoire pièces jointes converties : {$docserverAttachPath}\n\n";
    }

    $query = "SELECT r.res_id as real_res_id, r2.res_id as convert_res_id, d.path_template as convert_path_template, r2.path as convert_path, r2.filename as convert_filename, r2.fingerprint as convert_fingerprint from res_view_attachments r LEFT JOIN res_view_attachments r2 ON REGEXP_REPLACE(r.filename, '\.(.)*$', '') = REGEXP_REPLACE(r2.filename, '\.(.)*$', '') LEFT JOIN docservers d ON d.docserver_id = r2.docserver_id WHERE r.status not in ('DEL', 'OBS', 'TMP') AND r2.status not in ('DEL', 'OBS', 'TMP') AND r.attachment_type <> 'converted_pdf' AND r2.attachment_type = 'converted_pdf' AND r.res_id <> 0";
    $stmt = $db->query($query, []);
    echo "{$stmt->rowCount()} Pièce(s) jointe(s) de type 'converted_pdf' trouvée :\n\n";
    if ($stmt->rowCount() > 0) {
        while ($convertedAttachment = $stmt->fetchObject()) {
            $pathFile = str_replace('#', '/', $docserverAttachPath.$convertedAttachment->convert_path);
            $fullFilename = str_replace('#', '/', $convertedAttachment->convert_path_template.$convertedAttachment->convert_path.$convertedAttachment->convert_filename);
            $newFullFilename = str_replace('#', '/', $pathFile.$convertedAttachment->convert_filename);
            if (file_exists($fullFilename)) {
                if (!is_dir($pathFile)) {
                    echo "Création du dossier {$pathFile}...\n";
                    if (!mkdir($pathFile, 0777, true)) {
                        die('Echec lors de la création des répertoires...');
                    }
                }
                echo "Copie du document : {$fullFilename} => {$newFullFilename}...\n";
                if (!copy($fullFilename, $newFullFilename)) {
                    echo "ECHOUÉE!\n";
                    $resKO[] = "RES_ID : ".$convertedAttachment->convert_res_id . " (Copie du document : {$fullFilename} => {$newFullFilename} FAILED)";
                } else {
                    echo "OK!\n";
                    echo "Insertion dans la table adr_attachments...\n";
              
                    $query = "DELETE FROM adr_attachments WHERE res_id = ? and type = 'PDF'";
                    $db->query($query, [$convertedAttachment->real_res_id]);
                    $query = "INSERT INTO adr_attachments(res_id, type, docserver_id, path, filename, fingerprint) VALUES (?, ?, ?, ?, ?, ?)";
                    $tmp = $db->query($query, [$convertedAttachment->real_res_id, 'PDF', 'CONVERT_ATTACH', $convertedAttachment->convert_path, $convertedAttachment->convert_filename, $convertedAttachment->convert_fingerprint]);
                    if ($tmp) {
                        $resOK[] = $convertedAttachment->convert_res_id;
                    }
                }
            } else {
                $resKO[] = "RES_ID : ".$convertedAttachment->convert_res_id . " ({$fullFilename} non trouvé)";
                echo "Document : {$fullFilename} non trouvé\n";
            }
        }
    }
    
    //PJ VERSIONS
    $query = "SELECT path_template FROM docservers WHERE docserver_id = 'CONVERT_ATTACH_VERSION'";
    $stmt = $db->query($query, []);
    $docserverAttach = $stmt->fetchObject();
    $docserverAttachPath = str_replace('#', '/', $docserverAttach->path_template);
    if (empty($docserverAttach)) {
        echo "Le docserver CONVERT_ATTACH_VERSION n'existe pas !\n";
        exit();
    } else {
        echo "Nouveau répertoire pièces jointes versionnées converties : {$docserverAttachPath}\n\n";
    }

    $query = "SELECT r.res_id_version as real_res_id, r2.res_id as convert_res_id, d.path_template as convert_path_template, r2.path as convert_path, r2.filename as convert_filename, r2.fingerprint as convert_fingerprint from res_view_attachments r LEFT JOIN res_view_attachments r2 ON REGEXP_REPLACE(r.filename, '\.(.)*$', '') = REGEXP_REPLACE(r2.filename, '\.(.)*$', '') LEFT JOIN docservers d ON d.docserver_id = r2.docserver_id WHERE r.status not in ('DEL', 'OBS', 'TMP') AND r2.status not in ('DEL', 'OBS', 'TMP') AND r.attachment_type <> 'converted_pdf' AND r2.attachment_type = 'converted_pdf' AND r.res_id_version <> 0";
    $stmt = $db->query($query, []);
    echo "{$stmt->rowCount()} Pièce(s) jointe(s) versionnée(s) de type 'converted_pdf' trouvée :\n\n";
    if ($stmt->rowCount() > 0) {
        while ($convertedAttachment = $stmt->fetchObject()) {
            $pathFile = str_replace('#', '/', $docserverAttachPath.$convertedAttachment->convert_path);
            $fullFilename = str_replace('#', '/', $convertedAttachment->convert_path_template.$convertedAttachment->convert_path.$convertedAttachment->convert_filename);
            $newFullFilename = str_replace('#', '/', $pathFile.$convertedAttachment->convert_filename);
            if (file_exists($fullFilename)) {
                if (!is_dir($pathFile)) {
                    echo "Création du dossier {$pathFile}...\n";
                    if (!mkdir($pathFile, 0777, true)) {
                        die('Echec lors de la création des répertoires...');
                    }
                }
                echo "Copie du document : {$fullFilename} => {$newFullFilename}...\n";
                if (!copy($fullFilename, $newFullFilename)) {
                    echo "ECHOUÉE!\n";
                    $resKO[] = "RES_ID : ".$convertedAttachment->convert_res_id . " (Copie du document : {$fullFilename} => {$newFullFilename} FAILED)";
                } else {
                    echo "OK!\n";
                    echo "Insertion dans la table adr_attachments_version...\n";
              
                    $query = "DELETE FROM adr_attachments_version WHERE res_id = ? and type = 'PDF'";
                    $db->query($query, [$convertedAttachment->real_res_id]);
                    $query = "INSERT INTO adr_attachments_version(res_id, type, docserver_id, path, filename, fingerprint) VALUES (?, ?, ?, ?, ?, ?)";
                    $tmp = $db->query($query, [$convertedAttachment->real_res_id, 'PDF', 'CONVERT_ATTACH_VERSION', $convertedAttachment->convert_path, $convertedAttachment->convert_filename, $convertedAttachment->convert_fingerprint]);
                    if ($tmp) {
                        $resOK[] = $convertedAttachment->convert_res_id;
                    }
                }
            } else {
                $resKO[] = "RES_ID : ".$convertedAttachment->convert_res_id . " ({$fullFilename} non trouvé)";
                echo "Document : {$fullFilename} non trouvé\n";
            }
        }
    }

    if (!empty($resOK)) {
        $resOKList = "'".implode("','", $resOK)."'";
        echo "Suppression des PJ {$resOKList} de type 'converted_pdf'...\n";
        $query = "Delete FROM res_attachments WHERE res_id IN ({$resOKList})";
        $db->query($query, []);
    }
    $nbresOK = count($resOK);
    $output[] = "{$nbresOK} PJ de type 'converted_pdf' migré.\n";

    $resKOList = implode("\n", $resKO);
    $nbResKO = count($resKO);
    $output[] = "{$nbResKO} PJ de type 'converted_pdf' NON migré :\n";
    $output[] = "$resKOList\n\n";
    echo "\n\nLog {$currentDir}/result.log généré\n";
    file_put_contents($currentDir.'/result.log', implode('', $output));
}

foreach ($nonReadableFiles as $file) {
    printf("The file %s it is not readable or not writable.\n", $file);
}

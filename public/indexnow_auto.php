<?php
// FONCTIONNEMENT
// https://indexnow-php.onrender.com/indexnow_auto.php pour avoir la liste
// https://indexnow-php.onrender.com/indexnow_auto.php?confirm=1 pour envoyer
// Mettre nouvelle url en dur dans le code + pousser sur render avec un manual deploy
// https://indexnow-php.onrender.com/indexnow_auto.php

// --- CONFIG ---
$confirm = isset($_GET['confirm']) ? true : false; // Nouveau : détermine si on a confirmé l'envoi
$indexNowKey  = 'da57fcb3046f4297b99ed0b843e41393'; // Clé IndexNow
$keyLocation  = 'https://www.2dolist.fr/da57fcb3046f4297b99ed0b843e41393.txt';

// (Ci-dessous, tout le code relatif au sitemap est COMMENTÉ, pour le garder mais ne pas l'exécuter)
/*
$sitemapUrl   = 'https://a359590.sitemaphosting7.com/4443587/sitemap_4443587.xml';
$lastCheckFile= __DIR__ . '/last_check.txt'; // Fichier pour stocker la dernière date de vérification

// --- RÉCUPÈRE ET PARSE LE SITEMAP ---
$sitemapContent = @file_get_contents($sitemapUrl);
if (!$sitemapContent) {
    exit("Impossible de récupérer le sitemap.\n");
}

$xml = @simplexml_load_string($sitemapContent);
if (!$xml) {
    exit("Format de sitemap invalide.\n");
}

// --- LIT LA DERNIÈRE DATE DE VÉRIFICATION ---
$lastCheckDate = 0; // 0 => tout envoyer si aucune trace
if (file_exists($lastCheckFile)) {
    $lastCheckDate = (int) file_get_contents($lastCheckFile);
}
$currentTimestamp = time(); // Date/heure actuelle pour mettre à jour après traitement

// --- PRÉPARE LES URLS À ENVOYER (NOUVELLES/MODIFIÉES) ---
$urlList = [];
foreach ($xml->url as $urlEntry) {
    $loc     = (string) $urlEntry->loc;      // URL
    $lastmod = (string) $urlEntry->lastmod;  // ex: 2022-12-31T10:00:00+00:00
    
    // Convertir la date du sitemap en timestamp
    $entryTimestamp = strtotime($lastmod);
    
    // Si la date de modif est > $lastCheckDate => URL considérée comme nouvelle/modifiée
    if ($entryTimestamp > $lastCheckDate) {
        $urlList[] = $loc;
    }
}

// Si aucune URL à envoyer, on met à jour la date et on sort
if (empty($urlList)) {
    file_put_contents($lastCheckFile, $currentTimestamp);
    exit("Aucune nouvelle URL à indexer.\n");
}
*/

// --- LISTE D’URLS À ENVOYER EN DUR ---
$urlList = [
    // Mets ici les URLs que tu veux envoyer manuellement
    'https://www.2dolist.fr/region/auvergne-rhone-alpes/departement/isere/categorie/avion',
    'https://www.2dolist.fr/region/bourgogne-franche-comte/departement/jura/categorie/avion',
    'https://www.2dolist.fr/region/centre-val-de-loire/categorie/avion',
    'https://www.2dolist.fr/region/centre-val-de-loire/departement/indre-et-loire/categorie/avion',
    'https://www.2dolist.fr/region/hauts-de-france/departement/pas-de-calais/categorie/avion', 
     'https://www.2dolist.fr/region/nouvelle-aquitaine/departement/dordogne/categorie/avion',
     'https://www.2dolist.fr/region/nouvelle-aquitaine/departement/vienne/categorie/avion',
     'https://www.2dolist.fr/region/nouvelle-aquitaine/departement/charente-maritime/categorie/avion',
     'https://www.2dolist.fr/region/occitanie/categorie/avion',
     'https://www.2dolist.fr/region/occitanie/departement/lot/categorie/avion',
     'https://www.2dolist.fr/region/occitanie/departement/herault/categorie/avion',
     'https://www.2dolist.fr/region/occitanie/departement/tarn-et-garonne/categorie/avion',
    'https://www.2dolist.fr/region/pays-de-la-loire/departement/loire-atlantique/categorie/avion',
     'https://www.2dolist.fr/region/provence-alpes-cote-dazur/departement/alpes-de-haute-provence/categorie/avion',	
     'https://www.2dolist.fr/region/provence-alpes-cote-dazur/departement/hautes-alpes/categorie/avion',
    
    ];

// --- AFFICHE LES URLS POUR LE LOG ---
echo "URLs détectées (non encore envoyées) :\n";
foreach ($urlList as $url) {
    echo " - $url\n";
}

// Nouveau : si pas de confirmation, on s'arrête avant l'envoi
if (!$confirm) {
    echo "\n(Aucun envoi effectué : ajoute ?confirm=1 à l'URL pour lancer l'envoi.)\n";
    exit;
}

// --- ENVOI À INDEXNOW (POST JSON) ---
$data = [
    'host'        => parse_url($urlList[0], PHP_URL_HOST), // Hôte de la 1ère URL
    'key'         => $indexNowKey,
    'keyLocation' => $keyLocation,
    'urlList'     => $urlList
];

$ch = curl_init('https://api.indexnow.org/indexnow');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// --- AFFICHE LA RÉPONSE POUR CONTRÔLE ---
echo "\nRéponse IndexNow : $response\n";

?>

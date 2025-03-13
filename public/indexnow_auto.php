<?php

// --- CONFIG ---
$sitemapUrl   = 'https://a359590.sitemaphosting7.com/4443587/sitemap_4443587.xml';
$indexNowKey  = '42db6e88b74940d1a004556d454cffcf'; // Clé IndexNow
$keyLocation  = 'https://www.2dolist.fr/42db6e88b74940d1a004556d454cffcf.txt';
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
// Force à tout envoyer (on supprime last_check.txt s'il existe)
if (file_exists($lastCheckFile)) {
    unlink($lastCheckFile);
}
$lastCheckDate = 0; // Donc on enverra toutes les URLs
 // Date/heure actuelle pour mettre à jour après traitement

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

// --- AFFICHE LES URLS POUR LE LOG ---
echo "URLs détectées pour IndexNow :\n";
foreach ($urlList as $url) {
    echo " - $url\n";
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

// --- MET À JOUR LE FICHIER DE DERNIÈRE VÉRIFICATION ---
file_put_contents($lastCheckFile, $currentTimestamp);

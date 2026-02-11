<?php
if (isset($_GET['help'])) {
  echo <<<HELP
───────────────────────────────
 FONCTIONNEMENT DU SCRIPT INDEXNOW
───────────────────────────────

UTILISATION NORMALE
  ▸ /indexnow-multi.php?site=fr
       → Liste les URLs modifiées (.fr)
  ▸ /indexnow-multi.php?site=fr&confirm=1
       → Envoie réellement à IndexNow (.fr)
  ▸ /indexnow-multi.php?site=com
       → Liste les URLs modifiées (.com)
  ▸ /indexnow-multi.php?site=com&confirm=1
       → Envoie réellement à IndexNow (.com)

OPTIONS SUPPLÉMENTAIRES
  ▸ &include_static=1
       → Ajoute aussi les pages statiques (ex: /, /gift, /faq)
  ▸ &manual_url=https://www.2dolistgo.com/gift
       → Envoie **uniquement cette URL** manuellement à IndexNow
         (indépendamment du sitemap)
  ▸ &help=1
       → Affiche cette aide

EXEMPLES
  ▸ /indexnow-multi.php?site=com&include_static=1&confirm=1
  ▸ /indexnow-multi.php?site=com&manual_url=https://www.2dolistgo.com/gift
───────────────────────────────
HELP;
  exit;
}

// --- CONFIG SITES ---
$sites = [
  'fr' => [
    'indexNowKey' => 'da57fcb3046f4297b99ed0b843e41393', // <- clé .fr (OK)
    'keyLocation' => 'https://www.2dolist.fr/da57fcb3046f4297b99ed0b843e41393.txt',
    'sitemapUrl'  => 'https://www.2dolist.fr/sitemap-test.xml',
    'staticPages' => [
      'https://www.2dolist.fr/',
      'https://www.2dolist.fr/cadeaux',
      'https://www.2dolist.fr/plan-du-site',
      'https://www.2dolist.fr/jet-prive',
      'https://www.2dolist.fr/faq',
    ],
  ],
  'com' => [
    'indexNowKey' => '23e4792a32114c79b29e35ad3f551060', // ex: 'abc123...'
    'keyLocation' => 'https://www.2dolistgo.com/23e4792a32114c79b29e35ad3f551060.txt',
    'sitemapUrl'  => 'https://www.2dolistgo.com/sitemap.xml',
    'staticPages' => [
      'https://www.2dolistgo.com/',
      'https://www.2dolistgo.com/gift',
      'https://www.2dolistgo.com/sitemap',
      'https://www.2dolistgo.com/private-jet',
    ],
  ],
  'com_ca' => [
    'indexNowKey' => '23e4792a32114c79b29e35ad3f551060', // même clé que .com
    'keyLocation' => 'https://www.2dolistgo.com/23e4792a32114c79b29e35ad3f551060.txt',
    'sitemapUrl'  => 'https://www.2dolistgo.com/ca/sitemap.xml',
    'staticPages' => [
      'https://www.2dolistgo.com/ca/',
      'https://www.2dolistgo.com/ca/flight-tickets',
      'https://www.2dolistgo.com/ca/sitemap',
    ],
  ],
];

// --- PARAMÈTRES URL ---
$site          = isset($_GET['site']) ? strtolower(trim($_GET['site'])) : 'fr';
$confirm       = isset($_GET['confirm']);
$includeStatic = isset($_GET['include_static']);

// --- VALIDATION SITE ---
if (!isset($sites[$site])) {
  exit("Site invalide. Utilise ?site=fr ou ?site=com ou ?site=com_ca\n");
}

$config       = $sites[$site];
$indexNowKey  = $config['indexNowKey'];
$keyLocation  = $config['keyLocation'];
$sitemapUrl   = $config['sitemapUrl'];
$staticPages  = $config['staticPages'];
$lastCheckFile= __DIR__ . "/last_check_{$site}.txt"; // suivi séparé par site

if (empty($indexNowKey) || strpos($keyLocation, 'YOUR_COM_INDEXNOW_KEY') !== false) {
  if ($site === 'com') {
    exit("Configure d'abord la clé IndexNow et le key file pour le .com.\n");
  }
}

// --- MODE MANUEL (envoi URL indépendante) ---
if (isset($_GET['manual_url'])) {
  $manualUrl = trim($_GET['manual_url']);

  if (!filter_var($manualUrl, FILTER_VALIDATE_URL)) {
    exit("URL manuelle invalide.\n");
  }

  echo "Mode manuel activé : envoi de l'URL $manualUrl\n";

  $data = [
    'host'        => parse_url($manualUrl, PHP_URL_HOST),
    'key'         => $indexNowKey,
    'keyLocation' => $keyLocation,
    'urlList'     => [$manualUrl],
  ];

  $ch = curl_init('https://api.indexnow.org/indexnow');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  echo "\nRéponse IndexNow : $response\n";
  exit; // on stoppe ici: pas de flux auto, pas de MAJ last_check
}

// --- RÉCUPÈRE ET PARSE LE SITEMAP ---
$sitemapContent = @file_get_contents($sitemapUrl);
if (!$sitemapContent) {
  exit("Impossible de récupérer le sitemap pour {$site} : $sitemapUrl\n");
}

$xml = @simplexml_load_string($sitemapContent);
if (!$xml) {
  exit("Format de sitemap invalide pour {$site}.\n");
}

// --- LIT LA DERNIÈRE DATE DE VÉRIFICATION ---
$lastCheckDate = 0;
if (file_exists($lastCheckFile)) {
  $lastCheckDate = (int) file_get_contents($lastCheckFile);
}
$currentTimestamp = time();
$maxEntryTimestamp = 0;

// --- PRÉPARE LES URLS À ENVOYER ---
$urlList = [];
foreach ($xml->url as $urlEntry) {
  $loc     = (string) $urlEntry->loc;
  $lastmod = (string) $urlEntry->lastmod;
  $entryTimestamp = strtotime($lastmod);

  if ($entryTimestamp > $maxEntryTimestamp) {
    $maxEntryTimestamp = $entryTimestamp;
  }
  if ($entryTimestamp > $lastCheckDate) {
    $urlList[] = $loc;
  }
}

// Exclure les pages statiques sauf si include_static=1
if (!$includeStatic) {
  $urlList = array_values(array_filter(
    $urlList,
    static fn($u) => !in_array($u, $staticPages, true)
  ));
}

// Si aucune URL à envoyer, on met à jour la date et on sort
if (empty($urlList)) {
  file_put_contents($lastCheckFile, $currentTimestamp);
  exit("[$site] Aucune nouvelle URL à indexer.\n");
}

// --- LOG ---
echo "Site: {$site}\n";
echo "URLs détectées (non encore envoyées) :\n";
foreach ($urlList as $url) {
  echo " - $url\n";
}

// Sans confirmation, on s'arrête ici
if (!$confirm) {
  echo "\n(Aucun envoi effectué : ajoute &confirm=1 à l'URL pour lancer l'envoi.)\n";
  exit;
}

// --- ENVOI À INDEXNOW ---
$data = [
  'host'        => parse_url($urlList[0], PHP_URL_HOST),
  'key'         => $indexNowKey,
  'keyLocation' => $keyLocation,
  'urlList'     => $urlList,
];

$ch = curl_init('https://api.indexnow.org/indexnow');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nRéponse IndexNow : $response\n";

// --- Mise à jour du checkpoint si succès ---
if (in_array($httpCode, [200, 202], true)) {
  $newCheckpoint = $maxEntryTimestamp > 0 ? $maxEntryTimestamp : $currentTimestamp;
  file_put_contents($lastCheckFile, $newCheckpoint);
  echo "\n(last_check_{$site}.txt mis à jour à $newCheckpoint)\n";
} else {
  echo "\nEnvoi non confirmé (HTTP $httpCode) : checkpoint non mis à jour.\n";
}

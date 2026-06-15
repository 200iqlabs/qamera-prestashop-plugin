<?php
/**
 * Dev helper (NOT shipped): generate the JS i18n payload + missing translation
 * entries for product-tab.tpl. Routes every JS-side string through the PS
 * translation domain `product-tab` (the template filename). Run with PHP CLI.
 */

// key => [PL source, EN].
$d = [
    'addingPackshot'          => ['Dodawanie jako packshot…', 'Adding as packshot…'],
    'addingImage'             => ['Dodawanie jako zdjęcie produktu…', 'Adding as product image…'],
    'addFailed'               => ['Nie udało się dodać zdjęcia.', 'Could not add the image.'],
    'addedPackshot'           => ['Dodano jako packshot.', 'Added as packshot.'],
    'addedImage'              => ['Dodano jako zdjęcie produktu — możesz wygenerować packshot poniżej.', 'Added as a product image — you can generate a packshot below.'],
    'netAdd'                  => ['Błąd sieci podczas dodawania.', 'Network error while adding.'],
    'badgeSource'             => ['źródło', 'source'],
    'badgePackshotLower'      => ['packshot', 'packshot'],
    'labelPhoto'              => ['Zdjęcie', 'Photo'],
    'genPackshot'             => ['Generuj packshot', 'Generate packshot'],
    'rolePackshot'            => ['Packshot', 'Packshot'],
    'accepted'                => ['Zatwierdzony', 'Approved'],
    'genSession'              => ['Generuj sesję', 'Generate session'],
    'delete'                  => ['Usuń', 'Delete'],
    'prepSource'              => ['Przygotowanie zdjęcia źródłowego…', 'Preparing the source image…'],
    'analyzing'              => ['Analiza zdjęcia źródłowego… (próba %s)', 'Analyzing the source image… (attempt %s)'],
    'genStartFailed'          => ['Nie udało się rozpocząć generacji.', 'Could not start generation.'],
    'genPackshotBusy'         => ['Generowanie packshotu… (to może potrwać do kilku minut)', 'Generating the packshot… (this can take a few minutes)'],
    'netUpload'               => ['Błąd sieci podczas wysyłki. Spróbuj ponownie.', 'Network error during upload. Try again.'],
    'jobStatusFailed'         => ['Nie udało się odczytać statusu zadania.', 'Could not read the job status.'],
    'packshotReady'           => ['Packshot gotowy.', 'Packshot ready.'],
    'genFailedWith'           => ['Generacja nie powiodła się: %s', 'Generation failed: %s'],
    'genFailed'               => ['Generacja nie powiodła się.', 'Generation failed.'],
    'genCancelled'            => ['Generacja anulowana.', 'Generation cancelled.'],
    'jobEndedState'           => ['Zadanie zakończone w stanie: %s', 'Job ended in state: %s'],
    'pollTimeout5'            => ['Przekroczono limit oczekiwania (5 min). Odśwież stronę, aby sprawdzić wynik.', 'Wait time exceeded (5 min). Refresh the page to check the result.'],
    'statusCheckErr'          => ['Błąd podczas sprawdzania statusu.', 'Error while checking status.'],
    'accept'                  => ['Zatwierdź', 'Approve'],
    'reject'                  => ['Odrzuć', 'Reject'],
    'labelSessions'           => ['Sesje', 'Sessions'],
    'sessionAssign'           => ['Zlecanie sesji…', 'Submitting the session…'],
    'sessionStartFailed'      => ['Nie udało się zlecić sesji.', 'Could not submit the session.'],
    'sessionBusy'             => ['Generowanie sesji… (to może potrwać do kilku minut)', 'Generating the session… (this can take a few minutes)'],
    'netSession'              => ['Błąd sieci podczas zlecania sesji.', 'Network error while submitting the session.'],
    'pollTimeoutShort'        => ['Przekroczono limit oczekiwania.', 'Wait time exceeded.'],
    'stateColon'              => ['Stan: %s', 'State: %s'],
    'failedShort'             => ['Nie powiodło się.', 'Failed.'],
    'publishing'              => ['Publikowanie zdjęcia w galerii…', 'Publishing the image to the gallery…'],
    'publishFailed'           => ['Nie udało się opublikować zdjęcia.', 'Could not publish the image.'],
    'alreadyInGallery'        => ['Zdjęcie było już w galerii produktu.', 'The image was already in the product gallery.'],
    'publishedOk'             => ['Zatwierdzono — dodano do galerii produktu.', 'Approved — added to the product gallery.'],
    'netPublish'              => ['Błąd sieci podczas publikacji.', 'Network error during publishing.'],
    'voteSaveFailed'          => ['Nie udało się zapisać oceny.', 'Could not save the vote.'],
    'packshotRejectedDeleted' => ['Packshot odrzucony i usunięty.', 'Packshot rejected and deleted.'],
    'packshotRejected'        => ['Packshot odrzucony.', 'Packshot rejected.'],
    'imageRejected'           => ['Zdjęcie odrzucone.', 'Image rejected.'],
    'packshotAccepted'        => ['Packshot zatwierdzony.', 'Packshot approved.'],
    'rejected'                => ['Odrzucono.', 'Rejected.'],
    'netVote'                 => ['Błąd sieci podczas zapisu oceny.', 'Network error while saving the vote.'],
    'noPackshotRef'           => ['Brak identyfikatora packshota do usunięcia.', 'No packshot identifier to delete.'],
    'confirmDelete'           => ['Usunąć ten packshot z katalogu Qamera AI? Tej operacji nie można cofnąć.', 'Delete this packshot from the Qamera AI catalog? This cannot be undone.'],
    'deletePackshotFailed'    => ['Nie udało się usunąć packshota.', 'Could not delete the packshot.'],
    'packshotDeleted'         => ['Packshot usunięty.', 'Packshot deleted.'],
    'netDelete'               => ['Błąd sieci podczas usuwania.', 'Network error while deleting.'],
    'badgeRejected'           => ['Odrzucony', 'Rejected'],
    'badgePending'            => ['Oczekuje', 'Pending'],
];

$plFile = __DIR__ . '/../qameraai/translations/pl.php';
$enFile = __DIR__ . '/../qameraai/translations/en.php';
$plSrc = file_get_contents($plFile);
$enSrc = file_get_contents($enFile);

$jsonLines = [];
$missingPl = [];
$missingEn = [];
$seenMd5 = [];

foreach ($d as $key => $pair) {
    list($pl, $en) = $pair;
    $md5 = md5($pl);
    $arrKey = "product-tab_" . $md5;
    // JSON block line: Smarty {l} routed through product-tab domain.
    $plEsc = str_replace("'", "\\'", $pl);
    $jsonLines[] = '        "' . $key . '": "{l s=\'' . $plEsc . '\' mod=\'qameraai\' js=1}"';

    if (isset($seenMd5[$md5])) {
        continue; // duplicate PL string within dict (same md5) — one entry suffices
    }
    $seenMd5[$md5] = true;

    if (strpos($plSrc, $arrKey) === false) {
        $missingPl[] = "\$_MODULE['<{qameraai}prestashop>" . $arrKey . "'] = '" . str_replace("'", "\\'", $pl) . "';";
    }
    if (strpos($enSrc, $arrKey) === false) {
        $missingEn[] = "\$_MODULE['<{qameraai}prestashop>" . $arrKey . "'] = '" . str_replace("'", "\\'", $en) . "';";
    }
}

if (in_array('--write', $argv, true)) {
    if ($missingPl) {
        file_put_contents($plFile, "\n// JS-side strings (product-tab.js) — i18n payload.\n" . implode("\n", $missingPl) . "\n", FILE_APPEND);
    }
    if ($missingEn) {
        file_put_contents($enFile, "\n// JS-side strings (product-tab.js) — i18n payload.\n" . implode("\n", $missingEn) . "\n", FILE_APPEND);
    }
    echo "appended pl=" . count($missingPl) . " en=" . count($missingEn) . "\n";
    return;
}

echo "===== JSON BLOCK (paste into product-tab.tpl) =====\n";
echo implode(",\n", $jsonLines) . "\n";
echo "\n===== MISSING pl.php (" . count($missingPl) . ") =====\n";
echo implode("\n", $missingPl) . "\n";
echo "\n===== MISSING en.php (" . count($missingEn) . ") =====\n";
echo implode("\n", $missingEn) . "\n";

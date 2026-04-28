<?php
// Redirect to new tutorial page
// Maps old help.php parameters to new tutorial.php

$tutorialMap = [
    'quickstart' => 'quickstart',
    'manual' => 'contacts',
    'modul1' => 'contacts',
    'modul2' => 'compose',
    'compose' => 'compose',
    'templates' => 'templates',
    'ai-matching' => 'ai-matching',
    'faq' => 'quickstart',
    'troubleshoot' => 'quickstart'
];

$doc = $_GET['doc'] ?? 'quickstart';
$section = $_GET['section'] ?? '';

// Determine which tutorial to show
if ($section === 'faq') {
    $target = 'quickstart';
} elseif ($section === 'troubleshoot') {
    $target = 'quickstart';
} else {
    $target = $tutorialMap[$doc] ?? 'quickstart';
}

header("Location: tutorial.php?t=$target");
exit;
?>

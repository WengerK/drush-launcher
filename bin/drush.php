<?php

use DrupalFinder\DrupalFinder;
use Webmozart\PathUtil\Path;
use Humbug\SelfUpdate\Updater;

set_time_limit(0);

$autoloaders = [
  __DIR__ . '/../../../autoload.php',
  __DIR__ . '/../vendor/autoload.php'
];

foreach ($autoloaders as $file) {
  if (file_exists($file)) {
    $autoloader = $file;
    break;
  }
}

if (isset($autoloader)) {
  require_once $autoloader;
}
else {
  echo 'You must set up the project dependencies using `composer install`' . PHP_EOL;
  exit(1);
}

$DRUSH_LAUNCHER_VERSION = '@git-version@';

$ROOT = FALSE;
$DEBUG = FALSE;
$VAR = FALSE;
$VERSION = FALSE;
$DRUSH_VERSION = 8; // Gets ovewritten later if Drush9 is detected.
$SELF_UPDATE = FALSE;

foreach ($_SERVER['argv'] as $arg) {
  // If a variable to set was indicated on the
  // previous iteration, then set the value of
  // the named variable (e.g. "ROOT") to "$arg".
  if ($VAR) {
    $$VAR = "$arg";
    $VAR = FALSE;
  }
  else {
    switch ($arg) {
      case "-r":
        $VAR = "ROOT";
        break;
      case "--debug":
        $DEBUG = TRUE;
        break;
      case "--version":
        $VERSION = TRUE;
        break;
      case "self-update":
        $SELF_UPDATE = TRUE;
        break;
    }
    if (substr($arg, 0, 7) == "--root=") {
      $ROOT = substr($arg, 7);
    }
  }
}

if ($ROOT === FALSE) {
  $ROOT = getcwd();
}
else {
  $ROOT = Path::canonicalize($ROOT);
}

$drupalFinder = new DrupalFinder();

if ($VERSION || $DEBUG || $SELF_UPDATE) {
  echo "Drush Launcher Version: {$DRUSH_LAUNCHER_VERSION}" .  PHP_EOL;
}

if ($SELF_UPDATE) {
  if ($DRUSH_LAUNCHER_VERSION === '@' . 'git-version' . '@') {
    echo "Automatic update not supported.\n";
    exit(1);
  }
  $updater = new Updater(null, false);
  $updater->setStrategy(Updater::STRATEGY_GITHUB);
  $updater->getStrategy()->setPackageName('drush/drush-launcher');
  $updater->getStrategy()->setPharName('drush.phar');
  $updater->getStrategy()->setCurrentLocalVersion($DRUSH_LAUNCHER_VERSION);
  try {
    $result = $updater->update();
    echo $result ? "Updated!\n" : "No update needed!\n";
    exit(0);
  } catch (\Exception $e) {
    echo "Automatic update failed, please download the latest version from https://github.com/drush-ops/drush-launcher/releases\n";
    exit(1);
  }
}

if ($DEBUG) {
  echo "ROOT: " . $ROOT . PHP_EOL;
}

if (!$drupalFinder->locateRoot($ROOT)) {
  echo 'The Drush launcher could not find a Drupal site to operate on. Please do *one* of the following:' . PHP_EOL;
  echo '  - Navigate to any where within your Drupal project and try again.' . PHP_EOL;
  echo '  - Add --root=/path/to/drupal so Drush knows where your site is located.' . PHP_EOL;
  echo '  - Add a site alias so Drush knows where your site is located.' . PHP_EOL;
  exit(1);
}

if (file_exists(Path::join($drupalFinder->getVendorDir(), 'drush/drush/src/Preflight/Preflight.php'))) {
  $DRUSH_VERSION = 9;
}

$drupalRoot = $drupalFinder->getDrupalRoot();
if ($DEBUG) {
  echo "DRUSH VERSION: " . $DRUSH_VERSION . PHP_EOL;
  echo "DRUPAL ROOT: " . $drupalRoot . PHP_EOL;
  echo "COMPOSER ROOT: " . $drupalFinder->getComposerRoot() . PHP_EOL;
  echo "VENDOR ROOT: " . $drupalFinder->getVendorDir() . PHP_EOL;
}

// MW: This chdir appears unneeded.
// Change into Drush root if we are not already inside there. We don't want to
// change if we are in a particular sites directory, for example.
//$cwd = getcwd();;
//if (!Path::isBasePath($drupalRoot, $cwd)) {
//  if ($DEBUG) {
//    echo "Changing directory to " . $drupalRoot . " since CWD ($cwd) is not inside." . PHP_EOL;
//  }
//  chdir($drupalRoot);
//}

if (!file_exists($drupalFinder->getVendorDir() . '/drush/drush/includes/preflight.inc')) {
  echo 'The Drush launcher could not find a local Drush in your Drupal site.' . PHP_EOL;
  echo 'Please add Drush with Composer to your project.' . PHP_EOL;
  echo 'Run \'cd "' . $drupalFinder->getComposerRoot() . '" && composer require drush/drush\'' . PHP_EOL;
  exit(1);
}
require_once $drupalFinder->getVendorDir() . '/drush/drush/includes/preflight.inc';

if ($DRUSH_VERSION == 8) {
  if (file_exists($drupalRoot . '/autoload.php')) {
    require_once $drupalRoot . '/autoload.php';
  }
  else {
    require_once $drupalFinder->getVendorDir() . '/autoload.php';
  }

  require_once $drupalFinder->getVendorDir() . '/drush/drush/includes/context.inc';
  drush_set_option('root', $drupalRoot);
  drush_set_option('local', TRUE);
}
else {
  // Nothing to do. Drush9 manages two autoloaders.
}

exit(drush_main());

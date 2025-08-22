<?php

// Replace with your plugin directory and desired Phar filename
$pluginDir = 'C:\xampp\htdocs\wordpress\wp-content\plugins\mottasl-woocommerce';

// Replace with your plugin directory and desired Phar filename
$pharName  = 'your-plugin.phar';

try {
  $phar = new Phar($pharName);
  $phar->setStub(Phar::PHAR_PHP_BINDIR . '/mottasl.php'); // Replace with your main plugin file

  // Exclude specific directories (optional)
  $phar->addFilesFromDirectory($pluginDir, '.', array(
      '!vendor/autoload.php' // Exclude autoloader (might be needed in some cases)
  ));

  $phar->compressFiles(Phar::GZ); // Optional: Compress files for smaller archive size
  echo "Phar archive '$pharName' created successfully!\n";
} catch (Exception $e) {
  echo "Error creating Phar archive: " . $e->getMessage() . "\n";
}

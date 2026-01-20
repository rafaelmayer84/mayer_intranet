<?php
/**
 * Laravel - A PHP Framework For Web Artisans
 * Redirect to public folder
 */

// Change current directory to public
chdir(__DIR__ . '/public');

// Include the front controller
require __DIR__ . '/public/index.php';

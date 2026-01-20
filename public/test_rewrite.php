<?php
echo "mod_rewrite: " . (extension_loaded(mod_rewrite) || function_exists(apache_get_modules) && in_array(mod_rewrite, apache_get_modules()) ? "✅ ATIVADO" : "❌ DESATIVADO");
echo "\n";
echo "REQUEST_URI: " . $_SERVER["REQUEST_URI"];
echo "\n";
echo "SCRIPT_NAME: " . $_SERVER["SCRIPT_NAME"];
?>

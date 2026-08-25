<?php
session_start();

echo "<h2>Zalpro Active Session Debugger</h2>";

echo "<h3>1. Session Data ($_SESSION):</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";

echo "<h3>2. Cookie Data ($_COOKIE):</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";
?>

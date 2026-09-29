<?php $assetBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'')),'/'); $assetBase=preg_replace('~/(admin|collector|vendor|auth)$~','',$assetBase); ?>
<link rel="icon" href="<?= htmlspecialchars(rtrim($assetBase,'/').'/assets/favicon/favicon.ico',ENT_QUOTES) ?>">

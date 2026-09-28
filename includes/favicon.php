<?php $assetBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'')),'/'); if (preg_match('~/(admin|collector|vendor|auth)$~',$assetBase)) $assetBase=dirname($assetBase); ?>
<link rel="icon" href="<?= htmlspecialchars(rtrim($assetBase,'/').'/assets/favicon/favicon.ico',ENT_QUOTES) ?>">

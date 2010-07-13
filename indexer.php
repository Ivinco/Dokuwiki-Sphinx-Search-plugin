<?php
$ssPluginRoot = dirname(__FILE__);
chdir($ssPluginRoot);
system("indexer -c sphinx.conf --rotate dk_main");
?>

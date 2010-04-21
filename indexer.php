<?php
$ssPluginRoot = dirname(__FILE__);
chdir($ssPluginRoot);
system("/usr/bin/indexer -c sphinx.conf --rotate dk_main");
?>

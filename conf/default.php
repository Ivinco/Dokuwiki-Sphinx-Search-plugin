<?php
/**
 * Options for the sphinxsearch plugin
 *
 * @author Yaroslav Vorozhko <yaroslav@ivinco.com>
 */

$conf['host']     = 'localhost';
$conf['port']     = 3313;
$conf['index']     = 'dk_main';
$conf['maxresults'] = 10;
$conf['snippetsize'] = 200;
$conf['aroundwords'] = 5;

//main search matching weights
$conf['body_priority'] = 3;
$conf['title_priority'] = 3;
$conf['namespace_priority'] = 1;
$conf['pagename_priority'] = 3;

//"Matching pagenames" search matching weights
$conf['mp_namespace_priority'] = 1;
$conf['mp_pagename_priority'] = 2;

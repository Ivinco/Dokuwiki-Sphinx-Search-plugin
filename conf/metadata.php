<?php
/**
 * Options for the sphinxsearch plugin
 *
 * @author Yaroslav Vorozhko <yaroslav@ivinco.com>
 */
$meta['host']     = array('string');
$meta['port']     = array('numeric');
$meta['index']     = array('string');
$meta['maxresults'] = array('numeric');
$conf['snippetsize'] = array('numeric');
$conf['arroundwords'] = array('numeric');
$conf['body_priority'] = array('numeric');
$conf['title_priority'] = array('numeric');
$conf['namespace_priority'] = array('numeric');
$conf['pagename_priority'] = array('numeric');
$conf['mp_namespace_priority'] = array('numeric');
$conf['mp_pagename_priority'] = array('numeric');
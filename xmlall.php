<?php
/**
 * XML feed export
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

$deStatus = ini_get('display_errors');
ini_set('display_errors', 0);
/* Initialization */

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once(DOKU_INC.'inc/feedcreator.class.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/parser/parser.php');


require_once(DOKU_PLUGIN.'sphinxsearch/PageMapper.php');
require_once(DOKU_PLUGIN.'sphinxsearch/functions.php');

if (!file_exists(DOKU_INC.$conf['savedir']."/sphinxsearch/")){
	mkdir(DOKU_INC.$conf['savedir']."/sphinxsearch/");
}

$pagesList = getPagesList();

echo '<?xml version="1.0" encoding="utf-8"?>
<sphinx:docset>

<sphinx:schema>
<sphinx:field name="title"/> 
<sphinx:field name="body"/>
<sphinx:field name="namespace"/>
<sphinx:field name="pagename"/>
<sphinx:field name="level"/>
<sphinx:field name="modified"/>
<sphinx:attr name="level" type="int" bits="8" default="1"/>
</sphinx:schema>
';

$pageMapper = new PageMapper();
foreach($pagesList as $row){
    $dokuPageId = $row['id'];
    resolve_pageid('',$page,$exists);
    if (empty($dokuPageId) || !$exists){ //do not include not exists page
        continue;
    }
    //get meta data
    $metadata = p_get_metadata($dokuPageId);    
    
    $sections = getDocumentsByHeadings($dokuPageId, $metadata);
    
    if (!empty($sections)){
        foreach($sections as $hid => $section){
            //parse meta data for headers, abstract, date, authors
            $data = array();
            $data['id'] = crc32($dokuPageId.$hid);
            $data['namespace'] = getCategories($dokuPageId);
            $data['pagename'] = getPagename($dokuPageId);
            $data['level'] = $section['level'];
            $data['modified'] = $metadata['date']['modified'];
            $data['title'] = strip_tags($section['title_text']);
            $data['title_to_index'] = $section['title_to_index'];
            $data['body'] = $section['section']; //strip_tags(p_render('xhtml',p_get_instructions($section['section']),$info));

            //convert to utf-8 encoding
            $data['title_to_index'] = mb_convert_encoding($data['title_to_index'], "UTF-8", mb_detect_encoding($data['title_to_index'], "auto"));
            $data['body'] = mb_convert_encoding($data['body'], "UTF-8", mb_detect_encoding($data['body'], "auto"));

            echo formatXml($data)."\n";
            $pageMapper->add($dokuPageId, $data['title'], $section['title'], $hid);
        }
    } else {
        $data = array();
        $data['id'] = crc32($dokuPageId);
        $data['namespace'] = getCategories($dokuPageId);
        $data['pagename'] = getPagename($dokuPageId);
        $data['level'] = 1;
        $data['modified'] = $metadata['date']['modified'];
        $data['title'] = strip_tags($metadata['title']);
        $data['title_to_index'] = $metadata['title'];
        $data['body'] = io_readFile(wikiFN($dokuPageId)); //strip_tags(p_wiki_xhtml($dokuPageId,$metadata['date']['modified'],false));

        //convert to utf-8 encoding
        $data['title_to_index'] = mb_convert_encoding($data['title_to_index'], "UTF-8", mb_detect_encoding($data['title_to_index'], "auto"));
        $data['body'] = mb_convert_encoding($data['body'], "UTF-8", mb_detect_encoding($data['body'], "auto"));

        echo formatXml($data)."\n";
        $pageMapper->add($dokuPageId, $metadata['title'], $metadata['title']);
    }
    
}
echo '</sphinx:docset>';

ini_set('display_errors', $deStatus);
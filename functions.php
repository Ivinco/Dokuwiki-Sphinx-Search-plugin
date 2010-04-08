<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

function formatXml($data)
{
    $xmlFormat = '
<sphinx:document id="{id}">
<title><![CDATA[[{title}]]></title>
<body><![CDATA[[{body}]]></body>
<categories><![CDATA[[{categories}]]></categories>
<level>{level}</level>
<modified>{modified}</modified>
</sphinx:document>

';

    return str_replace( array('{id}', '{title}', '{body}', '{categories}', '{level}', '{modified}'),
                        array($data['id'], $data['title'], $data['body'], $data['categories'],
                             $data['level'], $data['modified']),
                $xmlFormat
            );
}

function getDocumentsByHeadings($id, $metadata)
{
    if (empty($metadata) || empty($metadata['description']['tableofcontents'])) return false;

    $sections = array();
    $level = 1;
    $previouse_title = '';
    foreach($metadata['description']['tableofcontents'] as $row){
        $sections[$row['hid']] = array(
                                    'section' => getSection($id, $row['title']),
                                    'level' => $row['level'],
                                    'title' => $row['title']
                                    );
        if ($row['level'] > $level){
            $sections[$row['hid']]['title_text'] = $previouse_title . " &raquo; ".$row['title'];
        } else {
            $sections[$row['hid']]['title_text'] = $row['title'];
            $previouse_title = $row['title'];
        }
    }
    return $sections;
}

function getSection($id, $header)
{
    static $cacheInstructions = null;
    static $cacheDoc = null;

    if (empty($cacheDoc[$id])){
        // Create the parser
        $Parser = & new Doku_Parser();

        // Add the Handler
        $Parser->Handler = & new Doku_Handler();

        // Load the header mode to find headers
        $Parser->addMode('header',new Doku_Parser_Mode_Header());
        $Parser->addMode('listblock',new Doku_Parser_Mode_ListBlock());

        // Loads the raw wiki document
        $doc = io_readFile(wikiFN($id));

        // Get a list of instructions
        $instructions = $Parser->parse($doc);

        unset($Parser->Handler);
        unset($Parser);

        //free old cache
        $cacheInstructions = null;
        $cacheDoc = null;

        //initialize new cache
        $cacheInstructions[$id] = $instructions;
        $cacheDoc[$id] = $doc;
    } else {
        $instructions = $cacheInstructions[$id];
        $doc = $cacheDoc[$id];
    }    

    

    // Use this to watch when we're inside the section we want
    $inSection = FALSE;
    $startPos = 0;
    $endPos = 0;

    // Loop through the instructions
    foreach ( $instructions as $instruction ) {

        if ( !$inSection ) {

            // Look for the header for the "Lists" heading
            if ( $instruction[0] == 'header' &&
                    trim($instruction[1][0]) == $header ) {

                $startPos = $instruction[2];
                $inSection = TRUE;
            }
        } else {

            // Look for the end of the section
            if ( $instruction[0] == 'section_close' ) {
                $endPos = $instruction[2];
                break;
            }
        }
    }

    // Normalize and pad the document in the same way the parse does
    // so that byte indexes with match
    $doc = "\n".str_replace("\r\n","\n",$doc)."\n";
    $section = substr($doc, $startPos, ($endPos-$startPos));

    return $section;
}

function getCategories($id)
{
    if (empty($id)) return '';

    if (false === strpos($id, ":")){
        return $id;
    }

    $ns = explode(":", $id);
    $nsCount = count($ns);

    $result = '';
    do{
        for($i = 0; $i < $nsCount; $i++){
            $name = $ns[$i];
            $result .= $name;
            if ($i < $nsCount - 1){
                 $result .= ':';
            }
        }
        $result .= ' ';
    }while($nsCount--);
    return $result;
}


 /**
  * Method return all wiki page names
  * @global array $conf
  * @return array
  */
 function getPagesList()
 {
    global $conf;

    $data = array();
    sort($data);
    search($data,$conf['datadir'],'search_allpages','','');

    return $data;
}

function getNsLinks($id, $keywords, $search)
{
    global $conf;
    $parts = explode(':', $id);
    $count = count($parts);
    
    // print intermediate namespace links
    $part = '';
    $data = array();
    $titles = array();
    for($i=0; $i<$count; $i++){
        $part .= $parts[$i].':';
        $page = $part;
        resolve_pageid('',$page,$exists);

        if (preg_match("#:start$#", $page) && !preg_match("#:start:$#", $part)) {
            $page = substr($page, 0, strpos($page, ":start"));
        }; 

        // output
        if ($exists){
            $titles[wl($page)] = $parts[$i];
        } else {
            $titles[wl($page)] = $parts[$i];
        }
        $data[] = array('link' => "?do=sphinxsearch&id={$keywords}".urlencode(" @categories $page"));
    }
    $titleExcerpt = $search->getExcerpt($titles, $search->starQuery($keywords));
    $i = 0;
    foreach ($data as $key => $notused){
        $data[$key]['title'] = $titleExcerpt[$i++];
    }
    return $data;
}
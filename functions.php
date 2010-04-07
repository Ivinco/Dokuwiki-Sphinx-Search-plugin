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
    foreach($metadata['description']['tableofcontents'] as $row){
        $sections[$row['hid']] = array(
                                    'section' => getSection($id, $row['title']),
                                    'title' => $row['title'],
                                    'level' => $row['level']
                                    );
    }
    return $sections;
}

function getSection($id, $header)
{
    // Create the parser
    $Parser = & new Doku_Parser();

    // Add the Handler
    $Parser->Handler = & new Doku_Handler();

    // Load the header mode to find headers
    $Parser->addMode('header',new Doku_Parser_Mode_Header());

    // Load the modes which could contain markup that might be
    // mistaken for a header
    $Parser->addMode('listblock',new Doku_Parser_Mode_ListBlock());
    $Parser->addMode('preformatted',new Doku_Parser_Mode_Preformatted());
    $Parser->addMode('table',new Doku_Parser_Mode_Table());
    $Parser->addMode('unformatted',new Doku_Parser_Mode_Unformatted());
    $Parser->addMode('php',new Doku_Parser_Mode_PHP());
    $Parser->addMode('html',new Doku_Parser_Mode_HTML());
    $Parser->addMode('code',new Doku_Parser_Mode_Code());
    $Parser->addMode('file',new Doku_Parser_Mode_File());
    $Parser->addMode('quote',new Doku_Parser_Mode_Quote());
    $Parser->addMode('footnote',new Doku_Parser_Mode_Footnote());
    $Parser->addMode('internallink',new Doku_Parser_Mode_InternalLink());
    $Parser->addMode('media',new Doku_Parser_Mode_Media());
    $Parser->addMode('externallink',new Doku_Parser_Mode_ExternalLink());
    $Parser->addMode('windowssharelink',new Doku_Parser_Mode_WindowsShareLink());
    $Parser->addMode('filelink',new Doku_Parser_Mode_FileLink());

    // Loads the raw wiki document
    $doc = io_readFile(wikiFN($id));

    // Get a list of instructions
    $instructions = $Parser->parse($doc);

    unset($Parser);

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

function getNsLinks($id, $query, $search, $queryString)
{
    global $conf;
    $parts = explode(':', $id);
    $count = count($parts);

    $queryStringValue = $queryString;

    if (false !== ($pos = strpos($queryStringValue, "+%40categories"))){;
        $queryStringValue = substr($queryStringValue, 0, $pos);
    }

    // print intermediate namespace links
    $part = '';
    $data = array();
    $titles = array();
    for($i=0; $i<$count; $i++){
        $part .= $parts[$i].':';
        $page = $part;
        resolve_pageid('',$page,$exists);
        if (preg_match("#:start$#", $page)) {
            $page = substr($page, 0, strpos($page, ":start"));
        }; 

        // output
        if ($exists){
            //$titles[wl($page)] = useHeading('navigation') ? p_get_first_heading($page) : $page;
            if(!$titles[wl($page)]) {
                $titles[wl($page)] = $parts[$i];
            }
        } else {
            continue; //Skip not exists pages
            //$titles[wl($page)] = $parts[$i];
        }      
        $data[] = array('link' => '?'. $queryStringValue . urlencode(" @categories $page"));
    }
    $titleExcerpt = $search->getExcerpt($titles, $search->starQuery($query));
    $i = 0;
    foreach ($data as $key => $notused){
        $data[$key]['title'] = $titleExcerpt[$i++];
    }
    return $data;
}
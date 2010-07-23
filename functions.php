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
<namespace><![CDATA[[{namespace}]]></namespace>
<pagename><![CDATA[[{pagename}]]></pagename>
<level>{level}</level>
<modified>{modified}</modified>
</sphinx:document>

';

    return str_replace( array('{id}', '{title}', '{body}', '{namespace}', '{pagename}', '{level}', '{modified}'),
                        array($data['id'], escapeTextValue($data['title_to_index']),
                            escapeTextValue($data['body']),
                            escapeTextValue($data['namespace']),
                            escapeTextValue($data['pagename']),
                             $data['level'], $data['modified']),
                $xmlFormat
            );
}

function escapeTextValue($value)
{
    if ("" === $value)
    {
        return "";
    }
    //$value = mb_convert_encoding($value,'UTF-8','ISO-8859-1');
    $value = strip_tags($value);
    $value = stripInvalidXml($value);
    return str_replace("]]>", "]]><![CDATA[]]]]><![CDATA[>]]><![CDATA[", $value);
 }

function stripInvalidXml($value)
{
    $ret = "";
    if (empty($value))
    {
      return $ret;
    }

    $current = null;
    $length = strlen($value);
    for ($i=0; $i < $length; $i++)
    {
      $current = ord($value{$i});
      if (($current == 0x9) ||
          ($current == 0xA) ||
          ($current == 0xD) ||
          (($current >= 0x20) && ($current <= 0xD7FF)) ||
          (($current >= 0xE000) && ($current <= 0xFFFD)) ||
          (($current >= 0x10000) && ($current <= 0x10FFFF)))
      {
        $ret .= chr($current);
      }
      else
      {
        $ret .= " ";
      }
    }
    return $ret;
  }

function getDocumentsByHeadings($id, $metadata)
{
    if (empty($metadata) || empty($metadata['description']['tableofcontents'])) return false;

    $sections = array();
    $level = 1;
    $previouse_title = '';
    foreach($metadata['description']['tableofcontents'] as $row){
        $sections[$row['hid']] = array(
                                    'section' => getSectionByTitleLevel($id, $row['title']),
                                    'level' => $row['level'],
                                    'title' => $row['title']
                                    );
        if ($row['level'] > $level && !empty($previouse_title)){
            $sections[$row['hid']]['title_text'] = $previouse_title . " &raquo; ".$row['title'];
        } else {
            $sections[$row['hid']]['title_text'] = $row['title'];
            $previouse_title = $row['title'];
        }
        $sections[$row['hid']]['title_to_index'] = $row['title'];
    }
    return $sections;
}

function getSectionByTitleLevel($id, $header, $extended=false)
{
    $headerReg = preg_quote($header, '/');
    $doc = io_readFile(wikiFN($id));
    $regex = "(={1,6})\s*({$headerReg})\s*(={1,6})";    
    $section = '';
    if (preg_match("/$regex/i",$doc,$matches)) {
        $startHeader = $matches[0];
        $startHeaderPos = strpos($doc, $startHeader) + strlen($startHeader);
        $endDoc = substr($doc, $startHeaderPos);

        $regex = '(={3,6})(.*?)(={3,6})';
        if (preg_match("/$regex/i",$endDoc,$matches)) {
            $endHeader = $matches[0];
            $endHeaderPos = strpos($doc, $endHeader);
        } else {
            $endHeaderPos = 0;
        }
        if ($endHeaderPos){
            $section = substr($doc, $startHeaderPos, $endHeaderPos - $startHeaderPos);
        } else {
            $section = substr($doc, $startHeaderPos);
        }        
    }
    $section = trim($section);
    //trying to get next section content if body for first section is empty
    //working only for extended mode
    if ($extended && empty($section)){
        $startHeaderPos = $endHeaderPos + strlen($endHeader);
        $endDoc = substr($endDoc, $startHeaderPos);
        $regex = '(={3,6})(.*?)(={3,6})';
        if (preg_match("/$regex/i",$endDoc,$matches)) {
            $endHeader = $matches[0];
            $endHeaderPos = strpos($doc, $endHeader);
        } else {
            $endHeaderPos = 0;
        }
        if ($endHeaderPos){
            $section = substr($doc, $startHeaderPos, $endHeaderPos - $startHeaderPos);
        } else {
            $section = substr($doc, $startHeaderPos);
        }
    }
    $section = trim($section);
    return $section;
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
        return '';
    }

    $ns = explode(":", $id);
    $nsCount = count($ns) - 1;

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

function getPagename($id)
{
    if (empty($id)) return '';

    if (false === strpos($id, ":")){
        return $id;
    }

    $ns = explode(":", $id);
    return $ns[count($ns) - 1];
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
        $data[] = array('link' => "?do=search&id={$keywords}".urlencode(" @ns $page"));
    }
    $titleExcerpt = $search->getExcerpt($titles, $search->starQuery($keywords));
    $i = 0;
    foreach ($data as $key => $notused){
        $data[$key]['title'] = $titleExcerpt[$i++];
    }
    return $data;
}

function printNamespaces($query)
{
  $data = array();
  $query = str_replace(" ", "_", $query);
  $data = ft_pageLookup($query, false);

  if(!count($data)) return false;

  print '<h3>Matching pagenames</h3>';
  print '<ul>';
  $counter = 0;
  foreach($data as $id){
    print '<li>';
    $ns = getNS($id);
    if($ns){
      $name = shorten(noNS($id), ' ('.$ns.')',30);
    }else{
      $name = $id;
    }
    $href = wl($id);

    tpl_link($href,$id, "class='wikilink1'");
    print '</li>';
    if(++$counter == 20){
        break;
    }
  }
  print '</ul>';
}

function printNamespacesNew($pageNames)
{
    if(empty($pageNames)) return false;

    $limit = 10;
    print '<h3>Matching pagenames</h3>';
    print '<ul>';
    $counter = 0;
    foreach($pageNames as $id => $header){
        $ns = getNS($id);
        if($ns){
          $name = shorten(noNS($id), ' ('.$ns.')',30);
        }else{
          $name = $id;
        }
        print '<li>';
        /*if (!empty($header)){
            print '<a href="'.wl($id).'#'.$header.'" '. "class='wikilink1'>".$id."</a>".'#'.$header;
        } else {
            print '<a href="'.wl($id).'" '. "class='wikilink1'>".$id."</a>";
        }*/
        print '<a href="'.wl($id).'" '. "class='wikilink1'>".$id."</a>";
        print '</li>';
        if (++$counter == $limit){
            break;
        }
    }
    print '</ul>';
}

if(!function_exists('shorten')){
    /**
     * Shorten a given string by removing data from the middle
     *
     * You can give the string in two parts, teh first part $keep
     * will never be shortened. The second part $short will be cut
     * in the middle to shorten but only if at least $min chars are
     * left to display it. Otherwise it will be left off.
     *
     * @param string $keep   the part to keep
     * @param string $short  the part to shorten
     * @param int    $max    maximum chars you want for the whole string
     * @param int    $min    minimum number of chars to have left for middle shortening
     * @param string $char   the shortening character to use
     */
    function shorten($keep,$short,$max,$min=9,$char='⌇'){
        $max = $max - utf8_strlen($keep);
       if($max < $min) return $keep;
        $len = utf8_strlen($short);
        if($len <= $max) return $keep.$short;
        $half = floor($max/2);
        return $keep.utf8_substr($short,0,$half-1).$char.utf8_substr($short,$len-$half);
    }
}
if(!function_exists('utf8_strlen')){
    /**
     * Unicode aware replacement for strlen()
     *
     * utf8_decode() converts characters that are not in ISO-8859-1
     * to '?', which, for the purpose of counting, is alright - It's
     * even faster than mb_strlen.
     *
     * @author <chernyshevsky at hotmail dot com>
     * @see    strlen()
     * @see    utf8_decode()
     */
    function utf8_strlen($string){
        return strlen(utf8_decode($string));
    }
}

if(!function_exists('utf8_decode')){
    /**
     * (PHP 4, PHP 5)<br/>
     * Converts a string with ISO-8859-1 characters encoded with UTF-8
       to single-byte ISO-8859-1
     * @link http://php.net/manual/en/function.utf8-decode.php
     * @param string $data <p>
     * An UTF-8 encoded string.
     * </p>
     * @return string the ISO-8859-1 translation of data.
     */
    function utf8_decode ($data) {}
}
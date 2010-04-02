<?php
/**
 * XML feed export
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */


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

if (!file_exists(DOKU_INC.$conf['savedir']."/sphinxsearch/")){
	mkdir(DOKU_INC.$conf['savedir']."/sphinxsearch/");
}

$pagesList = getPagesList();

echo '<?xml version="1.0" encoding="utf-8"?>
<sphinx:docset>

<sphinx:schema>
<sphinx:field name="title"/> 
<sphinx:field name="body"/>
<sphinx:field name="categories"/>
<sphinx:field name="level"/>
<sphinx:field name="modified"/>
<sphinx:field name="creator"/>
<sphinx:attr name="level" type="int" bits="8" default="1"/>
</sphinx:schema>
';

$pageMapper = new PageMapper();

foreach($pagesList as $row){
    $dokuPageId = $row['id'];
    //get meta data
    $metadata = p_get_metadata($dokuPageId);
    $sections = getDocumentsByHeadings($dokuPageId, $metadata);
    if (!empty($sections)){
        foreach($sections as $hid => $section){
            //parse meta data for headers, abstract, date, authors
            $data = array();
            $data['id'] = crc32($dokuPageId.$hid);
            $data['categories'] = getCategories($dokuPageId) . '#' . $hid;
            $data['level'] = $section['level'];
            $data['modified'] = $metadata['date']['modified'];
            $data['creator'] = $metadata['creator'];
            $data['title'] = strip_tags($section['title']);
            $data['body'] = strip_tags(p_render('xhtml',p_get_instructions($section['section']),$info));

            echo formatXml($data)."\n";
            $pageMapper->add($dokuPageId, $section['title'], $hid);
        }
    } else {
        //parse meta data for headers, abstract, date, authors
        $data = array();
        $data['id'] = crc32($dokuPageId);
        $data['categories'] = getCategories($dokuPageId);
        $data['level'] = 1;
        $data['modified'] = $metadata['date']['modified'];
        $data['creator'] = $metadata['creator'];
        $data['title'] = strip_tags($metadata['title']);
        $data['body'] = strip_tags(p_wiki_xhtml($dokuPageId,$metadata['date']['modified'],false));

        echo formatXml($data)."\n";
        $pageMapper->add($dokuPageId, $metadata['title']);
    }
}

echo '</sphinx:docset>';



function formatXml($data)
{
    $xmlFormat = '
<sphinx:document id="{id}">
<title><![CDATA[[{title}]]></title>
<body><![CDATA[[{body}]]></body>
<categories><![CDATA[[{categories}]]></categories>
<level>{level}</level>
<modified>{modified}</modified>
<creator>{creator}</creator>
</sphinx:document>

';
    
    return str_replace( array('{id}', '{title}', '{body}', '{categories}', '{level}', '{modified}', '{creator}'),
                        array($data['id'], $data['title'], $data['body'], $data['categories'],
                             $data['level'], $data['modified'], $data['creator']),
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

/**
 * Array
(
    [date] => Array
        (
            [created] => 1239181434
            [modified] => 1239202933
        )

    [creator] => Sergey Nikolaev
    [last_change] => Array
        (
            [date] => 1239202933
            [ip] => 85.118.229.162
            [type] => E
            [id] => cal:minutes:boardreader:200904:20090408
            [user] => snikolaev
            [sum] =>
            [extra] =>
        )

    [contributor] => Array
        (
            [snikolaev] => Sergey Nikolaev
        )

    [title] => BoardReader call of Apr 8 2009
    [description] => Array
        (
            [tableofcontents] => Array
                (
                    [0] => Array
                        (
                            [hid] => boardreader_call_of_apr_8_2009
                            [title] => BoardReader call of Apr 8 2009
                            [type] => ul
                            [level] => 1
                        )

                    [1] => Array
                        (
                            [hid] => sergey
                            [title] => Sergey
                            [type] => ul
                            [level] => 2
                        )

                    [2] => Array
                        (
                            [hid] => slava
                            [title] => Slava
                            [type] => ul
                            [level] => 2
                        )

                    [3] => Array
                        (
                            [hid] => roman
                            [title] => Roman
                            [type] => ul
                            [level] => 2
                        )

                    [4] => Array
                        (
                            [hid] => nikita
                            [title] => Nikita
                            [type] => ul
                            [level] => 2
                        )

                    [5] => Array
                        (
                            [hid] => discussion
                            [title] => Discussion
                            [type] => ul
                            [level] => 2
                        )

                )

            [abstract] => Participants: Mindaugas, Sergey, Slava, Roman, Nikita

Duration: 23 min

Sergey

Status:

	*  published Roman's changes
	*  started reviewing Slava's changes


Plans:

	*  start altering (singature field)
	*  select server error handling
	*  publish Slava's and Roman's changes
        )

    [internal] => Array
        (
            [cache] => 1
            [toc] => 1
        )

)

 */


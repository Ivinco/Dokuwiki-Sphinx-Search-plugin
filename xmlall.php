<?php
/**
 * XML feed export
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// dokuwiki folder (absolute system path)
$dokuwiki_folder =  '/www/dokuwiki/htdocs';

// dokuwiki url
$dokuwiki_url = 'http://dokuwiki.home';

// link prefix to another page
$link_prefix = 'http://dokuwiki.home/doc.php/';

/* Initialization */

define('DOKU_PATH', $dokuwiki_folder);
define('DOKU_INC', DOKU_PATH . '/');
define('DOKU_CONF', DOKU_PATH . '/conf/');
define('DOKU_URL', $dokuwiki_url  . '/');

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once(DOKU_INC.'inc/feedcreator.class.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/search.php');

require_once(DOKU_PLUGIN.'sphinxsearch/PageMapper.php');

$pagesList = getPagesList();

echo '<?xml version="1.0" encoding="utf-8"?>
<sphinx:docset>

<sphinx:schema>
<sphinx:field name="title"/> 
<sphinx:field name="body"/>
<sphinx:field name="headings"/>
<sphinx:field name="categories"/>
<sphinx:field name="modified"/>
<sphinx:field name="created"/>
<sphinx:field name="creator"/>
<sphinx:field name="extra"/>
<sphinx:attr name="modified" type="timestamp"/>
</sphinx:schema>
';

$pageMapper = new PageMapper();

foreach($pagesList as $row){
    $dokuPageId = $row['id'];
    //get meta data
    $metadata = p_get_metadata($dokuPageId);    
    //parse meta data for headers, abstract, date, authors
    $data['id'] = crc32($dokuPageId);
    $data['headings'] = strip_tags(getHeadings($metadata));
    $data['categories'] = getCategories($dokuPageId);
    $data['created'] = $metadata['date']['created'];
    $data['modified'] = $metadata['date']['modified'];
    $data['creator'] = $metadata['creator'];
    $data['title'] = strip_tags($metadata['title']);
    $data['extra'] = strip_tags($metadata['description']['abstract']);
    $data['body'] = strip_tags(p_wiki_xhtml($dokuPageId,$metadata['date']['modified'],false));

    echo formatXml($data)."\n";

    $pageMapper->add($dokuPageId);
}

echo '</sphinx:docset>';



function formatXml($data)
{
    $xmlFormat = '
<sphinx:document id="{id}">
<title><![CDATA[[{title}]]></title>
<body><![CDATA[[{body}]]></body>
<headings><![CDATA[[{headings}]]></headings>
<categories><![CDATA[[{categories}]]></categories>
<modified>{modified}</modified>
<created>{created}</created>
<creator>{creator}</creator>
<extra><![CDATA[[{extra}]]></extra>
</sphinx:document>

';
    
    return str_replace( array('{id}', '{title}', '{body}', '{headings}', '{categories}', '{modified}', '{created}', '{creator}', '{extra}'),
                        array($data['id'], $data['title'], $data['body'], $data['headings'],
                            $data['categories'],  $data['modified'], $data['created'], $data['creator'], $data['extra']),
                $xmlFormat
            );
}

function getHeadings($metadata)
{
    if (empty($metadata) || empty($metadata['description']['tableofcontents'])) return '';

    $result = array();
    foreach($metadata['description']['tableofcontents'] as $row){
        $result[] = $row['title'];
    }
    return implode(", ", $result);
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


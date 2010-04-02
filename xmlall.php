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


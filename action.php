<?php
/**
 * Script to search in dokuwiki documents
 *
 * @author Yaroslav Vorozhko <yaroslav@ivinco.com>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_INC.'inc/parser/parser.php');

require_once(DOKU_PLUGIN . 'action.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/sphinxapi.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/PageMapper.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/SphinxSearch.php');
require_once(DOKU_PLUGIN . 'sphinxsearch/functions.php');


class action_plugin_sphinxsearch extends DokuWiki_Action_Plugin {

    /**
	* return some info
	*/
    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
	}

    /**
	* Register to the content display event to place the results under it.
	*/
    /**
     * register the eventhandlers
     */
    function register(&$controller){
        $controller->register_hook('TPL_CONTENT_DISPLAY',   'BEFORE', $this, 'handle_act_unknown', array());
    }

    /**
     * If our own 'googlesearch' action was given we produce our content here
     */
    function handle_act_unknown(&$event, $param){
        global $ACT;
        global $QUERY;
        if($ACT != 'search') return; // nothing to do for us

        // we can handle it -> prevent others
        $event->stopPropagation();
        $event->preventDefault();

        
        $this->_search($QUERY,$_REQUEST['start']);
    }    

    /**
     * do the search and displays the result
     */
    function _search($query, $start) {
        global $conf;        

        $start = (int) $start;
        if($start < 0) $start = 0;

        $categories = $this->_getCategories($query);        
        $keywords = $this->_getKeywords($query);	

        $search = new SphinxSearch($this->getConf('host'), $this->getConf('port'), $this->getConf('index'));
        $search->setSnippetSize($this->getConf('snippetsize'));
        $search->setArroundWordsCount($this->getConf('aroundwords'));
        $search->setTitlePriority($this->getConf('title_priority'));
        $search->setBodyPriority($this->getConf('body_priority'));
        $search->setCategoriesPriority($this->getConf('categories_priority'));
        
        $pagesList = $search->search($keywords, $categories, $start, $this->getConf('maxresults'));

        if ($search->getError()){
            echo '<b>' . $search->getError() . '</b>!';
            return;
        }
        
        $totalFound = $search->getTotalFound();
        if(empty($pagesList)){
            echo '<b>Nothing was found by ' . $query . '</b>!';
            return;
        } else {
            echo '<style type="text/css">
                div.dokuwiki .search_snippet{
                    color:#000000;
                    margin-left:0px;
                }
                div.dokuwiki .search_cnt{
                    color:#CCCCCC;
                    font-size: 10px;
                }
                div.dokuwiki .search_nmsp{
                    font-size: 10px;
                }
                </style>
                ';

            echo '<h2>Found '.$totalFound . ($totalFound == 1  ? ' document ' : ' documents ') . ' for query "' . hsc($query).'"</h2>';
            echo '<div class="search_result">';
            // printout the results
            foreach ($pagesList as $crc => $row) {
                $page = $row['page'];
                $bodyExcerpt = $row['bodyExcerpt'];
                $titleTextExcerpt = $row['titleTextExcerpt'];
                $hid = $row['hid'];

                $metaData = p_get_metadata($page);

                if (!empty($titleTextExcerpt)){
                    $titleText = $titleTextExcerpt;
                } elseif(!empty($row['title_text'])){
                    $titleText = $row['title_text'];
                } elseif(!empty($metaData['title'])){
                    $titleText = hsc($metaData['title']);
                } else {
                    $titleText = hsc($page);
                }

                $namespaces = getNsLinks($page, $keywords, $search);
                $href = !empty($hid) ? (wl($page).'#'.$hid) : wl($page);

                echo '<a href="'.$href.'" title="" class="wikilink1">'.$titleText.'</a><br/>';
                echo '<div class="search_snippet">';
                echo strip_tags($bodyExcerpt, '<b>,<strong>');
                echo '</div>';
                $sep=':';
                $i = 0;
                echo '<span class="search_nmsp">';
                foreach ($namespaces as $name){
                    $link = $name['link'];
                    $pageTitle = $name['title'];
                    tpl_link($link, $pageTitle);
                    if ($i++ < count($namespaces)-1){
                        echo $sep;
                    }
                }
                if (!empty($hid)){
                    echo '#'.$hid;
                }
                echo '</span>';
                echo '<span class="search_cnt"> - Last modified '.date("Y-m-d H:i",$metaData['date']['modified']).'</span> ';
                echo '<span class="search_cnt">by '.$metaData['last_change']['user'].'</span> ';
                echo '<br />';echo '<br />';
            }
            echo '</div>';
            echo '<div class="sphinxsearch_nav">';
            if ($start > 1){
                $prev = $start - $this->getConf('maxresults');
                if($prev < 0) $prev = 0;

                echo $this->external_link(wl('',array('do'=>'search','id'=>$query,'start'=>$prev),'false','&'),
                                          'prev','wikilink1 gs_prev',$conf['target']['interwiki']);
            }
            echo ' ';
            if($start + $this->getConf('maxresults') < $totalFound){
                $next = $start + $this->getConf('maxresults');

                echo $this->external_link(wl('',array('do'=>'search','id'=>$query,'start'=>$next),'false','&'),
                                          'next','wikilink1 gs_next',$conf['target']['interwiki']);
            }
            echo '</div>';
        }
        
    }

     function searchform(){
          global $lang;
          global $ACT;
          global $QUERY;

          // don't print the search form if search action has been disabled
          if (!actionOk('search')) return false;

          print '<form action="'.wl().'" accept-charset="utf-8" class="search" id="dw__search"><div class="no">';
          print '<input type="hidden" name="do" value="search" />';
          print '<input type="text" ';
          if($ACT == 'search') print 'value="'.htmlspecialchars($QUERY).'" ';
          print 'id="qsearch__in" accesskey="f" name="id" class="edit" title="[ALT+F]" />';
          print '<input type="submit" value="'.$lang['btn_search'].'" class="button" title="'.$lang['btn_search'].'" />';
          print '</div></form>';
          return true;
    }

    function _getCategories($query)
    {
        $categories = '';
        $query = urldecode($query);
        if (false !== ($pos = strpos($query, "@categories"))){;
            $categories = substr($query, $pos + strlen("@categories"));
        }
        return trim($categories);
    }

    function _getKeywords($query)
    {
        $keywords = $query;
        $query = urldecode($query);
        if (false !== ($pos = strpos($query, "@categories"))){;
            $keywords = substr($keywords, 0, $pos);
        } 
        return trim($keywords);
    }
}

?>

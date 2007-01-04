<?php
/**
 * Syntax Plugin medialist
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_medialist extends DokuWiki_Syntax_Plugin {

    /**
     * General Info
     */
    function getInfo(){
        return array(
            'author' => 'Michael Klier',
            'email'  => 'chi@chimeric.de',
            'date'   => '2006-01-04',
            'name'   => 'mediaindex',
            'desc'   => 'Displays a list of mediafiles linked from the given page or located in the namespace of the page.',
            'url'    => 'http://www.chimeric.de/projects/dokuwiki/plugin/medialist'
        );
    }

    /**
     * Syntax Type
     *
     * Needs to return one of the mode types defined in $PARSER_MODES in parser.php
     */
    function getType()  { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort()  { return 299; }
    
    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{medialist>.+?}}',$mode,'plugin_medialist');
    }

    /**
     * Handler to prepare matched data for the rendering process
     */
    function handle($match, $state, $pos, &$handler){
        global $ID;

        // catch the match
        $match = substr($match,12,-2);

        // process the match
        $mdir = mediaFN(cleanID($match));
        if(empty($match) || !@file_exists($mdir) || !@is_dir($mdir)) $match = $ID;

        // check permissions
        if(auth_quickaclcheck($match) < AUTH_READ) return array();

        return array($match);
    }

    /**
     * Handles the actual output creation.
     */
    function render($mode, &$renderer, $data) {
        
        if($mode == 'xhtml'){
            // disable caching
            $renderer->info['cache'] = false;
            $renderer->doc .= $this->p_xhtml_medialist($data[0]);
            return true;
        }
        return false;
    }

    /**
     * Renders the medialist
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function p_xhtml_medialist($id){
        $out  = '';

        $media = $this->media_lookup($id);

        if(empty($media)) return;

        $out .= '<ul class="medialist">';
        $out .= html_buildlist($media,'medialist',array(&$this,'media_item'));
        $out .= '</ul>';

        return ($out);
    }

    /**
     * Callback function for html_buildlist()
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function media_item($src) {
        global $conf;

        $out = '';

        $link = array();
        $link['url']    = ml($src);
        $link['class']  = 'media';
        $link['target'] = $conf['target']['media'];
        $link['name']   = preg_replace('#.*?/|.*?:#','',$src);
        $link['title']  = $link['name'];

        // add file icons
        list($ext,$mime) = mimetype($src);
        $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
        $link['class'] .= ' mediafile mf_'.$class;

        // build the link
        $out .= '<a href="' . $link['url'] . '" ';
        $out .= 'class="' . $link['class'] . '" ';
        $out .= 'target="' . $link['target'] . '" ';
        $out .= 'title="' . $link['title'] . '">';
        $out .= $link['name'];
        $out .= '</a>';

        return ($out);
    }

    /**
     * searches for media linked in the page and its namespace and
     * returns an array of items
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function media_lookup($id) {
        global $conf;

        $media = array();

        $linked_media = array();
        $intern_media = array();

        // get the instructions
        $ins = p_cached_instructions(wikiFN($id),true,$id);

        // get linked media files
        foreach($ins as $node) {
            if($node[0] == 'internalmedia') {
                array_push($linked_media,$node[1][0]);
            } elseif($node[0] == 'externalmedia') {
                array_push($linked_media,$node[1][0]);
            }
        }

        // get mediafiles of current namespace
        $res = array(); // search result
        $dir = utf8_encode(str_replace(':','/',$id));
        require_once(DOKU_INC.'inc/search.php');
        search($res,$conf['mediadir'],'search_media',array(),$dir);
        foreach($res as $item) {
            array_push($intern_media,$item['id']);
        }

        // remove unique items
        $media = array_unique(array_merge($linked_media,$intern_media));

        return($media);
    }
}
//Setup VIM: ex: et ts=4 enc=utf-8 :

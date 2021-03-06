<?php

/**
 * Wiki V3 CMS Extension
 **/

require_once('wiki-v3.lib.php');

function extension_wikiv3( &$cms ){
    
    global $WIKI_DEFAULT_CONFIG;
    
    // register self in CMS
    $cms->extensions['wikiv3'] = new WikiParserExt();
    $cms->extensions['wikiv3']->smarty = $cms->smarty;
    $cms->extensions['wikiv3']->loadConfig($WIKI_DEFAULT_CONFIG);
    $cms->extensions['wikiv3']->loadConfig($cms->config['extensions']['wikiv3']['config']);
    // register
    $cms->smarty->register_modifier('wikify3',array(&$cms->extensions['wikiv3'],'smarty_wikify'));
    $cms->smarty->register_block('wikify3',array(&$cms->extensions['wikiv3'],'smarty_wikify_block'));
    $cms->smarty->register_modifier('parse_content',array(&$cms->extensions['wikiv3'],'smarty_parse'));
}

class WikiParserExt extends WikiParser{
    
    var $smarty;
    
    function smarty_wikify( $string ){
    
        return $this->parseString($string);
    }
    function smarty_wikify_block( $params, $content, &$smarty, &$repeat ){
        
        return $this->parseString($this->smarty->fetch('str:'.$content));
    }
    function smarty_parse( $string, $type ){
        
        switch($type){
            case 'wikify':
                return $this->parseString($string);
                break;
            case 'html':
                return $string;
                break;
            case 'smarty':
                return $this->smarty->fetch('str:'.$string);
                break;
            case 'smartwiki':
                return $this->parseString($this->smarty->fetch('str:'.$string));
                break;
        }
        return $string;
    }
};

?>
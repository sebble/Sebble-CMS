<?php

// -- CMS Extension : Simple Smarty Modifiers & Functions
//

// main function
function extension_simple_modifiers( &$cms ){
    
    $cms->smarty->register_modifier('break','smarty_modifier_break');
    $cms->smarty->register_modifier('dateonly','smarty_modifier_dateonly');
    $cms->smarty->register_modifier('wikify','smarty_modifier_wikify');
    $cms->smarty->register_function('assign_array','smarty_function_assign_array');
}

function smarty_modifier_break($string, $p=3, $continue=false){
    
    $string = preg_replace('/\r\n/',"\n",$string);
    $string = preg_replace('/\r/',"\n",$string);
    $paras = explode("\n\n",$string,$p+1);
    
    if(count($paras)>1){
        $last = array_pop($paras);
    }else{
        $last = '';
    }
    
    if(!$continue){
        return implode("\n\n",$paras);
    }else{
        return $last;
    }
}

function smarty_modifier_dateonly($string){
    
    $date = explode(' ',$string);
    return $date[0];
}

require_once('wiki.lib.php');

function smarty_modifier_wikify($string)
{
    return wiki_parse($string);
}

function smarty_function_assign_array($params, &$smarty)
{
    #if(isset($params['keys'])){
        $smarty->assign($params['as'],array_combine(explode(',',$params['keys']),explode(',',$params['values'])));
    #}else{
    #    $smarty->assign($params['as'],explode(',',$params['values']));
    #}
}


?>

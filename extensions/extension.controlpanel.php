<?php

/**
 * Secure Login and Session Functions
 **/

// let's start the session! 
session_start();
require_once('extend.lib.php');

/**
 * Secure Class
 * This version still uses the config file.
 * Note, the DB table `users` has been deleted for now
 **/

// This extension is a mess, sort out which vars should be defined and when!!

function extension_controlpanel( &$cms ){
    
    // register self in CMS
    $cms->extensions['controlpanel'] = new ControlPanel($cms);
    $cms->extensions['controlpanel']->Run();
    $cms->smarty->register_function('cp_placeholder',array(&$cms->extensions['controlpanel'],'smarty_placeholder'));
    $cms->smarty->register_function('cp_set_page',array(&$cms->extensions['controlpanel'],'smarty_setpage'));
    //$cms->smarty->plugins_dir[]=$cms->config['system']['system_dir'].'/plugins/controlpanel';
    // get useful data, maybe a silly overhead..
    // correction - just add thefns, not run them now.
    $cms->smarty->register_function('cp_get_types',array(&$cms->extensions['controlpanel'],'smarty_gettypes'));
    $cms->smarty->register_function('cp_get_templates',array(&$cms->extensions['controlpanel'],'smarty_gettemplates'));
    $cms->smarty->register_function('cp_get_sections',array(&$cms->extensions['controlpanel'],'smarty_getsections'));
    $cms->smarty->assign('js_confirm','onclick="javascript:return confirm(\'Are you sure you want to do this?\')"');
}

class ControlPanel{
    
    var $_CMS;
    var $_User;
    var $_Page;
    var $_Action;
    var $_Config;
    
    function ControlPanel( &$cms ){
        
        $this->_CMS = &$cms;
        //$this->config['users']..
        $this->_Config = $this->_CMS->config['extensions']['controlpanel'];
        $this->_Action['action'] = mysql_real_escape_string($_REQUEST[$this->_Config['action_var']]);
    }
    function Run(){
        $this->UserAuthenticate();
        $this->ExecuteAction();
        $this->ExtendSmarty();
    }
    
    function UserAuthenticate( $timeout=null ){
        
        // check for config timeout
        set_default($timeout,$this->_Config['timeout'],30);
        // check for login
        #die(print_r($_SESSION));
        if($_SESSION['cms5_username']!=''&&$_SESSION['cms5_activity']>time()-$timeout*60){
            // re-login for extended period
            #die('Re-login');
            //$this->UserLoad('guest');
            $this->UserLoad($_SESSION['cms5_username']);
            return true;
        }else{
            #die('Fail');
            $this->UserLogout();
            // guest may be required for login page and some actions
            $this->UserLoad('guest');
            return false;
        }
    }
    function UserLogin( $username, $password ){
        
        // check pw
        if(check_salted_md5($password,$this->_Config['users'][$username]['password'])){
            // correct username & password
            $_SESSION['cms5_logintime'] = time();
            $this->UserLoad($username);
            return true;
        }
        return false;
    }
    function UserLoad( $username ){
        
        // create sysadmin group -- for page list at top
        foreach((array)$this->_Config['pages'] as $page=>$title){
            $this->_Config['groups']['sysadmin']['pages'][] = $page;
        }
        
        $this->_User = $this->_Config['users'][$username];
        // resolve groups and pages
        //print_r($this->cms->user);
        //exit();
        foreach((array)$this->_User['groups'] as $group){
            foreach((array)$this->_Config['groups'][$group] as $k=>$perm){
                $this->_User['permissions'][$k] = array_unique(array_merge(
                    (array)$this->_User['permissions'][$k],
                    (array)$perm
                ));
            }
        }
        // sort out pages
        foreach($this->_User['permissions']['pages'] as $p){
            // add to title lookup (not req'd if use config
            
            if(!strstr($p,'.')){
                $this->_User['pages'][$p]['title']=$this->_Config['pages'][$p];
            }else{
                $bits=explode('.',$p);
                if(count($bits)==2&&isset($this->_User['pages'][$bits[0]])){
                    $this->_User['pages'][$bits[0]]['subpages'][$p]=$this->_Config['pages'][$p];
                }
            }
        }
        // we want to know the user's pages, with titles, and sub-pages
        
        
        // refresh login
        $_SESSION['cms5_username'] = $username;
        $_SESSION['cms5_activity'] = time();
        // refresh CMS
        $this->_User['options'] = $_SESSION['cms5_options'];
    }
    function UserLogout(){
        
        unset($_SESSION['cms5_username']);
        unset($_SESSION['cms5_activity']);
        unset($_SESSION['cms5_logintime']);
        unset($_SESSION['cms5_options']);
    }
    function ExtendSmarty(){
        
        $this->_CMS->smarty->plugins_dir[] = $this->_CMS->config['system']['app_dir'].'/plugins/controlpanel';
        $this->_CMS->smarty->plugins_dir[] = $this->_CMS->config['system']['system_dir'].'/plugins/controlpanel';
        // add the action results vars...
        #foreach($this->_User as $k=>$uv){
        #    $this->_CMS->smarty->assign($k,$uv);
        #}
        $this->_CMS->smarty->assign('user',$this->_User);
        $this->_CMS->smarty->assign('action_level',$this->_Action['level']);
        $this->_CMS->smarty->assign('action_msg',$this->_Action['message']);
        foreach($this->_Action as $k=>$action_var){
            $this->_CMS->smarty->assign('action_'.$k,$action_var);
        }
        $this->_CMS->smarty->assign('cp_pages',$this->_Config['pages']);
        // get tabs only
        $tabs=array();
        foreach($this->_User['pages'] as $url=>$tab){
            $tabs[$url]=$this->_Config['pages'][$url];
        }
        $this->_CMS->smarty->assign('cp_tabs',$tabs);
    }
    function ExecuteAction(){
        
        if($this->_Action['action']=='') return;
        if(in_array($this->_Action['action'],$this->_User['permissions']['actions']) ||
           in_array('sysadmin',$this->_User['groups'])){
            $action = explode('.',$this->_Action['action'],2);
            $file='/actions/controlpanel/action.'.$action[0].'.php';
            if(file_exists($this->_CMS->config['system']['app_dir'].$file)){
                require_once($this->_CMS->config['system']['app_dir'].$file);
                if(isset($action[1])){
                    call_user_func_array('action_'.$action[0].'_'.$action[1],array(&$this->_CMS,&$this));
                }
            }elseif(file_exists($this->_CMS->config['system']['system_dir'].$file)){
                require_once($this->_CMS->config['system']['system_dir'].$file);
                if(isset($action[1])){
                    call_user_func_array('action_'.$action[0].'_'.$action[1],array(&$this->_CMS,&$this));
                }
            }else{
                $this->_Action['level']='bad';
                $this->_Action['message']='Invalid Action: '.$this->_Action['action'].' (file missing)';
            }
        }else{
            $this->_Action['level']='bad';
            $this->_Action['message']='Invalid Action: '.$this->_Action['action'];
        }
    }
    
    // -- Smarty functions
    
    function smarty_placeholder($params, &$smarty){
        
        // check allowed
        if(in_array($params['page'],$this->_User['permissions']['pages'])){
            // assign anything..?
            return $smarty->fetch("controlpanel/{$params['page']}.tpl");
        }else{
            return $smarty->fetch("controlpanel/login.tpl");
        }
        
    }
    function smarty_setpage($params, &$smarty){
        
        // just set current tab etc.
        if(strstr($params['page'],'.')){
            $page=explode('.',$params['page'],2);
            $smarty->assign('thistab',$page[0]);
            $smarty->assign('thissubtab',$page[1]);
            $smarty->assign('cp_subpages',(array)$this->_User['pages'][$page[0]]['subpages']);
        }else{
            $smarty->assign('thistab',$params['page']);
            $smarty->assign('thissubtab','');
            $smarty->assign('cp_subpages',(array)$this->_User['pages'][$params['page']]['subpages']);
        }
        $smarty->assign('cp_page',$params['page']);
        $smarty->assign('cp_pagetitle',$this->_Config['pages'][$params['page']]);
    }
    
    // Gathering Useful Data
    
    function smarty_gettypes($params, &$smarty){
        
        $types=array();
        $sql = "SELECT DISTINCT `type` FROM `content` ORDER BY `type` ASC;";
        $q=mysql_query($sql,$this->_CMS->cms['db_conn']);
        while($r=@mysql_fetch_array($q)){
            $types[]=$r['type'];
        }
        $smarty->assign('all_datatypes',$types);
    }
    function smarty_gettemplates($params, &$smarty){
        
        $tps=array();
        // read dirs for tpls
        //preg_match_files($regex, $dir);
        // order results
        $smarty->assign('all_templates',$tps);
    }
    function smarty_getsections($params, &$smarty){
        
        $sects=array();
        // read dirs for sections
        // order results
        $smarty->assign('all_sections',$sects);
    }
}

?>

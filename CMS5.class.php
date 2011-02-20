<?php

/**
 *  Content Management System
 *  version 5.0.2
 **/

// -- Define Constants
define("ACTION_SUCCESS", 1);
define("ACTION_FAIL",    0);
define("ACTION_NOTICE",  2);

define("CMS_VERSION",    '5.0.2');

define("BREAK_SHOWPAGE",    1);
define("BREAK_LOADPAGE",    2);
define("BREAK_CONFIG_DB",   3);
define("BREAK_REGEX",       4);
define("BREAK_SMARTY",      5);
define("BREAK_EXTENSIONS",  6);

// -- Require Classes
//require_once(dirname(__FILE__).'/extensions/data.class.php');
//require_once(dirname(__FILE__).'/classes/secure.class.php');
//require_once(dirname(__FILE__).'/extensions/smartyCMS.class.php');
// -- Require Libraries
require_once('extend.lib.php');

// error handling
//require_once(dirname(__FILE__).'/extensions/extension.error_handler.php');
//set_error_handler("CMScustomError");
//echo($test);

// extension required for normal operation!
//require_once(dirname(__FILE__).'/extensions/extension.data.php');
// not really, some things are better optimised here..

// -- Main Class
class CMS5{
    
    var $config = array();
    var $request = array();
    var $cms = array();
    var $page = array();
    var $smarty;
    var $response;
    var $loop;
    
    function CMS5(){
        // load default config file
        require_once(dirname(__FILE__).'/default.config.php');
        $this->config = $config;
        // start authentication?
        // load user file? or db?
        $this->Log("initiated: version ".CMS_VERSION);
        $this->response = 200;
        $this->loop=0;
    }
    
    function ReadConfig($config_file,$db=false){
        // read app config file
        require_once($config_file);
        $this->config = array_merge_full((array)$this->config,(array)$config);
        $this->Log("loaded app: {$this->config['system']['app_name']}");
        if($this->config['system']['allow_debug']){
            if(isset($_REQUEST['debug'])){
                define('CMS_DEBUG',$_REQUEST['debug']);
            }
        }
        $this->page['site_id'] = $this->config['system']['site_id'];
        $this->Breakpoint(BREAK_CONFIG_DB);
        
        if($db){
            // now connect to DB
            $this->ConnectDB($this->config['system']['database']['server'],
                              $this->config['system']['database']['username'],
                              $this->config['system']['database']['password'],
                              $this->config['system']['database']['database'],true);
        }
    }
    
    function ConnectDB($server,$user,&$pass,$db,$load=false){
        // connect to a CMS DB
        $this->cms['db_conn'] = mysql_connect($server,$user,$pass);
        $pass='**********'; // hide whatever password was used..
        if(!$this->cms['db_conn']){
            die('Could not connect to Database Server.');
        }
        if(!mysql_select_db($db,$this->cms['db_conn'])){
            die('Could not connect to Database.');
        }
        $this->Log("db connected: {$user}@{$db}@{$server}");
        //destroy auth details
        #unset($this->config['database']['password']);
        #$this->config['database']['password']='YES';
        //$this->config['system']['database']['password']='**********';
        if($load){
            // now load db dite options
            $this->LoadDBConfig(true);
        }
    }
    
    function LoadDBConfig($run=false){
        // load options from db 
        $site_id = $this->config['system']['site_id'];
        $q=mysql_query("SELECT option_name,option_value FROM options WHERE site={$site_id}",$this->cms['db_conn']);
        while($r=@mysql_fetch_array($q)){
            $this->config['options'][$r['option_name']]=$r['option_value'];
        }
        $this->Log("options loaded: site {$site_id}");
        if($run){
            // now run system
            $this->DoCMS($_REQUEST[$this->config['system']['page_var']],
                          $_REQUEST[$this->config['system']['action_var']]);
            // this may change to allow /index.php/page_var or others
        }
    }
    
    function DoCMS($page=false,$action=false){
        $this->loop++;
        // perform the main CMS stuff
        // sanitise request variables?
        // this may be completely unnecessary
        // extend.php.lib
        $this->request['get']=unescape_post($_GET);
        $this->request['post']=unescape_post($_POST);
        $this->request['request']=unescape_post($_REQUEST);
        $this->request['cookie']=unescape_post($_COOKIE);
        // execute valid action?
        $this->request['action']=mysql_real_escape_string($action);
        $this->cms['action']=mysql_real_escape_string($action);
        if(file_exists($this->config['system']['app_dir'].'/actions/action.'.$action.'.php')){
            require_once($this->config['system']['app_dir'].'/actions/action.'.$action.'.php');
        }
        // load correct page
        $this->request['page'] = mysql_real_escape_string($page);
        // -- repeat from here if changing template (see Smarty404())
        $this->cms['this_page'] = $this->request['page'];
        //thispage not needed here, set it when successful page requested from DB..
        // one more request
        $this->request['uri'] = 'http://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        
        $this->LoadPage();
        $this->Breakpoint(BREAK_SMARTY);
        $this->SetupSmarty();
        $this->LoadExtensions();
        $this->Breakpoint(BREAK_SHOWPAGE);
        $this->ShowPage();
    }
    
    function LoadPage(){
        // check page validity, load page data
        // convenience variables
        $page = $this->cms['this_page'];
        $site_id = $this->config['system']['site_id'];
        $page404 = $this->config['system']['404page'];
        $this->Breakpoint(BREAK_LOADPAGE);
        
        // homepage or other page?
        if(preg_match('/^[\/]?$/',$page)&&!preg_match('/^[\/]?$/',$this->config['options']['home'])){
            // checked that page is blank and homepage is defined
            $this->Redirect($this->config['options']['home']);
            // redirected to homepage
            exit();
        }
        
        // load page data
        $q=mysql_query("SELECT ID,title,description,keywords,template,sections,extensions FROM pages WHERE site={$site_id} AND active=1 AND slug='$page'",$this->cms['db_conn']);
        if(!$r=@mysql_fetch_array($q)){
            // NEW: check each regex against request uri
            $q=mysql_query("SELECT ID,regex FROM pages WHERE site={$site_id} AND active=1 AND regex<>''",$this->cms['db_conn']);
            //while regexes...
            while($s=mysql_fetch_array($q)){
                // compare
                if(preg_match($s['regex'],$page,$match)){
                    $this->Log("regex: {$s['regex']}");
                    $this->Breakpoint(BREAK_REGEX);
                    $q=mysql_query("SELECT title,description,keywords,template,sections,extensions FROM pages WHERE site={$site_id} AND active=1 AND ID={$s['ID']}",$this->cms['db_conn']);
                    if(!$r=@mysql_fetch_array($q)){
                        break;
                        // continue with 404
                    }else{
                        $this->page['regex']=$s['regex'];
                        $this->request['regex']=$match;
                        // avoid 404 somehow..
                        if($page==$page404){ //unless is 404
                            header("HTTP/1.0 404 Not Found");$this->response=404;
                        }
                        // store page data
                        // but first fix template
                        $tp=explode(':',$r['template'],2);
                        $r['template']=$tp[0];
                        $r['template_page']=$tp[1];
                        $this->page=$r;
                        // add page regex
                        // parse section data (function in extend.lib.php)
                        $this->page['sections']=safe_unserialise($r['sections']);
                        $this->page['extensions']=safe_unserialise($r['extensions']);
                        // load page template
                        $this->PageTemplate();
                        // load page 'default' content
                        $q=mysql_query("SELECT content,description FROM content WHERE site={$site_id} AND name='{$s['ID']}' AND type='page'",$this->cms['db_conn']);
                        if(!$t=@mysql_fetch_array($q)){
                            // oops, no page content
                        }else{
                            $this->page['main_content']=$t['content'];
                            $this->page['secondary_content']=$t['description'];
                        }
                        return;
                    }
                }
            }
            
            $this->Log("error404: {$page}");
            $error = mysql_error($this->cms['db_conn']);
            $this->Log("sql: {$error}");
            header("HTTP/1.0 404 Not Found");$this->response=404;
            if($page==$page404){
                $this->Log("fatal: no 404 page");
                $this->Breakpoint();
                die('No 404 Page');
            }
            $this->cms['this_page'] = $page404;
            $this->LoadPage();
            return;
        }
        if($page==$page404){
            header("HTTP/1.0 404 Not Found");$this->response=404;
        }
        // store page data
        // but first fix template
        $tp=explode(':',$r['template'],2);
        $r['template']=$tp[0];
        $r['template_page']=$tp[1];
        $this->page=$r;
        // parse section data (function in extend.lib.php)
        $this->page['sections']=safe_unserialise($r['sections']);
        $this->page['extensions']=safe_unserialise($r['extensions']);
        // load page template
        $this->PageTemplate();
        // load page 'default' content
        $q=mysql_query("SELECT content,description FROM content WHERE site={$site_id} AND name='{$r['ID']}' AND type='page'",$this->cms['db_conn']);
        if(!$t=@mysql_fetch_array($q)){
            // oops, no page content
        }else{
            $this->page['main_content']=$t['content'];
            $this->page['secondary_content']=$t['description'];
        }
        $this->Breakpoint(5);
    }
    
    function PageTemplate(){
        
        if($this->page['template_page']!=''){
            $this->Log("Has Page Template");
            if(is_array($this->config['templates'][$this->page['template_page']])){
                $this->Log("Loading Page Template: {$this->page['template_page']}");
                foreach($this->config['templates'][$this->page['template_page']] as $ph=>$sections){
                    $this->page['sections'][$ph]=array_merge((array)$this->page['sections'][$ph],$sections);
                }
            }
        }
    }
    
    function Redirect($page){
        // perform sanitised redirect
        $this->Log("redirect: {$page}");
        $this->Breakpoint();
        header("HTTP/1.1 301 Moved Permanently");
        header('Location: '.$this->options['siteurl'].'/'.$page);
            // this must change to allow other addresses i.e. /index.php/home or /index.php?page=home
            // or is this already possible with removal of the slash above..?
            // this would cause a possible problem with sanitation
        exit();
        // exit PHP to close connection and force redirect
    }
    
    function SetupSmarty(){
        // configure smarty to be used with this app
        // should be done after action
        
        require_once($this->config['system']['smarty_class']);
        $this->smarty = new Smarty();
        $app_dir = $this->config['system']['app_dir'];
        
        // configure smarty
        $this->smarty->template_dir = array($app_dir.'/templates',
                                        dirname(__FILE__).'/templates');
        $this->smarty->caching = $this->config['system']['caching'];
        // !! tp_dir array is an undocumented 'feature'
        $this->smarty->compile_dir = $app_dir.'/templates/_compile';
        $this->smarty->cache_dir = $app_dir.'/templates/_cache'; // is it bad to mix these two?
        #$this->smarty->config_dir = $app_dir.'/configs'; // basically DB or config.php options
        $this->smarty->plugins_dir[] = $app_dir.'/plugins';
        $this->smarty->plugins_dir[] = dirname(__FILE__).'/plugins';
        
        
        // assign all relevant variables
        foreach((array)$this->config['options'] as $k=>$v){
                $this->smarty->assign($k,$v);
        }
        foreach((array)$this->config['info'] as $k=>$v){
                $this->smarty->assign($k,$v);
        }
        foreach((array)$this->user as $k=>$v){
            if(is_string($v)){
                $this->smarty->assign($k,$v);
            }
        }
        foreach((array)$this->page as $k=>$v){
            if(is_string($v)){
                $this->smarty->assign($k,$v);
            }
        }
        foreach((array)$this->request as $k=>$v){
            $this->smarty->assign($k,$v);
        }
        foreach((array)$this->action as $k=>$v){
            $this->smarty->assign($k,$v);
        }
        
        
        
        // register the important bits of this
        $this->smarty->register_block('placeholder_block',array(&$this,'smarty_placeholder_block'));
        $this->smarty->register_function('placeholder',array(&$this,'smarty_placeholder'));
        $this->smarty->register_function('content404',array(&$this,'smarty_content404'));
        $this->smarty->register_function('header404',array(&$this,'smarty_header404'));
        $this->smarty->register_function('redirect',array(&$this,'smarty_redirect'));
        $this->smarty->register_function('reassign',array(&$this,'smarty_reassign'));
        // string resource not needed in Smarty3
        $this->smarty->register_resource('str',array(&$this,'smarty_str_template','smarty_str_timestamp','smarty_str_secure','smarty_str_trusted'));
    }
    
    function ShowPage(){
        // use the template and show the page
        
        ob_start();
        $this->smarty->cache_lifetime = 60;#echo $this->request['uri'];
        $this->smarty->display($this->page['template'].'.tpl',$this->cms['this_page']);
        //if(defined('SMARTY404')&&($this->page!='404')){$this->loadPage('404');ob_end_clean();$this->initSmarty($smarty);return;}
        if(defined('CONTENT404')&&$this->cms['page']!=$this->config['system']['404page'])
        $this->Content404();
        if($this->config['system']['rebuild']){
            while($this->cms['redefined_count'] > 0){
                $this->Log("Repeating build process with new variables. ");
                $this->cms['redefined_count'] = 0;
                ob_end_clean();
                #define global sql vars
                foreach((array)$this->cms['redefined'] as $key=>$value){
                    $this->smarty->assign($key,$value);
                }
                ob_start();
                #$this->smarty->cache_lifetime = 3600;echo $this->request['uri']);
                $this->smarty->display($this->page['template'].'.tpl',$this->cms['this_page']);
            }
        }
        header_by_code($this->response);
        ob_end_flush();
        
        // clean-up, we should be all done
        mysql_close($this->cms['db_conn']);
    }
    
    function Breakpoint($value=false){
        // stop here and dump environment
        $this->Log("breakpoint: {$value}");
        if(defined('CMS_DEBUG')){
            if(!$value||CMS_DEBUG==$value){
                echo $this->cms['log'];
                $this;$_SERVER;
                #print_r($this);
                print_r($_SERVER);
                print_r(get_defined_vars());
                trigger_error("Breakpoint: $value");
                // replace the error handler with this-ish
                // use the 'notice' level for breakpoints
                exit();
            }
        }
    }
    
    function Content404(){
        // there was a problem with the template or data
        if(defined('CONTENT404') &&
          $this->cms['this_page'] != $this->config['system']['404page']){
            // need to clear buffers
            
            //header("HTTP/1.0 404 Not Found");$this->response=404;
            //$this->cms['this_page'] = $this->config['system']['404page'];
            // Reset CMS things
            // Break out of all buffers
            ob_end_clean_all();
            
            $this->DoCMS($this->config['system']['404page']);
            exit;
        }
    }
    
    function LoadExtensions(){
        
        // load page extensions from DB
        $this->Breakpoint(BREAK_EXTENSIONS);
        // merge config (global) extensions (includes default conf) and page exts
        $extensions = array_merge((array)$this->config['system']['extensions'],(array)$this->page['extensions']);
        foreach($extensions as $ext){
        #echo "Ext: ".dirname(__FILE__).'/extensions/extension.'.$ext.'.php'."<br />";
            if(file_exists(dirname(__FILE__).'/extensions/extension.'.$ext.'.php')){
                require_once(dirname(__FILE__).'/extensions/extension.'.$ext.'.php');
                #die(print_r($this));
                call_user_func_array('extension_'.$ext,array(&$this));
                //$this->extensions[$k] = new $classname($this);
            }
        }
    }
    
    function Log( $msg ){
        
        // basic logging
        if(!isset($this->cms['microtime'])){
            $this->cms['microtime'] = micro_time();
        }
        $time = round( micro_time() - $this->cms['microtime'], 5 );
        $this->cms['log'].='['.$time.'] '.$msg."\n";
    }
    
    // ----- Smarty Functions
    // init 404
    // placeholder
    
    function smarty_placeholder($params, &$smarty){
        
        $output='';
        foreach((array)$this->page['sections'][$params['name']] as $s){
            #$smarty->assign('section_data',$s['data']);
            #$smarty->assign('section_type',$s['type']);
            #$smarty->assign('section_name',$s['section']);
            // fetch data now... think about caching later
            
            // now assign other vars
            #unset($s['data']);
            #unset($s['type']);
            #unset($s['section']);
            foreach($s as $k=>$var){
                $smarty->assign('section_'.$k,$var);
            }
            
            // build section using data etc..
            #$smarty->caching=0; # this stops bad things
            $output.=$smarty->fetch("sections/{$s['type']}.{$s['section']}.tpl");
        }
        // Thoughts..
        /*
          Placeholder should fetch data for the section template.
          But some section templates want more than 1 record entry.
          I.e., top 3 news..
        */
        
        return $output;
        
    }
    
    function smarty_placeholder_block($params, $content, &$smarty, &$repeat){
        
        if(count((array)$this->page['sections'][$params['name']])>0){
            return $content;
        }
        return '';
        
    }
    
    function smarty_content404($params, &$smarty){
        
        safe_define('CONTENT404', TRUE);$this->response=404; // ext.lib
    }
    
    function smarty_header404($params, &$smarty){
        
        header("HTTP/1.0 404 Not Found");$this->response=404;
    }
    
    function smarty_redirect($params, &$smarty){
        
        $this->Redirect($params['page']);
    }
    
    function smarty_reassign($params, &$smarty){
        
        // these will be redefined instantly, and redefined globally on second run.
        if(isset($this->cms['redefined_history'][$params['name']])){
            if(in_array($params['value'],$this->cms['redefined_history'][$params['name']])){
                $this->Log("Value previously used for {$params['name']}.");
            }else{
                $this->cms['redefined_count'] ++;
                $this->cms['redefined'][$params['name']] = $params['value'];
                $this->cms['redefined_history'][$params['name']][] = $params['value'];
            }
        }else{
            $this->cms['redefined_count'] ++;
            $this->cms['redefined'][$params['name']] = $params['value'];
            $this->cms['redefined_history'][$params['name']][] = $params['value'];
        }
        $smarty->assign($params['name'],$params['value']);
    }
    
    function smarty_str_template($tpl_name, &$tpl_source, &$smarty){$tpl_source=$tpl_name;return true;}
    function smarty_str_timestamp($tpl_name, &$tpl_timestamp, &$smarty){$tpl_timestamp=time();return true;}
    function smarty_str_secure($tpl_name, &$smarty){return true;}
    function smarty_str_trusted($tpl_name, &$smarty){}
    
}


?>

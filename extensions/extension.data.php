<?php

function extension_data( &$cms ){
    
    // create the objects
    $content = new cmsContent($cms);
    $cms->smarty->register_object('cmscontent',$content,array('top3','getpage','advanced'));
    $option = new cmsOption($cms);
    $cms->smarty->register_object('cmsoption',$option,array('advanced'));
    $page = new cmsPage($cms);
    $cms->smarty->register_object('cmspage',$page,array('advanced'));
    #$this->smarty->register_function('get_advanced',array(&$content,'advanced'));
    // could use the above instead...
    // how about the raw thingys
    //$cms->smarty->assign...('cmscontent',$content,array('top3','getbyid','getpage'));
    
    // assign some useful arrays
    # templates (from dir)
    $opts=array();
    require_once('dir.lib.php');
    $d1=dir_list($cms->config['system']['app_dir'].'/templates');
    $d2=dir_list($cms->config['system']['system_dir'].'/templates');
    $ts=array_unique(array_merge($d1['file'],$d2['file'])); #files only..
    foreach($ts as $v){$v=str_replace('.tpl','',$v);$opts[$v]=$v;}
    #print_r($opts);
    $cms->smarty->assign('cp_page_templates',$opts);
    # layouts (from config)
    $opts=array();
    foreach($cms->config['templates'] as $k=>$v){$opts[$k]=$k;}
    $cms->smarty->assign('cp_page_layouts',$opts);
    # sections? (from dir)
    $opts=array();
    #foreach($cms->config['templates'] as $k=>$v){$opts[$k]=$k;}
    $cms->smarty->assign('cp_page_sections',$opts);
    # content states
    $cms->smarty->assign('cp_content_states',array('active'=>'active','inactive'=>'inactive','pending'=>'pending'));
}

/**

  Notes about CMS Data objects
  
  Objects:
   * Options
   * Pages
   * Content
   * Users (used for detail lookups ony, login handled by config file for now)
   * Files (not in use yet, may use later if 'content' not suitable)
  
  Actions:
   * New
   * Update
   * Modify Specifically
   * Delete
   * Lookup
  
  Information:
   * ID/name/date
   * Owner
  
  Usage:
   $opt = new cmsOption($cms);
   $opt->SetSite(0);
   $opt->GetByName('siteurl');
   
   $opt->GetAll(); // alias of $opt->Fetch();
   
   $opt->SetByName();
  
  ToDo:
   * Page Section Manipulation (see content meta)
   * Get Page by Regex
   * Can't Delete Meta Data
   * Always returns Arrays
   * Doesn't actually do anything yet
   * Should probably use &$conn, not &$cms
   * Check that loop bit (see meta_update)
   * Finish Docs
  
  Docs:
   common:
    - SetSite( $site_id )
   cmsOption
    - GetByName( $name )
    - 
    
  Possible Bug!
    Limit on MikArtistik.com (sidealbums) does not seem to work.

  Quirks:
    Feature to return empty arrays but instead list all SQL for TP to manage.

**/

define('CMS_DATA_SELECT',0);
define('CMS_DATA_INSERT',1);
define('CMS_DATA_UPDATE',2);
define('CMS_DATA_DELETE',3);
define('CMS_UPDATE_ID',1); // what about other keys? - answer: you know them already
define('CMS_RESULT_ARRAY',2);
define('CMS_AFFECTED_ROWS',3);

define('CMS_TABLE_OPTIONS','options');
define('CMS_TABLE_PAGES','pages');
define('CMS_TABLE_CONTENT','content');

define('CMS_SLUG_REPLACE','-');

define('CMS_ANY_AUTHOR','-');
define('CMS_DEFAULT_DATE_FIELD','date');


// -- PHP4 compatible classes

class cmsDataObject {

    var $_CMS;
    
    var $_Link;
    var $_Table;
    var $_Values;
    var $_Keys;
    var $_Like;
    var $_RLike;
    var $_Date;
    
    var $_Limit;
    var $_OrderBy;
    var $_Offset;
    
    var $_SQL;
    var $_Quirk;
    
    var $_UTF8;
    
    function cmsDataObject( &$cms ){ // PHP4 constructor
        
        $this->_CMS = &$cms;
        $this->_Link = &$cms->cms['db_conn'];
        $this->_Limit=0;
        $this->_Offset=0;
        $this->_Quirk = false;
        
        $this->_UTF8 = true;
    }
    
    function Reset( ){
        
        //$this->_Link=NULL; //really..?
        //$this->_Table=NULL;
        $this->_Values=NULL;
        $this->_Keys=NULL;
        $this->_Like=NULL;
        $this->_RLike=NULL;
        $this->_Date=NULL;
        $this->_Limit=NULL;
        $this->_OrderBy=NULL;
        $this->_Offset=NULL;
        $this->_SQL=NULL;
        #$tmp=(object)array('cms'=>array('db_conn'=>&$this->_Link));
        #$this->cmsDataObject($tmp);
        $this->_Limit=0;
        $this->_Offset=0;
        // do not change Quirk, CMS, or Link
    }
    
    function Select(){
        
        $this->_Prepare(CMS_DATA_SELECT);
        $r = $this->_Execute(CMS_RESULT_ARRAY);
        return $r;
    }
    
    function Insert(){
        
        $this->_Prepare(CMS_DATA_INSERT);
        $id = $this->_Execute(CMS_UPDATE_ID);
        return $id;
    }
    
    function Update(){
        
        $this->_Prepare(CMS_DATA_UPDATE);
        $e = $this->_Execute(CMS_AFFECTED_ROWS);
    }
    
    function _Delete(){
        
        $this->_Prepare(CMS_DATA_DELETE);
        $e = $this->_Execute(CMS_AFFECTED_ROWS);
    }
    
    // more features
    function SetLimit( $limit ){
        
        $limit = intval($limit);
        $this->_Limit = $limit;
    }
    function SetOrder( $field, $ord='ASC' ){
        
        if(!in_array($ord,array('ASC','DESC'))){ return false; }
        $field = mysql_real_escape_string($field);
        $this->_OrderBy[] = array($field,$ord);
    }
    function SetOffset( $offset ){
        
        $offset = intval($offset);
        $this->_Offset = $offset;
    }
    
    // utilities
    function _Prepare( $action=CMS_DATA_SELECT ){
        
        if($this->_Limit>0){ $limit = " LIMIT {$this->_Limit}"; }
          else{ $limit=''; }
        if($this->_Offset>0){ $limit.= " OFFSET {$this->_Offset}"; }
        
        if(count($this->_OrderBy)>0){
            $order=' ORDER BY ';
            $os=array();
            foreach($this->_OrderBy as $o){
                $os[]=implode(' ',$o);
            }
            $order.=implode(',',$os);
        }else{ $order=''; }
        
        switch($action){
            case CMS_DATA_SELECT:
                $where = $this->_PrepareWhere();
                $sql = "SELECT * FROM `{$this->_Table}`{$where}{$order}{$limit};";
                break;
            case CMS_DATA_INSERT:
                $keys = '(`'.implode('`,`',array_keys($this->_Values)).'`)';
                $values=array();
                foreach($this->_Values as $v){
                    if(is_int($v)||is_float($v)){ $q=''; }
                    else{
                        $q='\'';
                        if($this->_UTF8){ /*$v=iconv_UTF8($v); -- BUG: Not supported */ }
                    }
                    $values[]="$q$v$q";
                }
                $values = '('.implode(',',$values).')';
                $sql = "INSERT INTO `{$this->_Table}` {$keys} VALUES {$values};";
                break;
            case CMS_DATA_UPDATE:
                $where = $this->_PrepareWhere();
                $values=array();
                foreach($this->_Values as $k=>$v){
                    if(is_int($v)||is_float($v)){ $q=''; }
                    else{
                        $q='\'';
                        if($this->_UTF8){ /*$v=iconv_UTF8($v); -- BUG: not supported */ }
                    }
                    $values[]="`$k`=$q$v$q";
                }
                $values = implode(',',$values);
                $sql = "UPDATE `{$this->_Table}` SET {$values}{$where}{$limit};";
                break;
            case CMS_DATA_DELETE:
                $where = $this->_PrepareWhere();
                $sql = "DELETE FROM `{$this->_Table}`{$where}{$limit};";
                break;
        }
        $this->_SQL = $sql;
    }
    function _PrepareWhere(){
        
        if(count($this->_Keys)>0){
            $keys=array();
            foreach($this->_Keys as $k=>$v){
                if(is_int($v)||is_float($v)) $q='';
                    else $q='\'';
                $keys[]="`$k`=$q$v$q";
            }
            foreach((array)$this->_Like as $l){
                $keys[]="`{$l[0]}` LIKE '{$l[1]}'";
            }
            foreach((array)$this->_RLike as $l){
                $keys[]="`{$l[0]}` RLIKE '{$l[1]}'";
            }
            if(count($this->_Date)>0){
                foreach($this->_Date as $k=>$date){
                    //$date=strtotime($date);
                    if(isset($date['day'])){
                        $date1=date('Y-m-d',$date);
                        $date2=date('Y-m-d',$date+60*60*24); // next day
                        $keys[]="`{$k}`>='{$date1}' AND `{$k}`<'{$date2}'";
                    }
                    if(isset($date['from'])){
                        $date1=date('Y-m-d',$date['from']);
                        $keys[]="`{$k}`>='{$date1}'";
                    }
                    if(isset($date['from'])){
                        $date2=date('Y-m-d',$date['to']);
                        $keys[]="`{$k}`<'{$date2}'";
                    }
                }
            }
            return ' WHERE '.implode(' AND ',$keys);
        }
    }
    function _Execute( $return=CMS_RESULT_ARRAY ){
        
        //echo $this->_SQL;
        $md5 = md5($this->_SQL);
        if(isset($this->_CMS->cms['sql_cache'][$md5])){
            return $this->_CMS->cms['sql_cache'][$md5];
        }
        
        $q=mysql_query($this->_SQL);
        
        switch($return){
            case CMS_RESULT_ARRAY:
                $result=array();
                while($r=mysql_fetch_array($q,$this->_Link)){
                    if($this->_UTF8){
                        foreach($r as $k=>$f){
                            #$r[$k] = iconv_UTF8($f); -- BUG: not included in all servers
                            $r[$k] = $f;
                        }
                    }
                    $result[]=$r;
                }
                $this->_CMS->cms['sql_cache'][$md5] = $result;
                return $result;
                break;
            case CMS_UPDATE_ID:
                $this->_CMS->cms['sql_cache'][$md5] = mysql_insert_id($this->_Link);
                return $this->_CMS->cms['sql_cache'][$md5];
                break;
            case CMS_AFFECTED_ROWS:
                $this->_CMS->cms['sql_cache'][$md5] = mysql_affected_rows($this->_Link);
                return $this->_CMS->cms['sql_cache'][$md5];
                break;
        }
        
        return false;
    }
    
    function Compatible( $on ){
        
        $this->_Compatibility = $on;
    }
}


class cmsOption extends cmsDataObject {
    
    // constructor
    function cmsOption( &$cms ){
    
        parent::cmsDataObject( $cms );
        $this->_Table = CMS_TABLE_OPTIONS;
        $this->SetSite(0);
    }
    
    // definition
    function SetSite( $site ){
        
        $site = intval($site);
        $this->_Keys['site'] = $site;
    }
    
    // actions
    function GetByName( $name ){
        
        $name = mysql_real_escape_string($name);
        $this->_Keys['option_name'] = $name;
        return $this->GetAll();
    }
    function GetByID( $id ){
        
        $id = intval($id);
        $this->_Keys['ID'] = $id;
        return $this->GetAll();
    }
    function GetByPart( $name ){
        // Use in main CMS class
        $name = mysql_real_escape_string($name);
        $this->_Like[] = array('option_name', $name.'%');
        return $this->GetAll();
    }
    function GetAll(){
        
        return $this->Select();
    }
    // descructive
    function InsertByName( $name, $value ){
        return $this->UpdateByName($name,$value,true);
    }
    function UpdateByName( $name, $value, $new=false ){
        
        $name = mysql_real_escape_string($name);
        $value = mysql_real_escape_string($value);
        $this->_Keys['option_name'] = $name;
        $this->_Values['option_value'] = $value;
        if($new){
            $this->_Values['site'] = $this->_Keys['site'];
            $this->_Values['option_name'] = $name;
            return $this->Insert();
        }else{
            return $this->Update();
        }
    }
    function UpdateByID( $id, $value ){
        
        $id = intval($id);
        $value = mysql_real_escape_string($value);
        $this->_Keys['ID'] = $id;
        $this->_Values['option_value'] = $value;
        return $this->Update();
    }
    function DeleteOption( $name ){
        
        $name = mysql_real_escape_string($name);
        $this->_Keys['option_name'] = $name;
        return $this->_Delete();
    }
    function DeleteAll( $really=false ){ // per site
        
        if($really) return $this->_Delete();
        else return false;
    }
    
    
    function advanced($params, &$smarty) {
        
		// Special Mode to globally load DB Data
		// modes: [1-listvars] [2-load globally] [3-do nothing]
		// modes: [1-normal] [2-pre-fetch-listing]
		
        $this->Reset();
        
        // fixed
        $this->SetSite($this->_CMS->config['system']['site_id']);
        // defaults
        set_default($params['as'],'option');
        
        if(isset($params['name'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByName($params['name'])),$smarty);
        }elseif(isset($params['id'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByID($params['id'])),$smarty);
        }elseif(isset($params['part'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByPart($params['part'])),$smarty);
        }elseif(isset($params['group'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByPart($params['group'])),$smarty);
        }else{
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetAll()),$smarty);
        }
    }
    
}


class cmsPage extends cmsDataObject {
    
    // constructor
    function cmsPage( &$cms ){
    
        parent::cmsDataObject( $cms );
        $this->_Table = CMS_TABLE_PAGES;
        $this->SetSite(0);
        $this->SetActive(1);
    }
    
    // definition
    function SetSite( $site ){
        
        $site = intval($site);
        $this->_Keys['site'] = $site;
    }
    function SetActive( $active=1 ){
        
        if($active=='any'){ unset($this->_Keys['active']); return true; }
        $active = intval($active);
        $this->_Keys['active'] = $active;
    }
    function SetValue( $key, $value ){
        
        $fields = array('slug','title','description','keywords','template','sections','active','regex');
        if(!in_array($key,$fields)) return false;
        switch($key){
            case 'slug':
                $value=preg_replace('/[^a-z0-9_\-\/\.]+/i','-',$value);
                break;
            case 'template':
                $value=strtolower($value);
                $value=preg_replace('/[^a-z0-9_\-\.:]/','',$value); // modified to allow 'smartytp:cmstp'
                // consider updating to allow similar to content meta (i.e., editing parts)
                break;
            case 'active':
                $value=intval($value);
                break;
            case 'sections':
                if(($v=json_decode($value))==null){
                    $value=$v;
                }
                if(is_array($value)){
                    $value=serialize($value);
                }
                // current format:
                //   placeholder(
                //     section(data),section(data),..),
                //   placeholder(
                //     section(data),section(data),..),
                //   ..
                // conside similar to content_meta
                break;
        }
        $value=mysql_real_escape_string($value);
        $this->_Values[$key]=$value;
    }
    function AddSection( $placeholder, $section, $data_id ){
        
        // should this be per-loaded and modified or just overwritten...?
    }
    
    // actions
    function GetBySlug( $name ){
        
        $name = mysql_real_escape_string($name);
        $this->_Keys['slug'] = $name;
        return $this->GetAll();
    }
    function GetByID( $id ){
        
        $id = intval($id);
        $this->_Keys['ID'] = $id;
        return $this->GetAll();
    }
    function GetByRegex( $name ){
        // use this in main CMS
        // todo..
        //return $this->Fetch();
    }
    function GetAll(){
        
        $r = $this->Select();
        foreach((array)$r as $k=>$v){
            $r[$k]['sections']=unserialize(base64_decode($v['sections']));
            $tp=explode(':',$v['template'],2);
            $r[$k]['template_tp']=$tp[0];
            $r[$k]['template_layout']=$tp[1];
        }
        return $r;
    }
    // descructive
    function InsertNew(){
        
        $this->_Values['site'] = $this->_Keys['site'];
        return $this->Insert();
    }
    function UpdateByID( $id ){
        
        $id = intval($id);
        $this->_Keys['ID'] = $id;
        return $this->Update();
    }
    function DeletePage( $id ){
        
        $id = intval($id);
        $this->_Keys['ID'] = $id;
        return $this->_Delete();
    }
    function DeleteAll($really=false){ // whole site
        
        if($really) return $this->_Delete();
        else return false;
    }
    
    
    function advanced($params, &$smarty) {
        
        $this->Reset();
        
        // fixed
        $this->SetSite($this->_CMS->config['system']['site_id']);
        // defaults
        set_default($params['as'],'data');
        
        if(isset($params['id'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByID($params['id'])),$smarty);
        }elseif(isset($params['slug'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetBySlug($params['alug'])),$smarty);
        }else{
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetAll()),$smarty);
        }
    }
    
}


class cmsContent extends cmsDataObject {
    
    // vars
    var $meta_content;
    
    // constructor
    function cmsContent( &$cms ){
    
        parent::cmsDataObject( $cms );
        $this->_Table = CMS_TABLE_CONTENT;
        $this->SetSite(0);
        $this->SetStatus('active'); // protect
        $this->SetAuthor(0);
    }
    
    function Reset(){
        
        $this->meta_content=NULL;
        parent::Reset();
    }
    
    // definition
    function SetSite( $site ){
        
        $site = intval($site);
        $this->_Keys['site'] = $site;
    }
    function SetAuthor( $author ){
        
        if($author==CMS_ANY_AUTHOR){ unset($this->_Keys['author']); return true; }
        $author=strtolower($author);
        $author=preg_replace('/[^a-z0-9]+/','',$author);
        $author=mysql_real_escape_string($author);
        $this->_Keys['author'] = $author;
    }
    function SetDate( $date, $field='date' ){
        
        $date=strtotime($date);
        if(in_array($field,array('date','modified'))){
            $this->_Date[$field]['day'] = $date;
        }
    }
    function SetDateRange( $from, $to, $field='date' ){
        
        $from=strtotime($from);
        $to  =strtotime($to);
        if(in_array($field,array('date','modified'))){
            $this->_Date[$field] = array('from'=>$from,'to'=>$to);
        }
    }
    function SetDateFrom( $from, $field='date' ){ // note: greater than or ewual to (day)
        
        $from=strtotime($from);
        if(in_array($field,array('date','modified'))){
            $this->_Date[$field]['from'] = $from;
        }
    }
    function SetDateTo( $to, $field='date' ){ // note: strictly less than (day)
        
        $to  =strtotime($to);
        if(in_array($field,array('date','modified'))){
            $this->_Date[$field]['to'] = $to;
        }
    }
    function SetStatus( $status='active' ){
        
        $enum = array('active','pending','inactive','any');
        if(!in_array($status,$enum)) return false;
        if($status=='any'){ unset($this->_Keys['status']); return true; }
        $status=mysql_real_escape_string($status);
        $this->_Keys['status'] = $status;
    }
    function SetName( $name ){
        
        $name=strtolower($name);
        $name=preg_replace('/[^a-z0-9\-]+/','-',$name);
        $name=mysql_real_escape_string($name);
        $this->_Keys['name'] = $name;
    }
    function SetType( $type ){
        
        $type=strtolower($type);
        $type=preg_replace('/[^a-z]+/','',$type);
        $type=mysql_real_escape_string($type);
        $this->_Keys['type'] = $type;
    }
    function SetMeta( $meta, $contains ){
        
        // should escape the regex here
        $this->_RLike[] = array('meta_content',"\"$meta\";s:[0-9]+:\"[^\"]*$contains");
    }
    
    // loads more to do
    // select, insert, update, delete
    
    function SetValue( $key, $value ){
        
        $fields = array('author','date','title','content','description','status','type');
        if($this->_Compatibility=='DB'){$fields=array_merge($fields,array('meta_oldc3','meta_oldc4'));}else{
        if(substr($key,0,5)=='meta_'){ $fields[]=$key;$caseX=$key; }else{ $caseX=''; } /*Compat*/ }
        if(!in_array($key,$fields)) return false;
        switch($key){
            case 'author':
                $value=strtolower($value);
                $value=preg_replace('/[^a-z0-9]+/','',$value);
                break;
            case 'date':
                $value=strtotime($value);
                $value=date('Y-m-d H:i:s',$value);
                break;
            case 'status':
                $enum = array('active','pending','inactive');
                if(!in_array($value,$enum)) return false;
                break;
            case 'title':
                // this also sets name
                $name=strtolower($value);
                $name=preg_replace('/[^a-z0-9\-]+/','-',$name);
                $name=mysql_real_escape_string($name);
                $this->_Values['name']=$name;
                break;
            case 'type':
                $type=strtolower($type);
                $type=preg_replace('/[^a-z]+/','',$type);
                break;
            case $caseX:
                $this->meta_content[$key]=$value;
                // nothing to set yet (see UpdateByID)
                return true;
                break;
            // compatibility
            case 'content':
                if($this->_Compatibility=='DB'){
                    $key='content_1';
                }
                break;
            case 'description':
                if($this->_Compatibility=='DB'){
                    $key='content_2';
                }
                break;
            case 'meta_oldc3':
                if($this->_Compatibility=='DB'){
                    $key='content_3';
                }
                break;
            case 'meta_oldc4':
                if($this->_Compatibility=='DB'){
                    $key='content_4';
                }
                break;
        }
        $value=mysql_real_escape_string($value);
        $this->_Values[$key]=$value;
    }
    
    // actions
    function GetByID( $id ){
        
        $id = intval($id );
        $this->_Keys['ID'] = $id;
        return $this->GetAll();
    }
    function GetByName( $name ){
        
        $name=strtolower($name);
        $name=preg_replace('/[^a-z0-9\-]+/','-',$name);
        $name=mysql_real_escape_string($name);
        $this->_Keys['name'] = $name;
        return $this->GetAll();
    }
    function GetAll(){
        
        $r = $this->Select();
        foreach((array)$r as $k=>$v){
            //$r[$k]['meta_content']=unserialize(base64_decode($v['meta_content']));
            #$meta_content=unserialize(base64_decode($v['meta_content']));
            $meta_content=unserialize($v['meta_content']);
            #print_r($meta_content);
            $r[$k]['meta_content']=array(); // reset result
            foreach((array)$meta_content as $l=>$w){
                $r[$k]['meta_content'][$l]=$w; // useful for merging
                $r[$k][$l]=$w; // real useful bit
            }
        }
        if($this->_Compatibility){
            if(isset($r[$k]['content_1'])){
                $r[$k]['content']=$r[$k]['content_1'];
                $r[$k]['description']=$r[$k]['content_2'];
                $r[$k]['meta_oldc3']=$r[$k]['content_3'];
                $r[$k]['meta_oldc4']=$r[$k]['content_4'];
            }else{
                $r[$k]['content_1']=$r[$k]['content'];
                $r[$k]['content_2']=$r[$k]['description'];
                $r[$k]['content_3']=$r[$k]['meta_oldc3'];
                $r[$k]['content_4']=$r[$k]['meta_oldc4'];
            }
        }
        return $r;
    }
    // descructive
    function InsertNew(){
        
        $this->_Values['site'] = $this->_Keys['site'];
        if(count((array)$this->meta_content)>0){
            $this->_Values['meta_content'] = serialize($this->meta_content);
        }
        $this->_Values['modified'] = date('Y-m-d H:i:s',time());
        return $this->Insert();
    }
    function UpdateByID( $id ){
        
        $id = intval($id);
        $this->_Keys['ID'] = $id;
        // now we know ID, decode meta and merge it
        if(count((array)$this->meta_content)>0){
            // fetch old meta data
            $cmslink = (object)array('cms'=>array('db_conn'=>&$this->_Link));
            $old = new cmsContent($cmslink); // oops, just pass link instead? - No! we now need full $cms for caching
            $old->SetSite($this->_Keys['site']);
            $old->SetAuthor(CMS_ANY_AUTHOR);
            $old->SetStatus('any');
            $r = $old->GetByID( $id );
            
            $new_meta = serialize(array_merge((array)$r[0]['meta_content'],$this->meta_content));
            $this->_Values['meta_content'] = $new_meta;
        }
        $this->_Values['modified'] = date('Y-m-d H:i:s',time());
        return $this->Update();
    }
    function DeleteContent( $id ){
        
        $id = intval($id);
        $this->_Keys['ID'] = $id;
        return $this->_Delete();
    }
    function DeleteAll($really=false){ // whole site
        
        if($really) return $this->_Delete();
        else return false;
    }
    
    // -- Smarty Functions
    
    function top3($params, &$smarty) {
        
        $this->Reset();
        // set defaults/requirements
        if(!isset($params['type'])) return false;
        set_default($params['top'],3);
        set_default($params['as'],'data');
        // set up the query
        $this->SetSite($this->_CMS->config['system']['site_id']);
        $this->SetAuthor(CMS_ANY_AUTHOR);
        $this->SetType($params['type']);
        $this->SetLimit($params['top']);
        $this->SetOrder('date','DESC');
        // evaluate the query
        $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetAll()),$smarty);
        // maybe return as type..?
    }
/*    function getbyid($params, &$smarty) {
        
        $this->Reset();
        // set defaults/requirements
        if(!isset($params['id'])) return false;
        if(!isset($params['type'])) return false;
        set_default($params['as'],'data');
        // set up the query
        $this->SetSite($this->_CMS->config['system']['site_id']);
        $this->SetAuthor(CMS_ANY_AUTHOR);
        $this->SetType($params['type']);
        // evaluate the query
        $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByID($params['id'])),$smarty);
        // maybe return as type..?
    }*/
    function getpage($params, &$smarty) {
        
        $this->Reset();
        // set defaults/requirements
        if(!isset($params['type'])) return false;
        set_default($params['limit'],3);
        set_default($params['as'],'data');
        set_default($params['page'],1);
        set_default($params['author'],CMS_ANY_AUTHOR);
        // set up the query
        $this->SetSite($this->_CMS->config['system']['site_id']);
        $this->SetAuthor($params['author']);
        $this->SetType($params['type']);
        $this->SetLimit($params['limit']);
        $this->SetOffset(($params['page']-1)*$params['limit']);
        $this->SetOrder('date','DESC');
        // evaluate the query
        $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetAll()),$smarty);
        // maybe return as type..?
    }
    function advanced($params, &$smarty) {
        
        $this->Reset();
        /*
    function SetSite( $site ){
    function SetAuthor( $author ){
    function SetDate( $date, $field='date' ){
    function SetDateRange( $from, $to, $field='date' ){
    function SetStatus( $status='active' ){
    function SetName( $name ){
    function SetType( $type ){
    // more features
    function SetLimit( $limit ){
    function SetOrder( $field, $ord='ASC' ){
    function SetOffset( $offset ){
    // actions
    function GetByID( $id ){
    function GetAll(){
        */
        // fixed
        $this->SetSite($this->_CMS->config['system']['site_id']);
        // defaults
        set_default($params['as'],'data');
        set_default($params['author'],CMS_ANY_AUTHOR);
        set_default($params['datefield'],'date');
        set_default($params['order'],'ASC');
        
        $this->SetAuthor($params['author']);
        // optional
        if(isset($params['date'])){
            $this->SetDate($params['date'],$params['datefield']);
        }
        if(isset($params['datefrom'])){
            $this->SetDateFrom($params['datefrom'],$params['datefield']);
        }
        if(isset($params['dateto'])){
            $this->SetDateTo($params['dateto'],$params['datefield']);
        }
        if(isset($params['name'])){
            $this->SetName($params['name']);
        }
        if(isset($params['type'])){
            $this->SetType($params['type']);
        }
        if(isset($params['limit'])){
            $this->SetLimit($params['type']);
            if(isset($params['offset'])){
                $this->SetOffset($params['offset']);
            }
            if(isset($params['page'])){
                $this->SetOffset((intval($params['page'])-1)*intval($params['limit']));
            }
        }
        if(isset($params['orderfield'])){
            $this->SetOrder($params['orderfield'],$params['order']);
        }### Update for multiple orders, also maybe {.. order='name,DESC'}
         ###                             or {.. orderfield='name,ref,provider'}
         ###  have to update this to use php_multisort for meta_fields
        // get
        if(isset($params['id'])){
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetByID($params['id'])),$smarty);
        }else{
            $this->_CMS->smarty_reassign(array('name'=>$params['as'],'value'=>$this->GetAll()),$smarty);
        }
        // usage:
        //{cmscontent->advanced [author='any'] [date='' [datefield='date'] | datefrom='' dateto='' [datefield='date']] [name='''] [type=''] [limit='' [offset='' | page='']] [orderfield='' [order='ASC']] [id=''] [as='data']}
    }
}





/*

// -- CP accessible functions

function cp_update_update(&$cms){ // default 'update' function

    $rq = $cms->request['post']; // or get, or request, or cookie (for all)
    
    // do some magic
    // done
}

function cp_update_option( &$cms ){
    
    $obj = new cmsOptions($cms);
    
    //$obj->WhereID();
    $obj->OptionName($cms->request['post']['name']); // OR
    $obj->OptionID($cms->request['post']['id']); // THEN
    $obj->OptionValue($cms->request['post']['value']);
    
    if($cms->request['post']['new']=='new'){
        $obj->New();
    }else{
        $obj->Update();
    }
}


// -- CMS data classes (PHP5 only)

class cmsDataObject {
    
    private $_Link;
    private $_State; // 0-new, 1-ready, 2-done
    private $_Table;
    private $_Data;
    private $_Where;
    
    public function __construct( &$cms ) {
        $this->_Link = $cms->cms['dbconn'];
        $this->_State = 0;
    }
    
    // this will be called automatically at the end of scope
    public function __destruct() {
        
        if($this->_State<2){
            // auto-detect new status
            // update DB
        }
        //mysql_close( $this->_Link );
    }
    
    public function New() {
        
        // form SQL for new (use extend.lib.php)
        $sql = extend_fn();
        $q = mysql_query($sql);
    }
    public function Update() {
        
        // form SQL for new (use extend.lib.php)
        $sql = extend_fn();
        $q = mysql_query($sql);
    }
}

class cmsOptions extends cmsDataObject {
    
    //private $_Name;
    //private $_ID;
    //private $_Value;
    private $_
    
    public function __construct() {
        parent::__construct();
        $this->_Table = 'options';
    }
    
    // give ID first => set _Where['ID']=$id;
    // give Name first => set
    
    
    public function OptionName($name){
        //$this->_Name = $name;
        $this->_Data['option_name'] = $name;
    }
    public function OptionID($id){
        $this->_ID = $id;
    }
    public function OptionValue($value){
    
        $this->_Data['option_value'] = $value;
        
        $this->_Value = $value;
        if($this->_Name != ''){
            $this->_Data['']
        }elseif($this->_Name != ''){
            
        }
    }
}

*/

?>

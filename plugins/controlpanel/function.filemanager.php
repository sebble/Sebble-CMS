<?php

function smarty_function_filemanager($params, &$smarty){
    
    require_once('dir.lib.php');
    require_once('extend.lib.php');
    
    // Defaults
    $params['newfile']      = (isset($params['newfile'])) ? $params['newfile'] : 'true' ;
    $params['newfolder']    = (isset($params['newfolder'])) ? $params['newfolder'] : 'true' ;
    $params['upload']       = (isset($params['upload'])) ? $params['newfolder'] : 'true' ;
    $params['compress']     = (isset($params['compress'])) ? $params['compress'] : 'false' ;
    $params['home']         = (isset($params['home'])) ? $params['home'] : $params['root'] ;
    $params['editable']     = (isset($params['editable'])) ? $params['editable'] : 'txt,html,php,css,htaccess';
    $params['thispage']     = (isset($params['thispage'])) ? $params['thispage'] : 'file-manager';
    $params['id']           = (isset($params['id'])) ? $params['id'] : 'file-manager';
    $params['action']       = (isset($params['action'])) ? $params['action'] : '';
    
    // Values
    $params['editable'] = explode(',',$params['editable']);
    
    /* File Manager Actions */
    // Add security to these functions
    // Add feedback
    if(isset($_REQUEST['do2'])&&$_REQUEST['id']==$params['id']){
        $do = $_REQUEST['do2'];
        if($do=='newfolder'){
            $newdir = $params['root'].$_REQUEST['dir'].$_REQUEST['new'];
            mkdir($newdir);
        }
        if($do=='newfile'){
            $newfile = $params['root'].$_REQUEST['dir'].$_REQUEST['new'];
            #echo $newfile;
            touch($newfile);
        }
        if($do=='delete2'){
            $olddir = $params['root'].$_REQUEST['dir'].$_REQUEST['file'];
            #echo $olddir;
            rmdir($olddir);
        }
        if($do=='delete'){
            $oldfile = $params['root'].$_REQUEST['dir'].$_REQUEST['file'];
            #echo $olddir;
            unlink($oldfile);
        }
        if($do=='rename'){
            $oldfile = $params['root'].$_REQUEST['dir'].$_REQUEST['old'];
            $newfile = $params['root'].$_REQUEST['dir'].$_REQUEST['new'];
            #echo $oldfile.' '.$newfile;
            rename($oldfile,$newfile);
        }
        if($do=='upload'){
            $target_path = $params['root'].$_REQUEST['dir']. basename( $_FILES['upload']['name']);
            
            if(move_uploaded_file($_FILES['upload']['tmp_name'], $target_path)) {
                //echo "The file ".  basename( $_FILES['upload']['name']). " has been uploaded";
            } else{
                //echo "There was an error uploading the file, please try again!";
            }
        }
        if($do=='compress'){
            $d=$params['root'].$_REQUEST['dir'];
            $d=explode('/',strrev(str_replace(array('//','///'),'/',$d)),2);
            if($d[0]==''){$d=explode('/',$d[1],2);}
            $d2=escapeshellarg(strrev($d[0]));
            $d1=escapeshellarg(strrev($d[1]));
            echo "tar -cvzpf /home/sebble2/tmp/tar/$f.tar.gz -C $d1 $d2";
            $f=time().rand();
            $o=exec("tar -cvzpf /home/sebble2/tmp/tar/$f.tar.gz -C $d1 $d2");
            //force download
            force_dl_3("/home/sebble2/tmp/tar/$f.tar.gz");
        }
    }
    
    $html='';
    // Add CSS+JS
    $html.="<script type=\"text/javascript\" src=\"{$params['filemanager']}/file-manager.js\"></script>\n";
    $html.="<style type=\"text/css\"><!-- @import url('{$params['filemanager']}/file-manager.css'); --></style>\n\n";
    
    $root_dir = $params['root'];
    $_REQUEST['dir'] = ($_REQUEST['dir']=='') ? '/' : $_REQUEST['dir'] ;
    $settings = $modes[$selected];
    $directory = safe_dir2($root_dir,$root_dir.$_REQUEST['dir']);
    $root = ($_REQUEST['dir']=='/') ? true : false ;
    $current = "{$params['thispage']}?dir={$_REQUEST['dir']}&id={$params['id']}";
    $anchor = "#editor-{$params['id']}";

    #var_dump($settings['root']);
    #var_dump($settings['root'].$_REQUEST['dir']);
    #var_dump($directory);
    
    #$html=var_dump($directory);

    if($directory!=false){
        
        $listing = dir_list($directory);
        #print_r($listing);
        $html.= "<ul class=\"file-manager\">";
        if($params['upload']=='true'){$html.= "<li class=\"none\"><a name=\"editor-{$params['id']}\"></a><form enctype=\"multipart/form-data\" action=\"$current&do2=upload\" method=\"post\"><input type=\"file\" name=\"upload\" /><input type=\"submit\" value=\"Upload\" /></form>&nbsp;</li>";}
        $compress='';
        if($params['compress']=='true'){$compress=" - [<a href=\"$current&do2=compress\">Download</a>]";}
      if($_REQUEST['dir']=='/'){$compress1=$compress;$compress2='&nbsp;';}else{$compress2=$compress;}
        $html.= "<li class=\"home clear\" ><a href=\"{$params['thispage']}?dir=/\" name=\"editor-{$params['id']}\">{$params['home']}</a>$compress1<ul>";
        $html.= "<li class=\"folder\">{$_REQUEST['dir']}$compress2<ul>";
        if(!$root){
          $html.= "<li class=\"up\"><a href=\"{$params['thispage']}?dir=".dirname($_REQUEST['dir'])."/$anchor\">Up</a></li>";
        }
        if($params['newfolder']=='true'){$html.= "<li class=\"newfolder\">[<a href=\"javascript:do_newfolder('$current');\">New Folder</a>]</li>";}
        foreach($listing['dir'] as $dir){
            $html.= "<li class=\"folder\"><a href=\"{$params['thispage']}?dir={$_REQUEST['dir']}{$dir}/$anchor\">$dir</a><span class=\"actions\"> - [<a href=\"javascript:do_delete2('$current','$dir');\">Delete</a>] [<a href=\"javascript:do_rename('$current','$dir');\">Rename</a>] <!--[<a href=\"#\">Permissions</a>] [<a href=\"#\">Copy</a>] [<a href=\"#\">Move</a>]--></span></li>";
        }
        if($params['newfile']=='true'){$html.= "<li class=\"newfile\">[<a href=\"javascript:do_newfile('$current');\">New File</a>]</li>";}
        foreach($listing['file'] as $file){
            $parts = pathinfo($file);
            if($parts['extension']==''){$parts['extension']='None';}
            if(in_array($parts['extension'],$params['editable'])){
                $ext = ' ascii';
                $action = "[<a href=\"{$params['editor']}?file={$_REQUEST['dir']}{$file}\" class=\"edit\">Edit</a>]";
            }
            else{
                $ext='';
                $action = '';//"[<a href=\"#\">View</a>]";
            }
            // click action
            if($params['action']!=''){
                $click = "<a href=\"{$params['action']}?file=$file\">$file</a>";
            }else{
                $click=$file;
            }
            $fsize=format_size(filesize($params['root'].$_REQUEST['dir'].$file));
            $html.= "<li class=\"file$ext\">$click<span class=\"actions\"> ($fsize) - <!--[<a href=\"#\">View</a>]--> $action [<a href=\"javascript:do_delete('$current','$file');\">Delete</a>] [<a href=\"javascript:do_rename('$current','$file');\">Rename</a>] <!--[<a href=\"#\">Permissions</a>] [<a href=\"#\">Copy</a>] [<a href=\"#\">Move</a>]--></span></li>";
        }
        $html.= "</ul></li></ul></li></ul>";
        
    }else{
        $html.= "Invalid Directory!";
    }
    
    return $html;
}

function force_dl($string,$ext,$filename){

    $mime_type = (PMA_USR_BROWSER_AGENT == 'IE' || PMA_USR_BROWSER_AGENT == 'OPERA')
        ? 'application/octetstream'
        : 'application/octet-stream';
    header('Content-Type: ' . $mime_type);
    if (PMA_USR_BROWSER_AGENT == 'IE')
    {
        header('Content-Disposition: inline; filename="' . $filename . '.' . $ext . '"');
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header("Content-Length: ".filesize($filename));
        readfile($string);
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '.' . $ext . '"');
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Pragma: no-cache');
        header("Content-Length: ".filesize($filename));
        readfile($string);
    }
    exit();
}

function force_dl_3($file){
    
    if (file_exists($file)) {
        $j = ob_get_level();
        for($i=0;$i<$j;$i++){
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

?>

    
    
    
    

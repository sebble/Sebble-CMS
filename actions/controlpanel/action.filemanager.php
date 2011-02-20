<?php

function action_filemanager_save(){
    
    require_once('file.lib.php');
    require_once('dir.lib.php');
    require_once('extend.lib.php');
    // don't use session forthese values, it means no multi-tasking
    if(is_writable($_SESSION['fileeditorparams']['root'].'/'.$_SESSION['fileeditorparams']['file'])){
        file_save($_SESSION['fileeditorparams']['root'].'/'.$_SESSION['fileeditorparams']['file'],unescape_post($_REQUEST['contents']));
        echo "File Saved!";
        exit();
    }
    echo "Error!";
    exit();
}

?>

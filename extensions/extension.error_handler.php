<?php

function CMScustomError($errno, $errstr, $errfile, $errline, $errcontext) {

    $exceptions = array(8);
    // 8 - undefined var/index
    
    if(!in_array($errno,$exceptions)){
        echo "<b>Error:</b> [$errno] $errstr<br />";
        echo "Ending Script";
        die();
    }
}

?>

<?php

function action_session_login( &$cms, &$cp ){
    
    //print_r();
    //oops, finish creating object in CMS before calling this, then we'll have $cms->extensions['cp'] back..
    $r=$cp->UserLogin($cms->request['request']['cp5_uname'],$cms->request['request']['cp5_pword']);
    if($r){
        $cp->_Action['level']='good';
        $cp->_Action['message']='Login Success: '.$cp->_User['username'];
    }else{
        $cp->_Action['level']='bad';
        $cp->_Action['message']='Login Failed!';
    }
}
function action_session_logout( &$cms, &$cp ){
    
    $cp->UserLogout();
    $cp->_Action['level']='good';
    $cp->_Action['message']='Logged out - please close this window.';
}

?>

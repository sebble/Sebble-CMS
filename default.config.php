<?php

/**
 *  CMS Default Configuration File
 *
 **/

// CMS System Settings
$config['system'] = array(
    'system_dir'=>'/home/www/.sebble/lib/CMS_5.0.1',
    'http_dir'  =>'/home/www/public_html',
    'site_id'   => 0,
    'page_var'  =>'page5',
    'action_var'=>'do5',
    'allow_debug'=>false,
    '404page'   =>'404',
    'smarty_class'=>'/home/www/.sebble/lib/Smarty/Smarty.class.php',
    'database'  => array(
        'server'=>'localhost'
    ),
    'option_pref'=>'DB',
    'page_mode' =>'modrewrite', // modrewrite, 404rewrite, index.php/, index.php?
    'extensions'=>array('data','smartyCMS','simple_modifiers'),
    'rebuild'=>true  // allows e.g., sections to alter title
);

// CMS Application Info
$config['info'] = array(
    'app_name'  =>'Sebble CMS Application'
);

// Extension Configs
// -- Control Panel --
$config['extensions']['controlpanel'] = array(
    'pages'=>array(
        'login'=>'Login'
    ),
    'users'=>array(
        'guest'=>array(
            'username'=>'guest',
            'groups'=>array('guest'),
            'default-site'=>0,
            'fullname'=>'Guest Account'
        )
    ),
    'groups'=>array(
        'guest'=>array(
            'pages'=>array('login'),
            'actions'=>array('session.login','session.logout','session.badpage')
        )
    ),
    'action_var'=>'cp5',
    'timeout'=>'30'
);

// Page Templates
$config['templates'] = array();

?>

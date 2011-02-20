<?php

function smarty_function_fileeditor($params, &$smarty){
    
    require_once('file.lib.php');
    require_once('dir.lib.php');
    require_once('extend.lib.php');
    
    // Defaults
    $params['editable']     = (isset($params['editable'])) ? $params['editable'] : 'txt,html,php,css,htaccess';
    $params['height']       = (isset($params['height'])) ? $params['height'] : '350';
    $params['file']         = (isset($params['file'])) ? $params['file'] : $_REQUEST['file'];
    
    // Values
    $params['editable'] = explode(',',$params['editable']);
    
    $_SESSION['fileeditorparams'] = $params;
    
    /* File Manager Actions */
    // Add security to these functions
    // Add feedback
    if(isset($_REQUEST['do2'])){
        $do = $_REQUEST['do2'];
        if($do=='save'){
            if(file_exists($params['root'].'/'.$params['file'])){
                file_save($params['root'].'/'.$params['file'],unescape_post($_REQUEST['contents']));
                $smarty->assign('action_msg','File Saved!');
                $smarty->assign('action_level','good');
            }
        }
    }
    
    // check file root
    $path_parts = pathinfo($params['root'].'/'.$params['file']);
    $directory = safe_dir2($params['root'],$path_parts['dirname']);
    $extension = strtolower($path_parts['extension']);
    if($directory&&file_exists($params['root'].'/'.$params['file'])){
        // fetch file
        $content = file_get_contents($params['root'].'/'.$params['file']);
        $content = htmlentities($content,ENT_QUOTES);
    }else{
        $content = 'Invalid File';
        return $content;
    }
    
    //choose mode
    $extmap['js']   = 'js';
    $extmap['xml']  = 'xml-html';
    $extmap['css']  = 'css';
    $extmap['html'] = 'html-mix';
    $extmap['htm']  = 'html-mix';
    $extmap['php']  = 'php-html';
    $extmap['py']   = 'python';
    $extmap['sql']  = 'sql';
    $extmap['ini']  = 'ini';
    $extmap['htaccess'] = 'ini';
    $extmap['htpasswd'] = 'ini';
    $extmap['conf'] = 'ini';
    $extmap['txt']  = 'txt';
    $extmap['tpl']  = 'xml-html';
    $type = $extmap[$extension];
    
    $html='';
    // Add CSS+JS
    $html.="<script src=\"/CodeMirror-0.8/js/codemirror.js\" type=\"text/javascript\"></script>\n";
    $html.="<script src=\"{$params['fileeditor']}/prototype.js\" type=\"text/javascript\"></script>\n";
    $html.="<style type=\"text/css\"><!-- @import url('{$params['fileeditor']}/file-editor.css'); --></style>\n\n";
    
    $html.= <<<EOT
    <div class="file-editor"><form action='tools.file-manager.edit' method='POST' id='file-editor-form'>
    <input type="hidden" name="file" value="{$params['file']}" />
    <input type="hidden" name="cp5" value="filemanager.save" />
    <textarea id="file-editor-code" name="contents" cols="120" rows="30">$content</textarea>
    <!--<input type="submit" value="Save Changes" /></form>-->
    </div>
EOT;
    
    #$root_dir = $params['root'];
    #$_REQUEST['dir'] = ($_REQUEST['dir']=='') ? '/' : $_REQUEST['dir'] ;
    #$settings = $modes[$selected];
    #$directory = safe_dir($root_dir,$root_dir.$_REQUEST['dir']);
    #$root = ($_REQUEST['dir']=='/') ? true : false ;
    
    switch($type){
        case 'js':
            $options    = <<<EOT
    parserfile: ["tokenizejavascript.js", "parsejavascript.js"],
    stylesheet: "CodeMirror-0.8/css/jscolors.css",
    autoMatchParens: true
EOT;
            break;
        case 'xml-html':
            $options    = <<<EOT
    parserfile: "parsexml.js",
    stylesheet: "CodeMirror-0.8/css/xmlcolors.css"
EOT;
            break;
        case 'css':
            $options    = <<<EOT
    parserfile: "parsecss.js",
    stylesheet: "CodeMirror-0.8/css/csscolors.css"
EOT;
            break;
        case 'html-mix':
            $options    = <<<EOT
    parserfile: ["parsexml.js", "parsecss.js", "tokenizejavascript.js", "parsejavascript.js", "parsehtmlmixed.js"],
    stylesheet: ["CodeMirror-0.8/css/xmlcolors.css", "CodeMirror-0.8/css/jscolors.css", "CodeMirror-0.8/css/csscolors.css"]
EOT;
            break;
        case 'php-html':
            $options    = <<<EOT
    parserfile: ["parsexml.js", "parsecss.js", "tokenizejavascript.js", "parsejavascript.js",
                 "../contrib/php/js/tokenizephp.js", "../contrib/php/js/parsephp.js",
                 "../contrib/php/js/parsephphtmlmixed.js"],
    stylesheet: ["CodeMirror-0.8/css/xmlcolors.css", "CodeMirror-0.8/css/jscolors.css", "CodeMirror-0.8/css/csscolors.css", "CodeMirror-0.8/contrib/php/css/phpcolors.css"]
EOT;
            break;
        case 'python':
            $options    = <<<EOT
    parserfile: ["../contrib/python/js/parsepython.js"],
    stylesheet: "CodeMirror-0.8/contrib/python/css/pythoncolors.css",
    lineNumbers: true,
    textWrapping: false,
    indentUnit: 4,
    parserConfig: {'pythonVersion': 2, 'strictErrors': true}
EOT;
            break;
        case 'sql':
            $options    = <<<EOT
    parserfile: "../contrib/sql/js/parsesql.js",
    stylesheet: "CodeMirror-0.8/contrib/sql/css/sqlcolors.css",
    textWrapping: false
EOT;
            break;
        default:
            $options    = <<<EOT
    parserfile: "parsedummy.js",
    stylesheet: "CodeMirror-0.8/css/xmlcolors.css"
EOT;
    }
    
    $html.=<<<EOT
<script type="text/javascript">
  /*function submit_form(form) {
    $("editor").value = editor.getCode(); // The magic dust
    
    new Ajax.Request('/admin/design/update', {
      asynchronous:true,
      evalScripts:true,
      parameters:Form.serialize(form)
    });
  }*/

  var editor = CodeMirror.fromTextArea('file-editor-code', {
    height: "{$params['height']}px",
    /*height: "dynamic",*/
    path: "/CodeMirror-0.8/js/",
    continuousScanning: 500,
    lineNumbers: true,
    saveFunction: function(event){
      $("file-editor-code").value = editor.getCode(); // The magic dust
      
      new Ajax.Request('/ajax?cp5=filemanager.save', {
        method: 'post',
        onSuccess: function(transport) {
          alert(transport.responseText);
        },
        asynchronous:true,
        evalScripts:true,
        parameters:Form.serialize($("file-editor-form"))
      });
    },
    indentUnit: 2,
    tabMode: "shift",
    enterMode: "flat",
    electricMode: "off",
    textWrapping: "on",
$options
  });
</script>
EOT;
    
    #$html.="<script type=\"text/javascript\" src=\"{$params['style']}/file-editor.js\"></script>";
    
    return $html;
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
{cp_set_page page=$regex[1]}
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>{$cp_pagetitle} - {$title}</title>
<link rel="stylesheet" href="{$cp}/style/style.css" />
<style>{literal}
    .cp_msg_normal p { background-color:#fffeeb; }
    .cp_msg_bad p { background-color:#fcc; }
    .cp_msg_good p { background-color:#cfc; }
</style>{/literal}
</head>
<body>
<div id="cp_top"><p id="cp_view"><a href="{$site}">View Site</a></p><p id="cp_user">Hello, <strong>{$user.username}</strong>. [<a href="{$login}?cp5=session.logout">Sign Out</a>]</p><div class="clear"></div></div>


{*{capture name=capture_msg}Oh noes!{/capture}*}
{*{php}
    $this->assign('action_msg',"Important, <em>but good</em>, message <a href='#'>here</a>.");
    $this->assign('action_level',"good");
{/php}*}

{if $action_msg eq ''}{*No Action MSG*}
    {if $smarty.capture.capture_msg eq ''}{*No Capture MSG*}
        {capture name=capture_msg}
            {*Set Capture MSG*}
            Current time is {$smarty.now|date_format:"%H:%M on %a %e %b, %Y"}
        {/capture}
        {capture name=capture_level}normal{/capture}
    {/if}
{/if}

<ul id="cp_tabs" class="flat">
{foreach from=$cp_tabs key=taburl item=tabtitle}
    {if $taburl eq $thistab}{capture} class="cp_navactive"{/capture}{else}{capture}{/capture}{/if}
    <li><a href="{$taburl}"{$smarty.capture.default}>{$tabtitle}</a></li>
{/foreach}
</ul>

{if $cp_subpages|@count gt 0}
    <ul class="cp_links flat cp_navactive">
    {foreach from=$cp_subpages key=subpageurl item=subpage}
       <li><a href="{$subpageurl}">{$subpage}</a></li>
    {/foreach}
    </ul>
{/if}

{*
{foreach from=$pages item=tab key=taburl}
    {if $thistab eq $taburl}
    {if $tab.subpages|@count gt 0}
    <ul class="cp_links flat cp_navactive">
    {foreach from=$tab.subpages key=subpageurl item=subpage}
       <li><a href="{$subpageurl}">{$subpage}</a></li>
    {/foreach}
    </ul>
    {/if}
    {/if}
{/foreach}
*}

<div id="cp_info" class="cp_msg_{$action_level|default:$smarty.capture.capture_level}"><p>{$action_msg|default:$smarty.capture.capture_msg}</p></div>
<div id="cp_forms">
  {capture}{cp_placeholder page="$regex[1]"}{/capture}
  {if $smarty.capture.default ne ''}{$smarty.capture.default}{else}<h1>Error</h1><p>This page was not found, please contact your administrator.</p>{/if}
</div>
<p id="cp_footer">Control Panel 5 created by <a href="#">Sebastian Mellor</a> - <a href="http://sebble.com">Sebble.com</a></p>
</body>
</html>

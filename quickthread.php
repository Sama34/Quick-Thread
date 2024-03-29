<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

$plugins->add_hook('global_start', 'quickthread_tplcache');
$plugins->add_hook('forumdisplay_end', 'quickthread_run');

function quickthread_info()
{
	return array(
		'name'			=> 'Quick Thread',
		'description'	=> 'Makes a quick thread box on forumdisplay pages similar to the quick reply on showthread.',
		'website'		=> 'http://mybbhacks.zingaburga.com/',
		'author'		=> 'ZiNgA BuRgA',
		'authorsite'	=> 'http://zingaburga.com/',
		'version'		=> '1.4',
		'compatibility'	=> '14*,15*,16*,17*,18*',
		'guid'			=> ''
	);
}

function quickthread_install() {
	global $db, $mybb;
	$db->insert_query('templates', array(
		'sid' => -1,
		'title' => 'forumdisplay_quick_thread',
		'template' => $db->escape_string('
<br />
<form method="post" action="newthread.php?fid={$fid}&amp;processed=1" name="quick_thread_form" id="quick_thread_form">
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="action" value="do_newthread" />
	<input type="hidden" name="posthash" value="{$posthash}" id="posthash" />

	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<thead>
			<tr>
				<td class="thead" colspan="2">
					<div class="expcolimage"><img src="{$theme[\'imgdir\']}/collapse{$collapsedimg[\'quickthread\']}.'.($mybb->version_code >= 1700 ? 'png':'gif').'" id="quickthread_img" class="expander" alt="[-]" title="[-]" /></div>
					<div><strong>{$lang->quick_thread}</strong></div>
				</td>
			</tr>
		</thead>
		<tbody style="{$collapsed[\'quickthread_e\']}" id="quickthread_e">
			<tr>
				<td class="trow1" valign="top" width="22%">
					<strong>{$lang->subject}</strong>
				</td>
				<td class="trow1">
					<div style="width: 95%">
						{$prefixselect}<input type="text" class="textbox" name="subject" size="40" maxlength="85" style="padding-left: 4px; padding-right: 4px; margin: 0;" cols="80" tabindex="1" />
					</div>
				</td>
			</tr>
			<tr>
				<td class="trow2" valign="top" width="22%">
					<strong>{$lang->message}</strong><br />
					<span class="smalltext"><br />
					<label><input type="checkbox" class="checkbox" name="postoptions[signature]" value="1" {$postoptionschecked[\'signature\']} />&nbsp;<strong>{$lang->signature}</strong></label><br />
					<label><input type="checkbox" class="checkbox" name="postoptions[disablesmilies]" value="1" />&nbsp;<strong>{$lang->disable_smilies}</strong></label>{$modoptions}</span>
				</td>
				<td class="trow2">
					<div style="width: 95%">
						<textarea style="width: 100%; padding: 4px; margin: 0;" rows="8" cols="80" name="message" id="message" tabindex="2"></textarea>
						{$codebuttons}
					</div>
					{$smilieinserter}
				</td>
			</tr>
			{$captcha}
			<tr>
				<td colspan="2" align="center" class="tfoot"><input type="submit" class="button" value="{$lang->post_thread}" tabindex="2" accesskey="s" id="quick_thread_submit" /> <input type="submit" class="button" name="previewpost" value="{$lang->preview_post}" tabindex="3" /></td>
			</tr>
		</tbody>
	</table>
</form>
'),
		'version' => '1411'
	));
}
function quickthread_is_installed() {
	return $GLOBALS['db']->fetch_field($GLOBALS['db']->simple_select('templates', 'tid', 'title="forumdisplay_quick_thread"'), 'tid');
}
function quickthread_uninstall() {
	$GLOBALS['db']->delete_query('templates', 'title="forumdisplay_quick_thread"');
}


function quickthread_activate()
{
	global $db, $cache;
	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('forumdisplay', '~{$threadslist}~', '{$threadslist}{$quickthread}');
}

function quickthread_deactivate()
{
	global $db, $cache;
	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('forumdisplay', '~{$quickthread}~', '', 0);
}


function quickthread_tplcache() {
	if(THIS_SCRIPT == 'forumdisplay.php') {
		global $templatelist;
		if(isset($templatelist)) $templatelist .= ',forumdisplay_quick_thread,post_prefixselect_prefix,post_prefixselect_single';
	}
}

function quickthread_run() {
	global $foruminfo, $fpermissions;
	if($foruminfo['open'] == 0 || $foruminfo['type'] != 'f' || $fpermissions['canpostthreads'] == 0 || $GLOBALS['mybb']->user['suspendposting'] == 1)
		return;
	
	global $theme, $mybb, $templates, $fid, $lang, $collapsed, $collapsedimg;

	isset($collapsedimg['quickthread']) || $collapsedimg['quickthread'] = '';
	isset($collapsed['quickthread_e']) || $collapsed['quickthread_e'] = '';
	
	if(function_exists('build_prefix_select')) {
		$lang->load('newthread');
		// note that newthread lang needs to load before showthread lang, or stuff like $lang->close_thread gets overridden
		$prefixselect = build_prefix_select($fid);
	}

	$lang->load('showthread');
	isset($lang->quick_thread) || $lang->quick_thread = 'Quick New Thread';
	isset($lang->subject) || $lang->subject = 'Subject';
	
	$postoptionschecked = array('signature' => '');
	if($mybb->user['signature'] != '')
		$postoptionschecked['signature'] = ' checked="checked"';

    $captcha = '';

	if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
	{
		$correct = false;

		require_once MYBB_ROOT.'inc/class_captcha.php';

		$post_captcha = new captcha(false, "post_captcha");

		if($post_captcha->type == 1)
		{
			if($post_captcha->validate_captcha() == true)
			{
				$correct = true;

				$captcha = $post_captcha->build_hidden_captcha();
			}
		}

		if(!$correct)
		{
			if($post_captcha->type == 1)
			{
				$post_captcha->build_captcha();
			}
			elseif(in_array($post_captcha->type, array(4, 5, 8)))
			{
				$post_captcha->build_recaptcha();
			}
			elseif(in_array($post_captcha->type, array(6, 7)))
			{
				$post_captcha->build_hcaptcha();
			}
		}
		else if($correct && (in_array($post_captcha->type, array(4, 5, 8))))
		{
			$post_captcha->build_recaptcha();
		}
		else if($correct && (in_array($post_captcha->type, array(6, 7))))
		{
			$post_captcha->build_hcaptcha();
		}

		if($post_captcha->html)
		{
			$captcha = $post_captcha->html;
		}
	}

	$posthash = md5($mybb->user['uid'].mt_rand());
	$modoptions = $codebuttons = $smilieinserter = '';
	if(is_moderator($fid, 'canopenclosethreads')) {
		$modoptions .= '<br /><label><input type="checkbox" class="checkbox" name="modoptions[closethread]" value="1" />&nbsp;<strong>'.$lang->close_thread.'</strong></label>';
	}
	if(is_moderator($fid, 'canmanagethreads')) {
		$modoptions .= '<br /><label><input type="checkbox" class="checkbox" name="modoptions[stickthread]" value="1" />&nbsp;<strong>'.$lang->stick_thread.'</strong></label>';
	}

    if($mybb->settings['bbcodeinserter'] != 0 && $foruminfo['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
    {
        $codebuttons = build_mycode_inserter("message", $foruminfo['allowsmilies']);
        if($foruminfo['allowsmilies'] != 0)
        {
            $smilieinserter = build_clickable_smilies();
        }
    }

	eval('$GLOBALS[\'quickthread\'] = "'.$templates->get('forumdisplay_quick_thread').'";');
	if(!strpos($templates->cache['forumdisplay'], '{$quickthread}')) {
		$templates->cache['forumdisplay'] = str_replace('{$threadslist}', '{$threadslist}{$quickthread}', $templates->cache['forumdisplay']);
	}
}
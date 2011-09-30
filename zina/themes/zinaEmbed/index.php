<?php
# you can override default theme functions by including them here

function zinaEmbed_page_complete($zina, array $opts = array()) {
	$embed = $zina['embed'];

	$output = '';

	if ($embed == 'drupal' || $embed == 'joomla') {
		if (isset($opts['page_title'])) {
			if ($embed == 'joomla') $output .= '<h2 class="contentheading">'.$zina['title'].'</h2>';
		}
		$output .= '<div id="zina" class="clear-block '.$embed.'">';
		$output .= '<table border="0" cellpadding="5" cellspacing="0" width="100%" class="'.$embed.'"><tr>'.
			'<td nowrap="nowrap" valign="top" width="34%">';

		if (isset($zina['dir_year']) || isset($zina['dir_genre'])) {
			$output .= '<span class="zina-title-details">';
			if (isset($zina['dir_genre'])) $output .= $zina['dir_genre'];
			if (!empty($zina['dir_year'])) $output .= ' ('.$zina['dir_year'].')';
			$output .= '</span>';
		}
		$output .= '</td><td align="center" nowrap="nowrap" valign="top" width="33%">'.$zina['searchform'].'</td>'.
			'<td align="right" nowrap="nowrap" valign="top" width="33%">'.$zina['randomplayform'].'</td></tr></table>';

	} else {
		$output .= '<div id="zina" class="clear-block '.$embed.'">';
		$title = '<h1>'.$zina['title'].'</h1>';

		switch ($embed) {
			case 'wordpress':
		#wordpress
		# - H2
				break;
			case 'joomla':
				$title = '<div class="componentheading">'.$zina['title'].'</div>';
				break;
			default:
		}

		$output .= '<div class="zina-header"><div class="zina-header-left">'.$title;

			if (isset($zina['dir_year']) || isset($zina['dir_genre'])) {
				$output .= '<div class="zina-title-details">';
				if (isset($zina['dir_genre'])) $output .= $zina['dir_genre'];
				if (!empty($zina['dir_year'])) $output .= ' ('.$zina['dir_year'].')';
				$output .= '</div>';
			}
		$output .= '</div><div class="zina-header-right">';
		$output .= $zina['searchform'];

		if (isset($zina['admin_config'])) {
			$output .= zl(ztheme('icon','config.gif',zt('Settings')),$zina['admin_config']['path'],$zina['admin_config']['query']);
		}
		$lang['login'] = zt('Login');
		$lang['logout'] = zt('Logout');
		if (isset($zina['login'])) {
			$output .= zl(ztheme('icon',$zina['login']['type'].'.gif',$lang[$zina['login']['type']]), $zina['login']['path'], $zina['login']['query']);
		}

		$output .= '</div></div>';

		$output .= '<div class="zina-subheader"><div class="zina-subheader-left">'.ztheme('breadcrumb',$zina['breadcrumb']).'</div>'.
			'<div class="zina-subheader-right">'.$zina['randomplayform'].'</div></div>';
	}

	$output .= '<div class="zina-content clear-block"><div id="zina_messages">'.$zina['messages'].'</div>';
		if (!isset($zina['popup'])) { 
			if (!empty($zina['zinamp']) && (!isset($zina['page_main']) || isset($zina['category']))) {
				$output .= '<div style="text-align:right;">'.$zina['zinamp'].'</div>';
			}
		}
	$output .= $zina['content'].ztheme('page_footer',$zina).'</div></div>';

	return $output;
}

function zinaEmbed_cms_teaser($zina, $access = true) {
	$output = '<div class="zina zina-content clear-block">';
	if ($access) {
		$output .= '<div class="zina-directory-image">'.zl($zina['dir_image_sub'], $zina['path']).'</div>';
	}
	if (!empty($zina['description'])) $output .= '<p>'.$zina['description'].'</p>';
	$output .= '</div>';
	return $output;
}
?>

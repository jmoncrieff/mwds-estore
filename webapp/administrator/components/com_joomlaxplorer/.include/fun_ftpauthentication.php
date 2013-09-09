<?php
/** ensure this file is being included by a parent file */
defined( '_VALID_MOS' ) or die( 'Direct Access to this location is not allowed.' );
/*------------------------------------------------------------------------------
     The contents of this file are subject to the Mozilla Public License
     Version 1.1 (the "License"); you may not use this file except in
     compliance with the License. You may obtain a copy of the License at
     http://www.mozilla.org/MPL/

     Software distributed under the License is distributed on an "AS IS"
     basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
     License for the specific language governing rights and limitations
     under the License.

     The Original Code is fun_down.php, released on 2003-01-25.

     The Initial Developer of the Original Code is The QuiX project.

     Alternatively, the contents of this file may be used under the terms
     of the GNU General Public License Version 2 or later (the "GPL"), in
     which case the provisions of the GPL are applicable instead of
     those above. If you wish to allow use of your version of this file only
     under the terms of the GPL and not to allow others to use
     your version of this file under the MPL, indicate your decision by
     deleting  the provisions above and replace  them with the notice and
     other provisions required by the GPL.  If you do not delete
     the provisions above, a recipient may use your version of this file
     under either the MPL or the GPL."
------------------------------------------------------------------------------*/
/**
 * @author soeren
 * @copyright soeren (C) 2006
 * 
 * This file handles ftp authentication
 */

function ftp_authentication( $ftp_login='', $ftp_pass='') {
	global $dir, $mosConfig_live_site;
	
	if( $ftp_login != '' || $ftp_pass != '' ) {
		while( @ob_end_clean() );
			
		@header("Status: 200 OK");
		$url = parse_url( $mosConfig_live_site );
		$ftp = new Net_FTP( $url['host'], 21, 20 );
		$res = $ftp->connect();
		if( PEAR::isError( $res )) {
			echo jx_alertBox( $GLOBALS['messages']['ftp_connection_failed'] );
			echo jx_scriptTag('', '$(\'loadingindicator\').innerHTML = \'\';' );
			echo $GLOBALS['messages']['ftp_connection_failed'].'<br />['.$res->getMessage().']';
			exit;
		}
		else {
			$res = $ftp->login( $ftp_login, $ftp_pass );
			$ftp->disconnect();
			if( PEAR::isError( $res )) {
				echo jx_alertBox( $GLOBALS['messages']['ftp_login_failed'] );
				echo jx_scriptTag('', '$(\'loadingindicator\').innerHTML = \'\';' );
				echo $GLOBALS['messages']['ftp_login_failed'].'<br />['.$res->getMessage().']';
				exit;
			}
			echo jx_alertBox('Login OK!');
			$_SESSION['ftp_login'] = $ftp_login;
			$_SESSION['ftp_pass'] = $ftp_pass;
			
			session_write_close();
			
			echo jx_docLocation( str_replace( 'index3.php', 'index2.php', make_link('list', '' ).'&file_mode=ftp' ));
			exit;
		}
		
	}
	else {
		?>
		<script type="text/javascript" src="components/com_joomlaxplorer/_js/mootools.ajax.js"></script>
		<script type="text/javascript" src="components/com_joomlaxplorer/_js/functions.js"></script>
		<script type="text/javascript">
		function checkFTPAuth( url ) {
			showLoadingIndicator( $('loadingindicator'), true );
			$('loadingindicator').innerHTML += ' <strong><?php echo $GLOBALS['messages']['ftp_login_check'] ?></strong>';
			
			var controller = new ajax( url, { 	postBody: $('adminform'),
												evalScripts: true,
												update: 'statustext' 
												} 
									);
			controller.request();
			return false;
		}
		</script>
		
		<?php
	show_header('Local FTP Authentication');
	?><br/>
	
	<form name="ftp_auth_form" method="post" action="<?php echo $mosConfig_live_site ?>/administrator/index3.php" onsubmit="return checkFTPAuth('<?php echo $mosConfig_live_site ?>/administrator/index3.php');" id="adminform">
	
	<input type="hidden" name="no_html" value="1" />
	<table class="adminform" style="width:400px;">
		<tr><th colspan="3"><?php echo $GLOBALS["messages"]["ftp_login_lbl"] ?></th></tr>
		
		<tr><td colspan="3" style="text-align:center;" id="loadingindicator"></td></tr>
		<tr><td colspan="3" style="font-weight:bold;text-align:center" id="statustext">&nbsp;</td></tr>
		
		<tr>
			<td width="50" style="text-align:center;" rowspan="3"><img align="absmiddle" src="images/security_f2.png" alt="Login!" /></td>
			<td><?php echo $GLOBALS["messages"]["ftp_login_name"] ?>:</td>
			<td align="left">
				<input type="text" name="ftp_login_name" size="25" title="<?php echo $GLOBALS["messages"]["ftp_login_name"] ?>" />
			</td>
		</tr>		
		<tr>
			<td><?php echo $GLOBALS["messages"]["ftp_login_pass"] ?>:</td>
			<td align="left">
				<input type="password" name="ftp_login_pass" size="25" title="<?php echo $GLOBALS["messages"]["ftp_login_pass"] ?>" />
			</td>
		</tr>
		<tr><td colspan="2">&nbsp;</td></tr>
		<tr>
			<td style="text-align:center;" colspan="3">
			<input type="hidden" name="action" value="ftp_authentication" />
			<input type="hidden" name="option" value="com_joomlaxplorer" />
			<input type="submit" name="submit" value="<?php echo $GLOBALS['messages']['btnlogin'] ?>" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="button" name="cancel" value="<?php echo $GLOBALS['messages']['btncancel'] ?>" onclick="javascript:document.location='<?php echo make_link('list', $dir ) ?>';" />
			</td>
		</tr>
		<tr><td colspan="3">&nbsp;</td></tr>
	</table>
	</form>
	<?php	
	}
}
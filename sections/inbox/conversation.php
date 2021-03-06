<?
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$ConvID = $_GET['id'];
if(!$ConvID || !is_number($ConvID)) { error(404); }



$UserID = $LoggedUser['ID'];
$DB->query("SELECT InInbox, InSentbox FROM pm_conversations_users WHERE UserID='$UserID' AND ConvID='$ConvID'");
if($DB->record_count() == 0) {
	error(403);
}
list($InInbox, $InSentbox) = $DB->next_record();




if (!$InInbox && !$InSentbox) {

	error(404);
}

// Get information on the conversation
$DB->query("SELECT
	c.Subject,
	cu.Sticky,
	cu.UnRead,
	cu.ForwardedTo,
	um.Username
	FROM pm_conversations AS c
	JOIN pm_conversations_users AS cu ON c.ID=cu.ConvID
	LEFT JOIN users_main AS um ON um.ID=cu.ForwardedTo
	WHERE c.ID='$ConvID' AND UserID='$UserID'");
list($Subject, $Sticky, $UnRead, $ForwardedID, $ForwardedName) = $DB->next_record();

$DB->query("SELECT UserID, Username, PermissionID, CustomPermissions, Enabled, Donor, Warned, Title
	FROM pm_messages AS pm
	JOIN users_info AS ui ON ui.UserID=pm.SenderID
	JOIN users_main AS um ON um.ID=pm.SenderID
	WHERE pm.ConvID='$ConvID'");

while(list($PMUserID, $Username, $PermissionID, $CustomPermissions, $Enabled, $Donor, $Warned, $Title) = $DB->next_record()) {
	$PMUserID = (int)$PMUserID;
	$Users[$PMUserID]['UserStr'] = format_username($PMUserID, $Username, $Donor, $Warned, $Enabled == 2 ? false : true, $PermissionID, $Title, true);
	$Users[$PMUserID]['Username'] = $Username;
	$Users[$PMUserID]['AdvTags'] = get_permissions_advtags($PMUserID, $CustomPermissions);
}
$Users[0]['UserStr'] = 'System'; // in case it's a message from the system
$Users[0]['Username'] = 'System';
$Users[0]['AdvTags'] = true;



if($UnRead=='1') {

	$DB->query("UPDATE pm_conversations_users SET UnRead='0' WHERE ConvID='$ConvID' AND UserID='$UserID'");
	// Clear the caches of the inbox and sentbox
	$Cache->decrement('inbox_new_'.$UserID);
}

show_header('View conversation '.$Subject, 'comments,inbox,bbcode');

// Get messages
$DB->query("SELECT SentDate, SenderID, Body, ID FROM pm_messages AS m WHERE ConvID='$ConvID' ORDER BY ID");
?>
<div class="thin">
	<h2><?=$Subject.($ForwardedID > 0 ? ' (Forwarded to '.$ForwardedName.')':'')?></h2>
	<div class="linkbox">
		<a href="inbox.php">[Back to inbox]</a>
	</div>
<?

while(list($SentDate, $SenderID, $Body, $MessageID) = $DB->next_record()) { ?>
        <div class="head">
                <?=$Users[(int)$SenderID]['UserStr'].' '.time_diff($SentDate);
                    if($SenderID!=0){ 
              ?>  - <a href="#quickpost" onclick="Quote('<?=$MessageID?>','','<?=$Users[(int)$SenderID]['Username']?>');">[Quote]</a>	
            <?      }  ?>
        </div>
	<div class="box vertical_space">
		<div class="body" id="message<?=$MessageID?>">
			<?=$Text->full_format($Body, $Users[(int)$SenderID]['AdvTags'])?>
		</div>
	</div>
<?
}
//$DB->query("SELECT UserID FROM pm_conversations_users WHERE UserID!='$LoggedUser[ID]' AND ConvID='$ConvID' AND (ForwardedTo=0 OR ForwardedTo=UserID)");
//$ReceiverIDs = $DB->collect('UserID');
//if(!empty($ReceiverIDs) && (empty($LoggedUser['DisablePM']) || array_intersect($ReceiverIDs, array_keys($StaffIDs)))) {

$DB->query("SELECT SenderID FROM pm_messages WHERE ConvID='$ConvID'");
list($ReplyID) = $DB->next_record();

if(!empty($ReplyID) && $ReplyID!=0 && (empty($LoggedUser['DisablePM']) || array_key_exists($ReplyID, $StaffIDs) ) ) {
?>
	<div class="head">Reply</div>
	<div class="box pad">
            <form action="inbox.php" method="post" id="messageform">
			<input type="hidden" name="action" value="takecompose" />
			<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
			<input type="hidden" name="toid" value="<?=$ReplyID?>" />
			<input type="hidden" name="convid" value="<?=$ConvID?>" />
            <? $Text->display_bbcode_assistant("quickpost", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
			<textarea id="quickpost" name="body" class="long" rows="10"></textarea> <br />
			<div id="preview" class="box vertical_space body hidden"></div>
			<div id="buttons" class="center">
				<input type="button" value="Preview" onclick="Quick_Preview();" /> 
				<input type="submit" value="Send message" />
			</div>
            </form>
      </div>
<?
}
?>
	<div class="head">Manage conversation</div>
	<div class="box pad">
            <form action="inbox.php" method="post">
			<input type="hidden" name="action" value="takeedit" />
			<input type="hidden" name="convid" value="<?=$ConvID?>" />
			<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />

			<table width="100%" class="noborder">
				<tr>
					<td class="center" width="33%"><label for="sticky">Sticky</label>
						<input type="checkbox" id="sticky" name="sticky"<? if($Sticky) { echo ' checked="checked"'; } ?> />
					</td>
					<td class="center" width="33%"><label for="mark_unread">Mark as unread</label>
						<input type="checkbox" id="mark_unread" name="mark_unread" />
					</td>
					<td class="center" width="33%"><label for="delete">Delete conversation</label>
						<input type="checkbox" id="delete" name="delete" />
					</td>

				</tr>
				<tr>
					<td class="center" colspan="3"><input type="submit" value="Manage conversation" /></td>
				</tr>
			</table>
            </form>
	</div>
<?
$DB->query("SELECT SupportFor FROM users_info WHERE UserID = ".$LoggedUser['ID']);
list($FLS) = $DB->next_record();
if((check_perms('users_mod') || $FLS != "") && (!$ForwardedID || $ForwardedID == $LoggedUser['ID'])) {
?>
	<div class="head">Forward conversation</div>
	<div class="box pad">
            <form action="inbox.php" method="post">
			<input type="hidden" name="action" value="forward" />
			<input type="hidden" name="convid" value="<?=$ConvID?>" />
			<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
			<label for="receiverid">Forward to</label>
			<select id="receiverid" name="receiverid">
<?
	foreach($StaffIDs as $StaffID => $StaffName) {
		if($StaffID == $LoggedUser['ID'] || in_array($StaffID, $ReceiverIDs)) {
			continue;
		}
?>
				<option value="<?=$StaffID?>"><?=$StaffName?></option>
<?
	}
?>
			</select>
			<input type="submit" value="Forward" />
            </form>
     </div>
<?
}

//And we're done!
?>
</div>
<?
show_footer();
?>

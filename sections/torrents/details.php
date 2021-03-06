<?
/*
function compare($X, $Y){ // if this is used anywhere else... 
	return($Y['score'] - $X['score']);
}*/


define(MAX_PERS_COLLAGES, 3); // How many personal collages should be shown by default

include(SERVER_ROOT.'/sections/tools/managers/mfd_functions.php');
include(SERVER_ROOT.'/sections/bookmarks/functions.php'); // has_bookmarked()
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

if(!$GroupID) $GroupID=ceil($_GET['id']);

include(SERVER_ROOT.'/sections/torrents/functions.php');
$TorrentCache = get_group_info($GroupID, true);

$TorrentDetails = $TorrentCache[0];
$TorrentList = $TorrentCache[1];

// Group details
list($Body, $Image, $GroupID, $GroupName, $GroupCategoryID,
    $GroupTime, $TorrentTags, $TorrentTagIDs, $TorrentTagUserIDs, $TagPositiveVotes, $TagNegativeVotes) = array_shift($TorrentDetails);

$DisplayName=$GroupName;
$AltName=$GroupName; // Goes in the alt text of the image
$Title=$GroupName; // goes in <title>
//$Body = $Text->full_format($Body);

$tagsort = isset($_GET['tsort'])?$_GET['tsort']:'score';
if(!in_array($tagsort, array('score','az','added'))) $tagsort = 'score';

$Tags = array();
if ($TorrentTags != '') {
	$TorrentTags=explode('|',$TorrentTags);
	$TorrentTagIDs=explode('|',$TorrentTagIDs);
	$TorrentTagUserIDs=explode('|',$TorrentTagUserIDs);
	$TagPositiveVotes=explode('|',$TagPositiveVotes);
	$TagNegativeVotes=explode('|',$TagNegativeVotes);
	
	foreach ($TorrentTags as $TagKey => $TagName) {
		$Tags[$TagKey]['name'] = $TagName;
		$Tags[$TagKey]['score'] = ($TagPositiveVotes[$TagKey] - $TagNegativeVotes[$TagKey]);
		$Tags[$TagKey]['id']=$TorrentTagIDs[$TagKey];
		$Tags[$TagKey]['userid']=$TorrentTagUserIDs[$TagKey];
	}
	uasort($Tags, "sort_$tagsort");
}
//advance tagsort for link
if($tagsort=='score') $tagsort2='az';
else if($tagsort=='az') $tagsort2='added';
else $tagsort2='score';

$TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
if (empty($TokenTorrents)) {
	$DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
	$TokenTorrents = $DB->to_array('TorrentID');
	$Cache->cache_value('users_tokens_'.$UserID, $TokenTorrents);
}

// Start output
show_header($Title,'comments,status,torrent,bbcode,details,jquery,jquery.cookie');


list($TorrentID, $FileCount, $Size, $Seeders, $Leechers, $Snatched, $FreeTorrent, $DoubleSeed, $TorrentTime, 
		$FileList, $FilePath, $UserID, $Username, $LastActive,
		$BadTags, $BadFolders, $BadFiles, $LastReseedRequest, $LogInDB, $HasFile,
            $ReviewID, $Status, $ConvID, $StatusTime, $KillTime, $StatusDescription, $StatusUserID, $StatusUsername) = $TorrentList[0];

$IsUploader =  $UserID == $LoggedUser['ID'];
$CanEdit = (check_perms('torrents_edit') ||  $IsUploader );

$Reported = false;
unset($ReportedTimes);
$Reports = $Cache->get_value('reports_torrent_'.$TorrentID);
if($Reports === false) {
		$DB->query("SELECT r.ID,
				r.ReporterID,
				r.Type,
				r.UserComment,
				r.ReportedTime
			FROM reportsv2 AS r
			WHERE TorrentID = $TorrentID
				AND Type != 'edited'
				AND Status != 'Resolved'");
		$Reports = $DB->to_array();
		$Cache->cache_value('reports_torrent_'.$TorrentID, $Reports, 0);
}	
        
if (count($Reports) > 0) {
            $Title = "This torrent has ".count($Reports)." active ".(count($Reports) > 1 ?'reports' : 'report');
            $DisplayName .= ' <span style="color: #FF3030; padding: 2px 4px 2px 4px;" title="'.$Title.'">Reported</span>';
}

$Icons = '';
if ( $DoubleSeed == '1' ) {
    $SeedTooltip = "Unlimited Doubleseed"; // a theoretical state?
} elseif (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['DoubleSeed'] > sqltime()) {
    $SeedTooltip = "Personal Doubleseed for ".time_diff($TokenTorrents[$TorrentID]['DoubleSeed'], 2, false);
}
if ($SeedTooltip) 
    $Icons = '<img src="static/common/symbols/doubleseed.gif" alt="DoubleSeed" title="'.$SeedTooltip.'" />&nbsp;&nbsp;';          
 
if ( $FreeTorrent == '1' ) { 
    $FreeTooltip = "Unlimited Freeleech";
}
elseif (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['FreeLeech'] > sqltime()) {
    $FreeTooltip = "Personal Freeleech for ".time_diff($TokenTorrents[$TorrentID]['FreeLeech'], 2, false);
}
if ($FreeTooltip) 
    $Icons .= '<img src="static/common/symbols/freedownload.gif" alt="Freeleech" title="'.$FreeTooltip.'" />&nbsp;&nbsp;';          


 
?>
<div class="details thin">
	<h2><?="$Icons$DisplayName"?></h2>
      
<? 
      

if(check_perms('torrents_review')){
    if (!isset($_GET['checked'])) update_staff_checking("viewing \"".cut_string($GroupName, 32)."\"  #$GroupID", true);
 
?>
    <div id="staff_status" class="status_box">
        <span class="status_loading">loading staff checking status...</span>
    </div>
    <br class="clear"/> 
    <script type="text/javascript">
        setTimeout("Update_status();", 500);
    </script>
<?
}


    if ($Status == 'Warned' || $Status == 'Pending') {
?>
	<div id="warning_status" class="box vertical_space">
		<div class="redbar warning">
                <strong>Status:&nbsp;Warned&nbsp; (<?=$StatusDescription?>)</strong>
            </div>
            <div class="pad"><strong>This torrent has been marked for deletion and will be automatically deleted unless the uploader fixes it. Download at your own risk.</strong><span style="float:right;"><?=time_diff($KillTime)?></span></div>
<?      if ($UserID == $LoggedUser['ID']) { // if the uploader is looking at the warning message 
            if ($Status == 'Warned') { ?>
                <div id="user_message" class="center">If you have fixed this upload make sure you have told the staff: <a class="button greenButton" onclick="Send_Okay_Message(<?=$GroupID?>,<?=($ConvID?$ConvID:0)?>);" title="send staff a message">By clicking here</a></div>
<?          } else {  ?>
                <div id="user_message" class="center"><div class="messagebar"><a href="staffpm.php?action=viewconv&id=<?=$ConvID?>">You sent a message to staff <?=time_diff($StatusTime)?></a></div></div>
<?          }
        }
?>
	</div>
<?
    } 
      $AlertClass = ' hidden';
	if(isset($_GET['did']) && is_number($_GET['did'])) {
          if($_GET['did'] == 1) {
              $ResultMessage ='Successfully edited description';
              $AlertClass = '';
          } elseif($_GET['did'] == 2) {
              $ResultMessage ='Successfully renamed title';
              $AlertClass = '';
          } elseif($_GET['did'] == 3) {
              $ResultMessage = 'Added '. display_str($_GET['addedtag']);
              if (isset($_GET['synonym'])) $ResultMessage .= ' as a synonym of '. display_str($_GET['synonym']);
              $AlertClass = '';
          } elseif($_GET['did'] == 4) {
              $ResultMessage = display_str($_GET['addedtag']). ' is already added.';
              $AlertClass = ' alert';
          } elseif($_GET['did'] == 5) {
              $ResultMessage = display_str($_GET['synonym']). ' is a synonym for '. display_str($_GET['addedtag']). ' which is already added.';
              $AlertClass = ' alert';
          }
          /* if($ResultMessage){ ?>
                  <script type="text/javascript">
                        function Kill_Message(){ setTimeout("$('#messagebar').hide()", 3000); }
                        //addDOMLoadEvent(Kill_Message);
                  </script>
<?        } */
      }
?>
	<div id="messagebarA" class="messagebar<?=$AlertClass?>" title="<?=$ResultMessage?>"><?=$ResultMessage?></div>
                  
	<div class="linkbox" >
    <?	if( $CanEdit) {   ?>
                <a href="torrents.php?action=editgroup&amp;groupid=<?=$GroupID?>">[Edit Torrent]</a>
    <?	} ?> 
    <?	if(has_bookmarked('torrent', $GroupID)) { ?>
                <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" onclick="Unbookmark('torrent', <?=$GroupID?>,'[Bookmark]');return false;">[Remove bookmark]</a>
    <?	} else { ?>
                <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" onclick="Bookmark('torrent', <?=$GroupID?>,'[Remove bookmark]');return false;">[Bookmark]</a>
    <?	} ?>
          <a href="torrents.php?action=grouplog&amp;groupid=<?=$GroupID?>">[View log]</a>

          <a href="reportsv2.php?action=report&amp;id=<?=$TorrentID?>" title="Report">[Report]</a>

    <?	if(check_perms('torrents_delete') || $UserID == $LoggedUser['ID']) { ?>
            <a href="torrents.php?action=delete&amp;torrentid=<?=$TorrentID ?>" title="Remove">[Remove]</a>
    <?	} ?>

	</div>
      <div  class="linkbox">
          
                     <div id="top_info">
                         <table class="boxstat">
                            <tr>
                            <td><?=format_username($UserID, $Username)?> &nbsp;<?=time_diff($TorrentTime);?></td>
                            <td><?=get_size($Size)?></td>
                            <td><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /> <?=number_format($Snatched)?></td>
                            <td><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /> <?=number_format($Seeders)?></td>
                            <td><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /> <?=number_format($Leechers)?></td>
                 <?
                    if ($Status) { // == 'Warned'
                        // not sure if we want to display 'okay' status but for the moment its in
                        echo '<td>'.get_status_icon($Status).'</td>';
                    }
                 ?>
                            </tr>
                         </table>
                       </div>
      </div>
      <div  class="linkbox">
                     <span id="torrent_buttons"  style="float: left;">
                                            <a href="torrents.php?action=download&amp;id=<?=$TorrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="button blueButton" title="Download">DOWNLOAD TORRENT</a>
 
<?	if (($LoggedUser['FLTokens'] > 0) && $HasFile  && (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['FreeLeech'] < sqltime()) && ($FreeTorrent == '0') && ($LoggedUser['CanLeech'] == '1')) { ?>
                                            <a href="torrents.php?action=download&amp;id=<?=$TorrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&usetoken=1" class="button greenButton" title="This will use 1 slot" onClick="return confirm('Are you sure you want to use a freeleech slot here?');">FREELEECH TORRENT</a>
<?	} ?>					
<?	if (($LoggedUser['FLTokens'] > 0) && $HasFile  && (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['DoubleSeed'] < sqltime()) && ($DoubleSeed == '0')) { ?>
                                            <a href="torrents.php?action=download&amp;id=<?=$TorrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&usetoken=2" class="button orangeButton" title="This will use 1 slot" onClick="return confirm('Are you sure you want to use a doubleseed slot here?');">DOUBLESEED TORRENT</a>
<?	} ?>					
                    
                     </span>
          
                     <span style="float: right;"><a id="slide_button"  class="button toggle infoButton" onclick="Details_Toggle();" title="Toggle display">Hide Info</a></span>
	 
<?		if(check_perms('torrents_review')){ ?>
                     <span style="float: right;"><a id="slide_tools_button"  class="button toggle redButton" onclick="Tools_Toggle();" title="Toggle staff tools">Staff Tools</a></span>
<?		} ?>
            <br style="clear:both" />
      </div>
<?

// For staff draw the tools section
if(check_perms('torrents_review')){ 
        // get review history
        if($ReviewID && is_number($ReviewID)) { // if reviewID == null then no history
            $DB->query("SELECT r.Status, r.Time, r.ConvID,
                               IF(r.ReasonID = 0, r.Reason, rs.Description),
                               r.UserID, um.Username  
                      FROM torrents_reviews AS r 
                      LEFT JOIN users_main AS um ON um.ID=r.UserID
                      LEFT JOIN review_reasons AS rs ON rs.ID=r.ReasonID
                      WHERE r.GroupID = $GroupID AND r.ID != $ReviewID ORDER BY Time");
            $NumReviews = $DB->record_count();
        } else $NumReviews = 0;
?>
    <table id="staff_tools" class="pad">
        <form id="form_reviews" action="" method="post">
                <tr class="head">
                    <td colspan="3">
                        <span style="float:left;"><strong>Review Tools</strong></span>
                   <? if($NumReviews>0) { ?>
                        <span style="float:right;"><a href="#" onclick="$('.history').toggle(); this.innerHTML=(this.innerHTML=='(Hide <?=$NumReviews?> Review Logs)'?'(View <?=$NumReviews?> Review Logs)':'(Hide <?=$NumReviews?> Review Logs)'); return false;">(View <?=$NumReviews?> Review Logs)</a></span>&nbsp;
                   <? } ?>   
                    </td>
                </tr>
<? 
    if ($NumReviews>0){ // if there is review history show it
        while(list($Stat, $StatTime, $StatConvID, $StatDescription, $StatUserID, $StatUsername) = $DB->next_record()) { ?>
                <tr class="history hidden">
                    <td width="200px"><strong>Status:</strong>&nbsp;&nbsp;<?=$Stat?"$Stat&nbsp;".get_status_icon($Stat):'Not set'?></td>
                    <td><?=$StatDescription?'<strong>Reason:</strong>&nbsp;&nbsp;'.$StatDescription:''?>
<?
                         if ($StatConvID>0) {
                             echo '<span style="float:right;">'.($Stat=='Pending'?'(user sent fixed message) &nbsp;&nbsp;':'').'<a href="staffpm.php?action=viewconv&id='.$StatConvID.'">'.($Stat=='Pending'?'Message sent to staff':"reply sent to $Username").'</a></span>';
                         } elseif ($Stat == 'Warned') {
                             echo '<span style="float:right;">(pm sent to '.$Username.')</span>';
                         }
?>
                    </td>
                    <td width="25%"><?=$Stat?'<strong>By:</strong>&nbsp;&nbsp;'.format_username($StatUserID, $StatUsername).'&nbsp;'.time_diff($StatTime):'';?></td>
                </tr>      
<?
        }
    } // end show history
?>
                <tr>
                    <td width="200px"><strong>Current Status:</strong>&nbsp;&nbsp;<?=$Status?"$Status&nbsp;".get_status_icon($Status):'Not set'?></td>
                    <td><?=$StatusDescription?'<strong>Reason:</strong>&nbsp;&nbsp;'.$StatusDescription:''?>
                            <? //$ConvID>0?'<span style="float:right;">'.($Status=='Pending'?'(user sent fixed message) &nbsp;&nbsp;':'').'<a href="staffpm.php?action=viewconv&id='.$ConvID.'">'.($Status=='Pending'?'Message sent to staff':"reply sent to $Username").'</a></span>':''?>
<?
                         if ($ConvID>0) {
                             echo '<span style="float:right;">'.($Status=='Pending'?'(user sent fixed message) &nbsp;&nbsp;':'').'<a href="staffpm.php?action=viewconv&id='.$ConvID.'">'.($Status=='Pending'?'Message sent to staff':"reply sent to $Username").'</a></span>';
                         } elseif ($Status == 'Warned') {
                             echo '<span style="float:right;">(pm sent to '.$Username.')</span>';
                         }
?>
                    </td>
                    <td width="25%"><?=$Status?'<strong>By:</strong>&nbsp;&nbsp;'.format_username($StatusUserID, $StatusUsername).'&nbsp;'.time_diff($StatusTime):'';?></td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:right">
                        <input type="hidden" name="action" value="set_review_status" />
                        <input type="hidden" id="groupid" name="groupid" value="<?=$GroupID?>" />
                        <input type="hidden" id="authkey" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" id="convid" name="convid" value="<?=$ConvID?>" />
                        <strong id="warn_insert" class="important_text" style="margin-right:20px;"></strong>
<?              if ( !$Status || $Status == 'Okay' || check_perms('torrents_review_override') ) { // onsubmit="return Validate_Form_Reviews('<?=$Status ')"   ?> 
                        <select id="reasonid" name="reasonid"  onchange="Select_Reason(<?=($Status == 'Warned' || $Status == 'Pending' || $Status == 'Okay')?'true':'false';?>);" >
                            <option value="-1" selected="selected">none&nbsp;&nbsp;</option> 
<? 
                    $DB->query("SELECT ID, Name FROM review_reasons ORDER BY Sort");
                    while(list($ReasonID, $ReasonName) = $DB->next_record()) { ?>
                            
                            <option value="<?=$ReasonID?>"><?=$ReasonName?>&nbsp;&nbsp;</option>
<?                  }    ?>
                            <option value="0">Other&nbsp;&nbsp;</option> 
                        </select>
                        <input id="mark_delete_button" type="submit" name="submit" value="Mark for Deletion" disabled="disabled" title="Mark this torrent for Deletion" />
                      
<?              } else {   ?> 
    
<?              }          ?>
                    </td>
                    <td>
<?              if ($Status == 'Pending'){  // || $Status == 'Warned' ?>
                        <input type="submit" name="submit" value="Accept Fix" title="Accept the fix this uploader has made" />
                        <input type="submit" name="submit" value="Reject Fix" title="Reject the fix this uploader has made" />
<?              } else  {  ?>
                        
                        <input type="submit" name="submit" value="Mark as Okay" <?=($Status=='Okay'||($Status == 'Warned' && !check_perms('torrents_review_override')))?'disabled="disabled" ':''?>title="Mark this torrent as Okay" />
<?                  if ($Status == 'Warned' && check_perms('torrents_review_override') )  {  ?>
                        <strong class="important_text" style="margin-left:10px;">override warned status?</strong>
<?                  }       ?>
<?              }       ?>
                    </td>
                </tr>
                <tr id="review_message" class="hidden">
                    <td colspan="2">
                        <div>
                            <span class="quote_label">
                                <strong>preview of PM that will automatically be sent to <?=format_username($UserID, $Username)?></strong>
                            </span>
                            <blockquote class="bbcode">
                                <span id="message_insert"></span>
                                <textarea id="reason_other" name="reason" class="hidden medium" style="vertical-align: middle;" rows="1" title="The reason entered here is also displayed in the warning notice, ie. keep it short and sweet"></textarea> 
<?
                                echo $Text->full_format(get_warning_message(false, true), true);
?>
                            </blockquote>
                        </div>
                    </td>
                    <td></td>
                </tr>
        </form>
    </table>
    <script type="text/javascript">
         addDOMLoadEvent(Load_Tools_Cookie);
    </script>
<?
} // end draw staff tools 
?>
 <div id="details_top">
    <div class="sidebar" style="float: right;">
<?
        if ($Image!="") {
?>
            <div class="head">
                <strong>Cover</strong>
                <span style="float:right;"><a href="#" id="covertoggle" onclick="Cover_Toggle()">(Hide)</a></span>
            </div>
            <div id="coverimage" class="box box_albumart">
<?
            if ($Image!="") {
                if(check_perms('site_proxy_images')) {
                    $Image = 'http'.($SSL?'s':'').'://'.SITE_URL.'/image.php?i='.urlencode($Image);
                }
?>
			<img style="max-width: 100%;" src="<?=$Image?>" alt="<?=$AltName?>" onclick="lightbox.init(this,220);" />
<?          } else { ?>
			<img src="<?=STATIC_SERVER?>common/noartwork/noimage.png" alt="Click to see full size image" title="Click to see full size image  " width="220" border="0" />
<?
            }
?>
            </div>
            <br/>
<?
        }
?>
            <a id="tags"></a>
            <div class="head">
                <strong><a href="torrents.php?id=<?=$GroupID?>&tsort=<?=$tagsort2?>" title="change sort order of tags to <?=$tagsort2?>">Tags</a></strong>
                
                <span style="float:right;margin-left:5px;"><a href="#" id="tagtoggle" onclick="TagBox_Toggle()">(Hide)</a></span>
                <span style="float:right;font-size:0.8em;">
                    <a href="torrents.php?action=tag_synonyms" target="_blank">synonyms</a> | <a href="articles.php?topic=tag" target="_blank">Tagging rules</a>
                </span>
            </div>
        <div id="tag_container" class="box box_tags">			
                <div class="tag_inner">
<?
if(count($Tags) > 0) {
?>
                          <ul id="torrent_tags" class="stats nobullet">
                                <li class="tag_warning">
                                    Please vote for tags based <a href="articles.php?topic=tag" target="_blank"><strong class="important_text">only</strong></a> on their appropriateness for this upload.
                                </li>  
        <?
                
            foreach($Tags as $TagKey=>$Tag) {

        ?>
                                <li id="tlist<?=$Tag['id']?>">
                                      <a href="torrents.php?taglist=<?=$Tag['name']?>" style="float:left; display:block;"><?=display_str($Tag['name'])?></a>
                                      <div style="float:right; display:block; letter-spacing: -1px;">
        <?		if(check_perms('site_vote_tag') || ($IsUploader && $LoggedUser['ID']==$Tag['userid'])){  ?>
                                      <a title="Vote down tag '<?=$Tag['name']?>'" href="#tags" onclick="Vote_Tag(<?="'{$Tag['name']}',{$Tag['id']},$GroupID,'down'"?>)" style="font-family: monospace;" >[-]</a>
                                      <span id="tagscore<?=$Tag['id']?>" style="width:10px;text-align:center;display:inline-block;"><?=$Tag['score']?></span>
                                      <a title="Vote up tag '<?=$Tag['name']?>'" href="#tags" onclick="Vote_Tag(<?="'{$Tag['name']}',{$Tag['id']},$GroupID,'up'"?>)" style="font-family: monospace;">[+]</a>
      
        <?          
                  } else {  // cannot vote on tags ?>
                                      <span style="width:10px;text-align:center;display:inline-block;" title="You do not have permission to vote on tags"><?=$Tag['score']?></span>
                                      <span style="font-family: monospace;" >&nbsp;&nbsp;&nbsp;</span>
                                      
        <?		} ?>
        <?		if(check_perms('users_warn')){ ?>
                                      <a title="User that added tag '<?=$Tag['name']?>'" href="user.php?id=<?=$Tag['userid']?>" >[U]</a>
        <?		} ?>
        <?		if(check_perms('site_delete_tag') ) { // || ($IsUploader && $LoggedUser['ID']==$Tag['userid']) 
                                  /*    <a title="Delete tag '<?=$Tag['name']?>'" href="torrents.php?action=delete_tag&amp;groupid=<?=$GroupID?>&amp;tagid=<?=$Tag['id']?>&amp;auth=<?=$LoggedUser['AuthKey']?>" style="font-family: monospace;">[X]</a> */
                                   ?>
                                   <a title="Delete tag '<?=$Tag['name']?>'" href="#tags" onclick="Del_Tag(<?="'{$Tag['id']}',$GroupID,'$tagsort'"?>)"   style="font-family: monospace;">[X]</a>
        <?		} else { ?>
                                      <span style="font-family: monospace;">&nbsp;&nbsp;&nbsp;</span>
        <?		} ?>
                                      </div>
                                      <br style="clear:both" />
                                </li>
        <?
            }
        ?>
                          </ul>
<?
} else {
?>
			Please add a tag for this torrent!
<?
} // action="torrents.php" 
?>
                </div>
<?      if(check_perms('site_add_tag') || $IsUploader){ ?>
			<div class="tag_add">
	<div id="messagebar" class="messagebar hidden"></div>
				<form id="form_addtag" action="" method="post" onsubmit="return false;">
					<input type="hidden" name="action" value="add_tag" />
					<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
					<input type="hidden" name="groupid" value="<?=$GroupID?>" />
					<input type="hidden" name="tagsort" value="<?=$tagsort?>" />
					<input type="text" id="tagname" name="tagname" size="15" onkeydown="if (event.keyCode == 13) { Add_Tag(); return false; }" />
					<input type="button" value="+" onclick="Add_Tag();" />
				</form>
			</div>
<?      }       ?>
            </div>
	</div>
	<div class="middle_column">
            <div class="head">Torrent Info</div>
		<table class="torrent_table">
			<tr class="colhead">
				<td></td>
                        <td width="80%">
                          Name
                        </td>
				<td>Size</td>
				<td class="sign"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></td>
				<td class="sign"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></td>
				<td class="sign"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></td>
			</tr>
<?

function filelist($Str) {
	return "</td><td>".get_size($Str[1])."</td></tr>";
}

$EditionID = 0;

        // The report array has been moved up above the display name so "reported" could be added to the title.
        if(count($Reports) > 0) {
		$Reported = true;
		include(SERVER_ROOT.'/sections/reportsv2/array.php');
		$ReportInfo = "<table><tr class='smallhead'><td>This torrent has ".count($Reports)." active ".(count($Reports) > 1 ?'reports' : 'report').":</td></tr>";

		foreach($Reports as $Report) {
			list($ReportID, $ReporterID, $ReportType, $ReportReason, $ReportedTime) = $Report;

			$Reporter = user_info($ReporterID);
			$ReporterName = $Reporter['Username'];

			if (array_key_exists($ReportType, $Types)) {
				$ReportType = $Types[$ReportType];
			} else {
				//There was a type but it wasn't an option!
				$ReportType = $Types['other'];
			}
			$ReportInfo .= "<tr><td>".(check_perms('admin_reports') ? "<a href='user.php?id=$ReporterID'>$ReporterName</a> <a href='reportsv2.php?view=report&amp;id=$ReportID'>reported it</a> " : "Someone reported it ").time_diff($ReportedTime,2,true,true)." for the reason '".$ReportType['title']."':";
			$ReportInfo .= "<blockquote>".$Text->full_format($ReportReason)."</blockquote></td></tr>";
		}
		$ReportInfo .= "</table>";
	}
	
	$FileList = str_replace(array('_','-'), ' ', $FileList);
	$FileList = str_replace('|||','<tr><td>',display_str($FileList));
	$FileList = preg_replace_callback('/\{\{\{([^\{]*)\}\}\}/i','filelist',$FileList);
	$FileList = '<table style="overflow-x:auto;"><tr class="smallhead"><td colspan="2">'.(empty($FilePath) ? '/' : '/'.$FilePath.'/' ).'</td></tr><tr class="rowa"><td><strong><div style="float: left; display: block;">File Name'.(check_perms('users_mod') ? ' [<a href="torrents.php?action=regen_filelist&amp;torrentid='.$TorrentID.'">Regenerate</a>]' : '').'</div></strong></td><td><strong>Size</strong></td></tr><tr><td>'.$FileList."</td></tr></table>";

	$TorrentUploader = $Username; // Save this for "Uploaded by:" below

	// similar to torrent_info()


	$ExtraInfo = $GroupName;
        $AddExtra = ' / ';

	if($FreeTorrent == '1') { $ExtraInfo.=$AddExtra.'<strong>Freeleech!</strong>'; $AddExtra=' / '; }
	if($FreeTorrent == '2') { $ExtraInfo.=$AddExtra.'<strong>Neutral Leech!</strong>'; $AddExtra=' / '; }
	if(!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['Type'] == 'leech') { $ExtraInfo.=$AddExtra.'<strong>Personal Freeleech!</strong>'; $AddExtra=' / '; }
	if(!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['Type'] == 'seed') { $ExtraInfo.=$AddExtra.'<strong>Personal Doubleseed!</strong>'; $AddExtra=' / '; }
	if($Reported) { $ExtraInfo.=$AddExtra.'<strong>Reported</strong>'; $AddExtra=' / '; }
	if(!empty($BadTags)) { $ExtraInfo.=$AddExtra.'<strong>Bad Tags</strong>'; $AddExtra=' / '; }
	if(!empty($BadFolders)) { $ExtraInfo.=$AddExtra.'<strong>Bad Folders</strong>'; $AddExtra=' / '; }
	if(!empty($BadFiles)) { $ExtraInfo.=$AddExtra.'<strong>Bad File Names</strong>'; $AddExtra=' / '; }
	
?>

			<tr class="groupid_<?=$GroupID?> edition_<?=$EditionID?> group_torrent" style="font-weight: normal;" id="torrent<?=$TorrentID?>">
                      <td class="center cats_col" rowspan="2" style="border-bottom:none;border-right:none;">
                         <? $CatImg = 'static/common/caticons/' . $NewCategories[$GroupCategoryID]['image']; ?>
                         <div title="<?= $NewCategories[$GroupCategoryID]['tag'] ?>"><img src="<?= $CatImg ?>" /></div>
                      </td>
                      <td style="border-bottom:none;border-left:none;">
                          <strong><?=$ExtraInfo; ?></strong>
						<!-- Uploaded by <?=format_username($UserID, $TorrentUploader)?> <?=time_diff($TorrentTime);?> -->

                      </td>
				<td class="nobr"><?=get_size($Size)?></td>
				<td><?=number_format($Snatched)?></td>
				<td><?=number_format($Seeders)?></td>
				<td><?=number_format($Leechers)?></td>
			</tr>
                  <tr>
                      <td colspan="5" class="right" style="border-top:none;border-bottom:none;border-left:none;">
                          <em>Uploaded by   <?=format_username($UserID, $TorrentUploader)?> <?=time_diff($TorrentTime);?> </em>
                          
                      </td>
                  </tr>
			<tr class="groupid_<?=$GroupID?> edition_<?=$EditionID?> torrentdetails pad" id="torrent_<?=$TorrentID; ?>">
				<td colspan="6" style="border-top:none;"> 
                            
<? if($Seeders == 0){ ?>            
                            <blockquote  style="text-align: center;">
						<?
						if ($LastActive != '0000-00-00 00:00:00' && time() - strtotime($LastActive) >= 432000) { ?>
							<strong>Last active: <?=time_diff($LastActive);?></strong>
						<?} else { ?>
                                          Last active: <?=time_diff($LastActive);?>
						<?} ?>
						<?
						if ($LastActive != '0000-00-00 00:00:00' && time() - strtotime($LastActive) >= 345678 && time()-strtotime($LastReseedRequest)>=864000) { ?>
						<a href="torrents.php?action=reseed&amp;torrentid=<?=$TorrentID?>&amp;groupid=<?=$GroupID?>"> [Request re-seed] </a>
						<?} ?>
                            </blockquote>
<? } ?>
                                    
<? if(check_perms('site_moderate_requests')) { ?>
					<div class="linkbox">
						<a href="torrents.php?action=masspm&amp;id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>">[Mass PM Snatchers]</a>
					</div>
<? } ?>
					<div class="linkbox">
						<a href="#" onclick="show_peers('<?=$TorrentID?>', 0);return false;">(View Peerlist)</a>
<? if(check_perms('site_view_torrent_snatchlist')) { ?> 
						<a href="#" onclick="show_downloads('<?=$TorrentID?>', 0);return false;">(View Downloadlist)</a>
						<a href="#" onclick="show_snatches('<?=$TorrentID?>', 0);return false;">(View Snatchlist)</a>
<? } ?>
						<a href="#" onclick="show_files('<?=$TorrentID?>');return false;">(View Filelist)</a>
<? if($Reported) { ?> 
						<a href="#" onclick="show_reported('<?=$TorrentID?>');return false;">(View Report Information)</a>
<? } ?>
					</div>
					<div id="peers_<?=$TorrentID?>" class="hidden"></div>
					<div id="downloads_<?=$TorrentID?>" class="hidden"></div>
					<div id="snatches_<?=$TorrentID?>" class="hidden"></div>
					<div id="files_<?=$TorrentID?>" class="hidden"><?=$FileList?></div>
<?  if($Reported) { ?> 
					<div id="reported_<?=$TorrentID?>"><?=$ReportInfo?></div>
<? } ?>
				</td>
			</tr>
		</table>
<?
$Requests = get_group_requests($GroupID);
if (count($Requests) > 0) {
	$i = 0;
?>
		<div class="box">
			<div class="head"><span style="font-weight: bold;">Requests (<?=count($Requests)?>)</span> <span style="float:right;"><a href="#" onClick="$('#requests').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Show)</a></span></div>
			<table id="requests" class="hidden">
				<tr class="head">
					<td>Format / Bitrate / Media</td>
					<td>Votes</td>
					<td>Bounty</td>
				</tr>
<?	foreach($Requests as $Request) {
		$RequestVotes = get_votes_array($Request['ID']);
?>
				<tr class="requestrows <?=(++$i%2?'rowa':'rowb')?>">
					<td><a href="requests.php?action=view&id=<?=$Request['ID']?>"></a></td>
					<td>
						<form id="form_<?=$Request['ID']?>">
							<span id="vote_count_<?=$Request['ID']?>"><?=count($RequestVotes['Voters'])?></span>
							<input type="hidden" id="requestid_<?=$Request['ID']?>" name="requestid" value="<?=$Request['ID']?>" />
							<input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
							&nbsp;&nbsp; <a href="javascript:Vote(0, <?=$Request['ID']?>)"><strong>(+)</strong></a>
						</form>
					</td>
					<td><?=get_size($RequestVotes['TotalBounty'])?></td>
				</tr>
<?	} ?>
			</table>
		</div>
<?
}
$Collages = $Cache->get_value('torrent_collages_'.$GroupID);
if(!is_array($Collages)) {
	$DB->query("SELECT c.Name, c.NumTorrents, c.ID FROM collages AS c JOIN collages_torrents AS ct ON ct.CollageID=c.ID WHERE ct.GroupID='$GroupID' AND Deleted='0' AND CategoryID!='0'");
	$Collages = $DB->to_array();
	$Cache->cache_value('torrent_collages_'.$GroupID, $Collages, 3600*6);
}
if(count($Collages)>0) {
?>
		<table id="collages">
			<tr class="head">
				<td width="85%">Collage name</td>
				<td># torrents</td>
			</tr>
<?	foreach ($Collages as $Collage) { 
		list($CollageName, $CollageTorrents, $CollageID) = $Collage;
?>
			<tr>
				<td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
				<td><?=$CollageTorrents?></td>
			</tr>
<?	} ?>
		</table>
<?
}

$PersonalCollages = $Cache->get_value('torrent_collages_personal_'.$GroupID);
if(!is_array($PersonalCollages)) {
	$DB->query("SELECT c.Name, c.NumTorrents, c.ID FROM collages AS c JOIN collages_torrents AS ct ON ct.CollageID=c.ID WHERE ct.GroupID='$GroupID' AND Deleted='0' AND CategoryID='0'");
	$PersonalCollages = $DB->to_array(false, MYSQL_NUM);
	$Cache->cache_value('torrent_collages_personal_'.$GroupID, $PersonalCollages, 3600*6);
}

if(count($PersonalCollages)>0) { 
	if (count($PersonalCollages) > MAX_PERS_COLLAGES) {
		// Pick 5 at random
		$Range = range(0,count($PersonalCollages) - 1);
		shuffle($Range);
		$Indices = array_slice($Range, 0, MAX_PERS_COLLAGES);
		$SeeAll = ' <a href="#" onClick="$(\'.personal_rows\').toggle(); return false;">(See all)</a>';
	} else {
		$Indices = range(0, count($PersonalCollages)-1);
		$SeeAll = '';
	}
?>
		<table id="personal_collages">
			<tr class="head">
				<td width="85%">This torrent is in <?=count($PersonalCollages)?> personal collage<?=((count($PersonalCollages)>1)?'s':'')?><?=$SeeAll?></td>
				<td># torrents</td>
			</tr>
<?	foreach ($Indices as $i) { 
		list($CollageName, $CollageTorrents, $CollageID) = $PersonalCollages[$i];
		unset($PersonalCollages[$i]);
?>
			<tr>
				<td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
				<td><?=$CollageTorrents?></td>
			</tr>
<?	}
	foreach ($PersonalCollages as $Collage) { 
		list($CollageName, $CollageTorrents, $CollageID) = $Collage;
?>
			<tr class="personal_rows hidden">
				<td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
				<td><?=$CollageTorrents?></td>
			</tr>
<?	} ?>
		</table>
<?
}

?>
           
        </div>
      <div style="clear:both"></div>
    </div>
      <div style="clear:both"></div>
	<div class="main_column">
		<div class="head">
                <strong>Description</strong>
                <span style="float:right;"><a href="#" onclick="$('#descbox').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(Hide)</a></span>
            </div>
		<div class="box">
			<div id="descbox" class="body">
<? 
                        $PermissionsInfo = get_permissions_for_user($UserID);
                        if($Body!='') {
                            $Body = $Text->full_format($Body, isset($PermissionsInfo['site_advanced_tags']) &&  $PermissionsInfo['site_advanced_tags'] );
                            echo $Body;
                        } else 
                            echo "There is no information on this torrent.";
?>
                  </div>
		</div>
            
		<div class="head">
            </div>
		<div class="box pad center">
<?

    $Thanks = $Cache->get_value('torrent_thanks_'.$GroupID);
    if($Thanks === false) {
          $DB->query("SELECT Thanks FROM torrents WHERE GroupID = '$GroupID'");
          list($Thanks) = $DB->next_record();
          $Cache->cache_value('torrent_thanks_'.$GroupID, $Thanks);
    }
    if (!$IsUploader && (!$Thanks || strpos($Thanks, $LoggedUser['Username'])===false )) {
?>
                <form action="torrents.php" method="post" id="thanksform">
                    <input type="hidden" name="action" value="thank" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
			  <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="button" onclick="Say_Thanks()" value="Thank the uploader!" class=" center" style="font-weight:bold;font-size:larger;" />
               </form>
<?  }   ?>
                <div  id="thanksdiv" class="pad<?if(!$Thanks)echo' hidden';?>" style="text-align:left">
                    <p><strong>The following people said thanks!</strong> &nbsp;<span id="thankstext"><?=$Thanks?></span></p>
                </div>
		</div>
<? 

$Results = $Cache->get_value('torrent_comments_'.$GroupID);
if($Results === false) {
	$DB->query("SELECT
			COUNT(c.ID)
			FROM torrents_comments as c
			WHERE c.GroupID = '$GroupID'");
	list($Results) = $DB->next_record();
	$Cache->cache_value('torrent_comments_'.$GroupID, $Results, 0);
}

if(isset($_GET['postid']) && is_number($_GET['postid']) && $Results > TORRENT_COMMENTS_PER_PAGE) {
	$DB->query("SELECT COUNT(ID) FROM torrents_comments WHERE GroupID = $GroupID AND ID <= $_GET[postid]");
	list($PostNum) = $DB->next_record();
	list($Page,$Limit) = page_limit(TORRENT_COMMENTS_PER_PAGE,$PostNum);
} else {
	list($Page,$Limit) = page_limit(TORRENT_COMMENTS_PER_PAGE,$Results);
}

//Get the cache catalogue
$CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
$CatalogueLimit=$CatalogueID*THREAD_CATALOGUE . ', ' . THREAD_CATALOGUE;

//---------- Get some data to start processing

// Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
$Catalogue = $Cache->get_value('torrent_comments_'.$GroupID.'_catalogue_'.$CatalogueID);
if($Catalogue === false) {
	$DB->query("SELECT
			c.ID,
			c.AuthorID,
			c.AddedTime,
			c.Body,
			c.EditedUserID,
			c.EditedTime,
			u.Username
			FROM torrents_comments as c
			LEFT JOIN users_main AS u ON u.ID=c.EditedUserID
                  LEFT JOIN users_main AS a ON a.ID = c.AuthorID
			WHERE c.GroupID = '$GroupID'
			ORDER BY c.ID
			LIMIT $CatalogueLimit");
	$Catalogue = $DB->to_array(false,MYSQLI_ASSOC, array('Badges'));
	$Cache->cache_value('torrent_comments_'.$GroupID.'_catalogue_'.$CatalogueID, $Catalogue, 0);
}

//This is a hybrid to reduce the catalogue down to the page elements: We use the page limit % catalogue
$Thread = array_slice($Catalogue,((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)%THREAD_CATALOGUE),TORRENT_COMMENTS_PER_PAGE,true);
?>
	<div class="linkbox"><a name="comments"></a>
<?
$Pages=get_pages($Page,$Results,TORRENT_COMMENTS_PER_PAGE,9,'#comments');
echo $Pages;
?>
	</div>
<?

//---------- Begin printing
foreach($Thread as $Key => $Post){
	list($PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = array_values($Post);
	list($AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle,,,$Signature) = array_values(user_info($AuthorID));
      $AuthorPermissions = get_permissions($PermissionID);
      list($ClassLevel,$PermissionValues,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight)=array_values($AuthorPermissions);
      // we need to get custom permissions for this author
      //$PermissionValues = get_permissions_for_user($AuthorID, false, $AuthorPermissions);
?>
<table class="forum_post box vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>" id="post<?=$PostID?>">
	<tr class="smallhead">
		<td colspan="2">
			<span style="float:left;"><a class="post_id" href='torrents.php?id=<?=$GroupID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>'>#<?=$PostID?></a>
				<?=format_username($AuthorID, $Username, $Donor, $Warned, $Enabled == 2 ? false : true, $PermissionID, $UserTitle, true)?> <?=time_diff($AddedTime)?> <a href="reports.php?action=report&amp;type=torrents_comment&amp;id=<?=$PostID?>">[Report]</a>
				- <a href="#quickpost" onclick="Quote('<?=$PostID?>','t<?=$GroupID?>','<?=$Username?>');">[Quote]</a>
<?if (($AuthorID == $LoggedUser['ID'] && time_ago($AddedTime)<USER_EDIT_POST_TIME) || check_perms('site_moderate_forums')){ ?>
                        - <a href="#post<?=$PostID?>" onclick="Edit_Form('<?=$PostID?>','<?=$Key?>');">[Edit]</a><? }
  if (check_perms('site_admin_forums')){ ?> 
                        - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');">[Delete]</a> <? } ?>
			</span>
			<span id="bar<?=$PostID?>" style="float:right;">
				<a href="#">&uarr;</a>
			</span>
		</td>
	</tr>
	<tr>
<? if(empty($HeavyInfo['DisableAvatars'])) {?>
		<td class="avatar" valign="top" rowspan="2">
	<? if ($Avatar) { ?>
			<img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$Username ?>'s avatar" />
	<? } else { ?>
			<img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
	<?
         }
        $UserBadges = get_user_badges($AuthorID); 
        if( !empty($UserBadges) ) {  ?>
               <div class="badges">
<?                  print_badges_array($UserBadges, $AuthorID); ?>
               </div>
<?      }      ?>
		</td>
<?
}
$AllowTags= get_permissions_advtags($AuthorID, false, $AuthorPermissions);
?>
		<td class="postbody" valign="top">
			<div id="content<?=$PostID?>" class="post_container">
                      <div class="post_content"><?=$Text->full_format($Body, $AllowTags) ?> </div>
          
                      
<? if($EditedUserID){ ?>  
                        <div class="post_footer">
<?	if(check_perms('site_moderate_forums')) { ?>
				<a href="#content<?=$PostID?>" onclick="LoadEdit('torrents', <?=$PostID?>, 1); return false;">&laquo;</a> 
<? 	} ?>
                        <span class="editedby">Last edited by
				<?=format_username($EditedUserID, $EditedUsername) ?> <?=time_diff($EditedTime,2,true,true)?>
                        </span>
                        </div>
        <? }   ?>  
			</div>
		</td>
	</tr>
<? 
      if( empty($HeavyInfo['DisableSignatures']) && ($MaxSigLength > 0) && !empty($Signature) ) { //post_footer
                        
            echo '
      <tr>
            <td class="sig"><div id="sig"><div>' . $Text->full_format($Signature, $AllowTags) . '</div></div></td>
      </tr>';
           }
?>
</table>
<?	} ?>
		<div class="linkbox">
		<?=$Pages?>
		</div>
<?
if(!$LoggedUser['DisablePosting']) { ?>
			<br />
			<div class="messagecontainer" id="container"><div id="message" class="hidden center messagebar"></div></div>
                  <div class="head">Post comment</div>
			<div class="box pad">
				<table id="quickreplypreview" class="forum_post box vertical_margin hidden" style="text-align:left;">
					<tr class="colhead_dark">
						<td colspan="2">
							<span style="float:left;"><a href='#quickreplypreview'>#XXXXXX</a>
								by <strong><?=format_username($LoggedUser['ID'], $LoggedUser['Username'], $LoggedUser['Donor'], $LoggedUser['Warned'], $LoggedUser['Enabled'] == 2 ? false : true, $LoggedUser['PermissionID'], false, true)?></strong>
							Just now
							<a href="#quickreplypreview">[Report Comment]</a>
							</span>
							<span id="barpreview" style="float:right;">
								<a href="#">&uarr;</a>
							</span>
						</td>
					</tr>
					<tr>
						<td class="avatar" valign="top">
                              <? if (!empty($LoggedUser['Avatar'])) {  ?>
                                            <img src="<?=$LoggedUser['Avatar']?>" class="avatar" style="<?=get_avatar_css($LoggedUser['MaxAvatarWidth'], $LoggedUser['MaxAvatarHeight'])?>" alt="<?=$LoggedUser['Username']?>'s avatar" />
                               <? } else { ?>
                                          <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
                              <? } ?>
						</td>
						<td class="body" valign="top">
							<div id="contentpreview" style="text-align:left;"></div>
						</td>
					</tr>
				</table>
				<form id="quickpostform" action="" method="post" onsubmit="return Validate_Form('message','quickpost')" style="display: block; text-align: center;">
					<div id="quickreplytext">
						<input type="hidden" name="action" value="reply" />
						<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
						<input type="hidden" name="groupid" value="<?=$GroupID?>" />
                            <? $Text->display_bbcode_assistant("quickpost", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
						<textarea id="quickpost" name="body" class="long"  rows="8"></textarea> <br />
					</div>
					<input id="post_preview" type="button" value="Preview" onclick="if(this.preview){Quick_Edit();}else{Quick_Preview();}" />
					<input type="submit" value="Post comment" />
				</form>
			</div>
<? } ?>
	</div>
</div>
<?

show_footer();
?>

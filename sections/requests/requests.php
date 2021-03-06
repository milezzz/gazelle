<?

$Queries = array();

$OrderWays = array('votes', 'bounty', 'created', 'lastvote', 'filled');
list($Page,$Limit) = page_limit(REQUESTS_PER_PAGE);
$Submitted = !empty($_GET['submit']);
					
//Paranoia					
$UserInfo = user_info((int)$_GET['userid']);
$Perms = get_permissions($UserInfo['PermissionID']);
$UserClass = $Perms['Class'];

$BookmarkView = false;

if(empty($_GET['type'])) { 
	$Title = 'Requests';
	if(!check_perms('site_see_old_requests') || empty($_GET['showall'])) {
		$SS->set_filter('visible', array(1));
	}
} else {
	switch($_GET['type']) {
		case 'created':
			$Title = 'My requests';
			$SS->set_filter('userid', array($LoggedUser['ID']));
			break;
		case 'voted':
			if(!empty($_GET['userid'])) {
				if(is_number($_GET['userid'])) {
					if (!check_paranoia('requestsvoted_list', $UserInfo['Paranoia'], $Perms['Class'], $_GET['userid'])) { error(PARANOIA_MSG); }
					$Title = "Requests voted for by ".$UserInfo['Username'];
					$SS->set_filter('voter', array($_GET['userid']));
				} else {
					error(404);
				}
			} else {
				$Title = "Requests I've voted on";
				$SS->set_filter('voter', array($LoggedUser['ID']));
			}
			break;
		case 'filled':
			if(empty($_GET['userid']) || !is_number($_GET['userid'])) {
				error(404);
			} else {
				if (!check_paranoia('requestsfilled_list', $UserInfo['Paranoia'], $Perms['Class'], $_GET['userid'])) { error(PARANOIA_MSG); }
				$Title = "Requests filled by ".$UserInfo['Username'];
				$SS->set_filter('fillerid', array($_GET['userid']));
			}
			break;
		case 'bookmarks':
			$Title = 'Your bookmarked requests';
			$BookmarkView = true;
			$SS->set_filter('bookmarker', array($LoggedUser['ID']));
			break;
		default:
			error(404);
	}
}

if($Submitted && empty($_GET['show_filled'])) {
	$SS->set_filter('torrentid', array(0));
}

if(!empty($_GET['search'])) {
	$Words = explode(' ', $_GET['search']);
	foreach($Words as $Key => &$Word) {
		if($Word[0] == '!' && strlen($Word) > 2) {
			if(strpos($Word,'!',1) === false) {
				$Word = '!'.$SS->EscapeString(substr($Word,1));
			} else {
				$Word = $SS->EscapeString($Word);
			}
		} elseif(strlen($Word) >= 2) {
			$Word = $SS->EscapeString($Word);
		} else {
			unset($Words[$Key]);
		}
	}
	if(!empty($Words)) {
		$Queries[] = "@* ".implode(' ', $Words);
	}
}

if(!empty($_GET['tags'])){
        $Tags = cleanup_tags($_GET['tags']);
	$Tags = array_unique(explode(' ', $Tags));
	$TagNames = array();
	foreach ($Tags as $Tag) {
		$Tag = sanitize_tag($Tag);
		if(!empty($Tag)) {
			$TagNames[] = $Tag;
		}
	}
	$Tags = get_tags($TagNames);
}

if(empty($_GET['tags_type']) && !empty($Tags)) {
	$_GET['tags_type'] = '0';
	$SS->set_filter('tagid', array_keys($Tags));
} elseif(!empty($Tags)) {
	foreach(array_keys($Tags) as $Tag) {
		$SS->set_filter('tagid', array($Tag));
	}
} else {
	$_GET['tags_type'] = '1';
}

if(!empty($_GET['filter_cat'])) {
	$Keys = array_keys($_GET['filter_cat']);
	$SS->set_filter('categoryid', $Keys);
}

if(!empty($_GET['requestor']) && check_perms('site_see_old_requests')) {
	if(is_number($_GET['requestor'])) {
		$SS->set_filter('userid', array($_GET['requestor']));
	} else {
		error(404);
	}
}

if(!empty($_GET['page']) && is_number($_GET['page'])) {
	$Page = min($_GET['page'], 50000/REQUESTS_PER_PAGE);
	$SS->limit(($Page - 1) * REQUESTS_PER_PAGE, REQUESTS_PER_PAGE, 50000);
} else {
	$Page = 1;
	$SS->limit(0, REQUESTS_PER_PAGE, 50000);
}

if(empty($_GET['order'])) {
	$CurrentOrder = 'created';
	$CurrentSort = 'desc';
	$Way = SPH_SORT_ATTR_DESC;
	$NewSort = 'asc';
} else {
	if(in_array($_GET['order'], $OrderWays)) {
		$CurrentOrder = $_GET['order'];
		if($_GET['sort'] == 'asc' || $_GET['sort'] == 'desc') {
			$CurrentSort = $_GET['sort'];
			$Way = ($CurrentSort == 'asc' ? SPH_SORT_ATTR_ASC : SPH_SORT_ATTR_DESC);
			$NewSort = ($_GET['sort'] == 'asc' ? 'desc' : 'asc');
		} else {
			error(404);
		}
	} else {
		error(404);
	}
}

switch($CurrentOrder) {
	case 'votes' :
		$OrderBy = "Votes";
		break;
	case 'bounty' :
		$OrderBy = "Bounty";
		break;
	case 'created' :
		$OrderBy = "TimeAdded";
		break;
	case 'lastvote' :
		$OrderBy = "LastVote";
		break;
	case 'filled' :
		$OrderBy = "TimeFilled";
		break;
	default :
		$OrderBy = "TimeAdded";
		break;
}
//print($Way); print($OrderBy); die();
$SS->SetSortMode($Way, $OrderBy);

if(count($Queries) > 0) {
	$Query = implode(' ',$Queries);
} else {
	$Query='';
}

$SS->set_index('requests requests_delta');
$SphinxResults = $SS->search($Query, '', 0, array(), '', '');
$NumResults = $SS->TotalResults;
//We don't use sphinxapi's default cache searcher, we use our own functions

if(!empty($SphinxResults['notfound'])) {
	$SQLResults = get_requests($SphinxResults['notfound']);
	if(is_array($SQLResults['notfound'])) {
		//Something wasn't found in the db, remove it from results
		reset($SQLResults['notfound']);
		foreach($SQLResults['notfound'] as $ID) {
			unset($SQLResults['matches'][$ID]);
			unset($SphinxResults['matches'][$ID]);
		}
	}
	
	// Merge SQL results with memcached results
	foreach($SQLResults['matches'] as $ID => $SQLResult) {
		$SphinxResults['matches'][$ID] = $SQLResult;
		
		//$Requests['matches'][$ID] = array_merge($Requests['matches'][$ID], $SQLResult);
		//We ksort because depending on the filter modes, we're given our data in an unpredictable order
		//ksort($Requests['matches'][$ID]);
	}
}

$PageLinks = get_pages($Page, $NumResults, REQUESTS_PER_PAGE);

$Requests = $SphinxResults['matches'];

$CurrentURL = get_url(array('order', 'sort'));

show_header($Title, 'requests');

?>
<div class="thin">
	<div class="linkbox">
<?	if (!$BookmarkView) { ?>
<?		if(check_perms('site_submit_requests')){ ?> 
		<a href="requests.php?action=new">[New request]</a>
		<a href="requests.php?type=created">[My requests]</a>
<?		} else { ?>
            <em> <a href="articles.php?topic=requests">You must be a Good Perv with a ratio of at least 1.05 to be able to make a Request.</a></em>
<?          }
		if(check_perms('site_vote')){?> 
		<a href="requests.php?type=voted">[Requests I've voted on]</a>
<?		} ?>
<?	} else { ?>
		<a href="bookmarks.php?type=torrents">[Torrents]</a>
		<a href="bookmarks.php?type=collages">[Collages]</a>
		<a href="bookmarks.php?type=requests">[Requests]</a>
<?	} ?>
	</div>
    	<div class="head"><?=$Title?></div>

	<div>
		<form action="" method="get">
<?	if ($BookmarkView) { ?>
			<input type="hidden" name="action" value="view" />
			<input type="hidden" name="type" value="requests" />
<?	} else { ?>
			<input type="hidden" name="type" value="<?=$_GET['type']?>" />
<?	} ?>
			<input type="hidden" name="submit" value="true" />
<?	if(!empty($_GET['userid']) && is_number($_GET['userid'])) { ?>
			<input type="hidden" name="userid" value="<?=$_GET['userid']?>" />
<?	} ?>
			<table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
				<tr>
					<td class="label">Search terms:</td>
					<td>
						<input type="text" name="search" size="75" value="<?if(isset($_GET['search'])) { echo display_str($_GET['search']); } ?>" />
					</td>
				</tr>
				<tr>
					<td class="label">Tags:</td>
					<td>
						<input type="text" name="tags" size="60" value="<?= (!empty($TagNames) ? display_str(implode(' ', $TagNames)) : '') ?>" />&nbsp;
						<input type="radio" name="tags_type" id="tags_type0" value="0" <?selected('tags_type',0,'checked')?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
						<input type="radio" name="tags_type" id="tags_type1" value="1"  <?selected('tags_type',1,'checked')?> /><label for="tags_type1"> All</label>
					</td>
				</tr>
				<tr>
					<td class="label">Include filled:</td>
					<td>
						<input type="checkbox" name="show_filled" <? if(!$Submitted || !empty($_GET['show_filled']) || (!$Submitted && !empty($_GET['type']) && $_GET['type'] == "filled")) { ?>checked="checked"<? } ?> />
					</td>
				</tr>
<?	if(check_perms('site_see_old_requests')){ ?> 
				<tr>
					<td class="label">Include old:</td>
					<td>
						<input type="checkbox" name="showall" <? if(!empty($_GET['showall'])) {?>checked="checked"<? } ?> />
					</td>
				</tr>
<?	/* ?> 
				<tr>
					<td class="label">Requested by:</td>
					<td>
						<input type="text" name="requester" size="75" value="<?=display_str($_GET['requester'])?>" />
					</td>
				</tr>
<?	*/} ?>
			</table>
			<table class="cat_list">
<?
$x=0;
reset($NewCategories);
$row = 'a';
foreach($NewCategories as $Cat) {
	if($x%7==0) {
		if($x > 0) {
?>
			</tr>
<?		} ?>
			<tr class="row<?=$row?>">
<?
            $row = ($row == 'a' ? 'b' :'a');
	}
	$x++;
?>
				<td>
					<input type="checkbox" name="filter_cat[<?=($Cat['id'])?>]" id="cat_<?=($Cat['id'])?>" value="1" <? if(isset($_GET['filter_cat'][$Cat['id']])) { ?>checked="checked"<? } ?>/>
					<label for="cat_<?=($Cat['id'])?>"><a href="requests.php?filter_cat[<?=$Cat['id']?>]=1"><?= $Cat['name'] ?></a></label>
				</td>
<?}?>                           
                                <td colspan="<?=7-($x%7)?>"></td>
                        </tr>
        		</table>

			<table>
				<tr>
					<td colspan="2" class="center">
						<input type="submit" value="Search requests" />
					</td>
				</tr>
			</table>	
		</form>
	</div>
	
	<div class="linkbox">
		<?=$PageLinks?>
	</div>
	<table id="request_table" cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
		<tr class="colhead">
			<td style="width: 38%;" class="nobr">
				Request Name
			</td>
			<td class="nobr">
				<a href="?order=votes&amp;sort=<?=(($CurrentOrder == 'votes') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Votes</a>
			</td>
			<td class="nobr">
				<a href="?order=bounty&amp;sort=<?=(($CurrentOrder == 'bounty') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Bounty</a>
			</td>
			<td class="nobr">
				<a href="?order=filled&amp;sort=<?=(($CurrentOrder == 'filled') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Filled</a>
			</td>
			<td class="nobr">
				Filled by
			</td>
			<td class="nobr">
				Requested by
			</td>
			<td class="nobr">
				<a href="?order=created&amp;sort=<?=(($CurrentOrder == 'created') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Created</a>
			</td>
			<td class="nobr">
				<a href="?order=lastvote&amp;sort=<?=(($CurrentOrder == 'lastvote') ? $NewSort : 'desc')?>&amp;<?=$CurrentURL ?>">Last Vote</a>
			</td>
		</tr>
<?	if($NumResults == 0) { ?>
		<tr class="rowb">
			<td colspan="8">
				Nothing found!
			</td>
		</tr>
<?	} else {
		$Row = 'a';
		$TimeCompare = 1267643718; // Requests v2 was implemented 2010-03-03 20:15:18
		foreach ($Requests as $RequestID => $Request) {
			
			list($RequestID, $RequestorID, $RequestorName, $TimeAdded, $LastVote, $CategoryID, $Title, $Image, $Description, 
                             $FillerID, $FillerName, $TorrentID, $TimeFilled) = $Request;
				
			$RequestVotes = get_votes_array($RequestID);
			
			$VoteCount = count($RequestVotes['Voters']);
			
			if($CategoryID == 0) {
				$CategoryName = "Unknown";
			} else {
				$CategoryName = $NewCategories[$CategoryID]['name'];
			}
			
			$IsFilled = ($TorrentID != 0);
			
                        $FullName ="<a href='requests.php?action=view&amp;id=".$RequestID."'>".$Title."</a>";
			
			$Row = ($Row == 'a') ? 'b' : 'a';
			
			$Tags = $Request['Tags'];
?>
		<tr class="row<?=$Row?>">
			<td>
				<?=$FullName?>
                                <? if ($LoggedUser['HideTagsInLists'] !== 1) { ?>
				<div class="tags">
<?			
			$TagList = array();
			foreach($Tags as $TagID => $TagName) {
				$TagList[] = "<a href='?tags=".$TagName.($BookmarkView ? "&amp;type=requests" : "")."'>".display_str($TagName)."</a>";
			}
			$TagList = implode(' ', $TagList);
?>
					<?=$TagList?>
				</div>
                                <? } ?>
			</td>
			<td class="nobr">
				<form id="form_<?=$RequestID?>">
					<span id="vote_count_<?=$RequestID?>"><?=$VoteCount?></span>
<?  	 	if(!$IsFilled && check_perms('site_vote')){ ?>
					<input type="hidden" id="requestid_<?=$RequestID?>" name="requestid" value="<?=$RequestID?>" />
					<input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
					&nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)"><strong>(+)</strong></a>
				</form>
<?  		} ?> 
			</td>
			<td class="nobr">
				<?=get_size($RequestVotes['TotalBounty'])?>
			</td>
			<td>
<?   		if($IsFilled){ ?>
				<a href="torrents.php?<?=(strtotime($TimeFilled)<$TimeCompare?'id=':'torrentid=').$TorrentID?>"><strong><?=time_diff($TimeFilled)?></strong></a>
<?   		} else { ?>
				<strong>No</strong>
<?   		} ?>
			</td>
			<td>
<?			if($IsFilled){ ?>
			<a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a>
<?			} else { ?>
			--
<?			} ?>
			</td>
			<td>
				<a href="user.php?id=<?=$RequestorID?>"><?=$RequestorName?></a>
			</td>
			<td>
				<?=time_diff($TimeAdded)?>
			</td>
			<td>
				<?=time_diff($LastVote)?>
			</td>
		</tr>
<?
		} // while
	} // else
?>
	</table>
	<div class="linkbox">
		<?=$PageLinks?>
	</div>
</div>
<?
show_footer();
?>

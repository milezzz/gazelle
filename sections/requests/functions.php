<?
enforce_login();

function get_request_tags($RequestID) {
	global $DB;
	$DB->query("SELECT rt.TagID, 
					t.Name 
				FROM requests_tags AS rt 
					JOIN tags AS t ON rt.TagID=t.ID 
				WHERE rt.RequestID = ".$RequestID."
				ORDER BY rt.TagID ASC");
	$Tags = $DB->to_array();
	$Results = array();
	foreach($Tags as $TagsRow) {
		list($TagID, $TagName) = $TagsRow;
		$Results[$TagID]= $TagName;
	}
	return $Results;
}

function get_votes_array($RequestID) {
	global $Cache, $DB;
	
	$RequestVotes = $Cache->get_value('request_votes_'.$RequestID);
	if(!is_array($RequestVotes)) {
		$DB->query("SELECT rv.UserID,
							rv.Bounty,
							u.Username
						FROM requests_votes as rv
							LEFT JOIN users_main AS u ON u.ID=rv.UserID
						WHERE rv.RequestID = ".$RequestID."
						ORDER BY rv.Bounty DESC");
		if($DB->record_count() < 1) {
			error(0);
		} else {
			$Votes = $DB->to_array();
			
			$RequestVotes = array();
			$RequestVotes['TotalBounty'] = array_sum($DB->collect('Bounty'));
			
			foreach($Votes as $Vote) {
				list($UserID, $Bounty, $Username) = $Vote;
				$VoteArray = array();
				$VotesArray[] = array('UserID' => $UserID, 
										'Username' => $Username,
										'Bounty' => $Bounty);
			}
	
			$RequestVotes['Voters'] = $VotesArray;
			$Cache->cache_value('request_votes_'.$RequestID, $RequestVotes);
		}
	}
	return $RequestVotes;
}
?>

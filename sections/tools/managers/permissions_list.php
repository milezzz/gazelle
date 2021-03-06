<?
show_header('Manage Permissions');
?>
<script type="text/javascript" language="javascript">
//<![CDATA[
function confirmDelete(id) {
	if (confirm("Are you sure you want to remove this permission class?")) {
		location.href="tools.php?action=permissions&removeid="+id;
	}
	return false;
}
//]]>
</script>
<div class="thin">
      <h2>User Classes</h2>
	<div class="linkbox">
		[<a href="tools.php?action=permissions&amp;id=new&amp;isclass=1">Create a new User Class<!--permission set--></a>]
		[<a href="tools.php">Back to Tools</a>]
	</div>
<?
//$DB->query("SELECT p.ID,p.Name,p.Level,COUNT(u.ID) FROM permissions AS p LEFT JOIN users_main AS u ON u.PermissionID=p.ID GROUP BY p.ID ORDER BY p.Level ASC");
$DB->query("SELECT p.ID,p.Name,p.Level,p.DisplayStaff,p.MaxSigLength,p.MaxAvatarWidth,
                   p.MaxAvatarHeight,COUNT(u.ID) 
                   FROM permissions AS p LEFT JOIN users_main AS u ON u.PermissionID=p.ID 
                   WHERE p.IsUserClass='1'
                   GROUP BY p.ID 
                   ORDER BY p.IsUserClass DESC, p.Level ASC");
if($DB->record_count()) { 
?>
	<table>
		<tr class="colhead">
			<td width="18%">Name</td>
			<td width="10%">Level</td>
			<td width="14%">Max Sig Length</td>
			<td width="14%">Max Avatar Size</td>
			<td width="14%">Display as Staff</td>
			<td width="10%">User Count</td>
			<td width="20%" class="center">Actions</td>
		</tr>
<?	while(list($ID,$Name,$Level,$DisplayStaff,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight,$UserCount)=$DB->next_record()) { 
             
?>
		<tr>
			<td><?=display_str($Name); ?></td>
			<td><?=$Level; ?></td>
			<td><?=$MaxSigLength; ?></td>
			<td><?=($MaxAvatarWidth.' x '.$MaxAvatarHeight); ?></td>
			<td><?=$DisplayStaff=='1'?'<strong>True</strong>':'False'; ?></td>
			<td><?=number_format($UserCount); ?></td>
			<td class="center">[<a href="tools.php?action=permissions&amp;id=<?=$ID ?>">Edit</a> | <a href="#" onclick="return confirmDelete(<?=$ID?>)">Remove</a>]</td>
		</tr>
<?	} ?>
	</table> 
<? } else { ?>
	<h3 align="center">There are no permission classes.</h3>
<? } ?>
      
      <br/>
      <h2>Group Permissions</h2>
	<div class="linkbox">
		[<a href="tools.php?action=permissions&amp;id=new&amp;isclass=0">Create a new Group Permissions</a>]
		[<a href="tools.php">Back to Tools</a>]
	</div>
      
<?
//$DB->query("SELECT p.ID,p.Name,p.Level,COUNT(u.ID) FROM permissions AS p LEFT JOIN users_main AS u ON u.PermissionID=p.ID GROUP BY p.ID ORDER BY p.Level ASC");
$DB->query("SELECT p.ID,p.Name,p.IsUserClass,COUNT(u.ID) 
                   FROM permissions AS p LEFT JOIN users_main AS u ON u.GroupPermissionID=p.ID 
                   WHERE p.IsUserClass='0'
                   GROUP BY p.ID 
                   ORDER BY p.IsUserClass DESC, p.Level ASC");
if($DB->record_count()) { 
?>
	<table style="width:50%;margin:0px auto;">
		<tr class="colhead">
			<td width="18%">Name</td>
			<td width="10%">User Count</td>
			<td width="20%" class="center">Actions</td>
		</tr>
<?	while(list($ID,$Name,$IsUserClass,$UserCount)=$DB->next_record()) { 
             
?>
		<tr>
			<td><?=display_str($Name); ?></td>
			<td><?=number_format($UserCount); ?></td>
			<td class="center">[<a href="tools.php?action=permissions&amp;id=<?=$ID ?>">Edit</a> | <a href="#" onclick="return confirmDelete(<?=$ID?>)">Remove</a>]</td>
		</tr>
<?	} ?>
	</table> 
<? } else { ?>
	<h3 align="center">There are no group permissions.</h3>
<? } ?>
</div>
<?
show_footer();
?>

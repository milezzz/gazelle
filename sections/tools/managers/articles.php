<?
enforce_login();
if(!check_perms('admin_manage_articles')){ error(403); }

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

switch($_REQUEST['action']) {
	case 'takeeditarticle':
		if(!check_perms('admin_manage_articles')){ error(403); }
		if(is_number($_POST['articleid'])){
			authorize();

                        $DB->query("SELECT Count(*) as c FROM articles WHERE TopicID='".db_string($_POST['topicid'])."' AND ID<>'".db_string($_POST['articleid'])."'");
                        list($Count) = $DB->next_record();
                        if ($Count > 0) {
                            error('The topic ID must be unique for the article');
                        }
                        
                        list($TopicID) = $DB->next_record();
			$DB->query("UPDATE articles SET Category='".(int)$_POST['category']."', 
                                                    SubCat='".(int)$_POST['subcat']."', 
                                                   TopicID='".db_string(strtolower($_POST['topicid']))."', 
                                                     Title='".db_string($_POST['title'])."', 
                                               Description='".db_string($_POST['description'])."', 
                                                      Body='".db_string($_POST['body'])."', 
                                                      Time='".sqltime()."' 
                                            WHERE ID='".db_string($_POST['articleid'])."'");

		}
		header('Location: tools.php?action=articles');
		break;
	case 'editarticle':
            $ArticleID = db_string($_REQUEST['id']);

                    $DB->query("SELECT ID, Category, SubCat, TopicID, Title, Description, Body FROM articles WHERE ID='$ArticleID'");
                    list($ArticleID, $Category, $SubCat, $TopicID, $Title, $Description, $Body) = $DB->next_record();
                break;
}

show_header('Manage articles','bbcode');

?>
<div class="thin">
    <h2><?= ($_GET['action'] == 'articles')? 'Create a new article' : 'Edit an article';?></h2> 
    <div id="quickreplypreview">
        <div id="contentpreview" style="text-align:left;"></div>
    </div>
    <form  id="quickpostform" action="tools.php" method="post">
        <div class="head"><?=($_GET['action'] == 'articles'?"New Article":"Edit Article: $Title")?></div>
        <div class="box pad">
            <div id="quickreplytext">
			<input type="hidden" name="action" value="<?= ($_GET['action'] == 'articles')? 'takearticle' : 'takeeditarticle';?>" />
			<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<? if($_GET['action'] == 'editarticle'){?> 
			<input type="hidden" name="articleid" value="<?=$ArticleID; ?>" />
<? }?> 
                  <div style="display:inline-block;margin-right:40px;vertical-align: top;">
                        <h3>Topic ID</h3>
                        <input type="text" name="topicid" <? if(!empty($TopicID)) { echo 'value="'.display_str($TopicID).'"'; } ?> />
                  </div>
                  <div style="display:inline-block;margin-right:40px;vertical-align: top;">
                        <h3>Category</h3>
                        <select name="category">
<? foreach($ArticleCats as $Key => $Value) { ?> 
                            <option value="<?=display_str($Key)?>"<?=($Category == $Key) ? 'selected="selected"' : '';?>><?=$Value?></option>
<? } ?>
                        </select>
                  </div>
                  <div style="display:inline-block;margin-right:40px;vertical-align: top;">
                        <h3>Sub-Category</h3>
                        <select name="subcat">
<? foreach($ArticleSubCats as $Key => $Value) { ?> 
                            <option value="<?=display_str($Key)?>"<?=($SubCat == $Key) ? 'selected="selected"' : '';?>><?=$Value?></option>
<? } ?>
                        </select>
                  </div>
                  <div style="display:inline-block;">
                      <ul>
                          <li><strong>Rules/Tutorials</strong> appears in the rules/help section</li>
                          <li><strong>Hidden</strong> used for content on other site pages<br/>(don't delete hidden content without being sure the topic is not needed)</li>
                      </ul>
                  </div>
			<h3>Title</h3>
			<input type="text" name="title" size="95" <? if(!empty($Title)) { echo 'value="'.display_str($Title).'"'; } ?> />
                        <h3>Description</h3>
			<input type="text" name="description" size="100" <? if(!empty($Description)) { echo 'value="'.display_str($Description).'"'; } ?> />
			<br />
			<h3>Body</h3>
                  &nbsp; special article tags allowed: [whitelist] &nbsp; [clientlist] &nbsp; [ratiolist] &nbsp; [dnulist]
                  <? $Text->display_bbcode_assistant('textbody', get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])) ?>
                  <textarea id="textbody" name="body" class="long" rows="15"><? if(!empty($Body)) { echo display_str($Body); } ?></textarea> 
            </div>
            <br />
           <div class="center">
			<input id="post_preview" type="button" value="Preview" onclick="if(this.preview){Edit_Article();}else{Preview_Article();}" />
                  <input type="submit" value="<?= ($_GET['action'] == 'articles')? 'Create new article' : 'Save changes';?>" />
            </div> 
        </div>
    </form>
    <br /><br />
    <h2>Other articles</h2>
        
<?
    $OldCategory = -1;
    $LastSubCat = -1;
    $OpenTable=false;
    $DB->query("SELECT ID, Category, SubCat, TopicID, Title, Body, Time, Description 
                  FROM articles 
              ORDER BY Category, SubCat, Title");// LIMIT 20
    while(list($ArticleID,$Category,$SubCat,$TopicID, $Title,$Body,$ArticleTime,$Description)=$DB->next_record()) {
        $Row = ($Row == 'a') ? 'b' : 'a';
  
        if($LastSubCat != $SubCat) {  
                $Row = 'b';
                $LastSubCat = $SubCat;

                if($OpenTable){  ?>
            </table><br/>
<?              }
         
            if($OldCategory != $Category ) { ?>
                <br/> 
                <h3 id="general"><?=$ArticleCats[$Category]?></h3>
<?
                $OldCategory = $Category; 
            }  ?>
            
        <div class="head"><?=($SubCat==1?"Other $ArticleCats[$Category] articles":$ArticleSubCats[$SubCat])?></div>
        <table width="100%" class="topic_list">
            <tr class="colhead">
                    <td style="width:300px;">Title</td>
                    <td>Additional Info</td>
            </tr>
<? 
            $OpenTable=true;
        }
?> 
            <tr class="row<?=$Row?>"> 
                <td class="nobr topic_link">
                    <span style="float:left">
                        <a href="articles.php?topic=<?=$TopicID?>" target="_blank" title="goto article"><?=display_str($Title)?></a>
            <!--  <a href="tools.php?action=editarticle&amp;id=<?=$ArticleID?>" title="Edit this article"><?=display_str($Title)?></a> -->
                    </span>
                    <span style="float:right" class="small">posted <?=time_diff($ArticleTime)?></span>
                </td>
                <td class="nobr">
                    <span style="float:left"><?=display_str($Description)?></span>
                    <span style="float:right">
                        <a href="tools.php?action=editarticle&amp;id=<?=$ArticleID?>">[Edit]</a>
                        <a href="tools.php?action=deletearticle&amp;id=<?=$ArticleID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onClick="return confirm('Are you sure you want to delete this article?');">[Delete]</a>
                    </span>
                </td>
            </tr>
<?  } ?>
        </table>
            
</div>
<? show_footer();?>

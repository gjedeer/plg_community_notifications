<?php

defined('_JEXEC') or die('Restricted access');
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
		$groups = CFactory::getModel('Groups');
		$members = $groups->getMembersId($album->groupid);

		$db = JFactory::getDBO();
		$date =& JFactory::getDate();
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
}
?>

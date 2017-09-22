<?php

/* GCForums
 * 
 * @author Christine Yu <internalfire5@live.com>
 * 
 */

elgg_register_event_handler('init','system','gcforums_init');

define("TOTAL_POST", 1);
define("TOTAL_TOPICS", 2);
define("RECENT_POST", 3);

function gcforums_init() {
	elgg_register_library('elgg:gcforums:functions', elgg_get_plugins_path() . 'gcforums/lib/functions.php');

	$action_path = elgg_get_plugins_path().'gcforums/actions/gcforums';

	elgg_register_css('gcforums-css','mod/gcforums/css/gcforums-table.css');						// styling the forums table
	elgg_register_plugin_hook_handler('register','menu:owner_block','gcforums_owner_block_menu');	// register menu item in group
	elgg_register_page_handler('gcforums', 'gcforums_page_handler');								// page handler for forums
	add_group_tool_option('forums', elgg_echo('gcforums:enable_group_forums'), false);				// add option for user to enable

	// actions for forum creation/editing/deletion (.../action/gcforums/[action]/...)
	elgg_register_action('gcforums/edit', $action_path.'/edit.php');
	elgg_register_action('gcforums/delete', $action_path.'/delete.php');
	elgg_register_action('gcforums/create', $action_path.'/create.php');
	elgg_register_action('gcforums/subscribe', $action_path.'/subscribe.php');

	// put a menu item in the site navigation (JMP request), placed in career dropdown
	elgg_register_menu_item('subSite', array(
		'name' => 'Forum',
		'text' => elgg_echo('gcforums:jmp_menu'),
		'href' => elgg_echo('gcforums:jmp_url'),
	));

	elgg_register_admin_menu_item("administer", "debugging_forums", "administer_utilities");
}


function gcforums_owner_block_menu($hook,$type,$return,$params) {
	$entity = elgg_extract('entity', $params);
	if ($entity->type === 'group' && $entity->forums_enable === 'yes') { // display only in group menu and only when user selected to enable forums in group
		$url = "gcforums/group/{$params['entity']->guid}";
		$item = new ElggMenuItem('gcforums',elgg_echo('gcforums:group_nav_label'),$url);
		$return[] = $item;
		return $return;
	}
}


/* Page Handler
 */
function gcforums_page_handler($page) {
	$vars = array();

	switch($page[0]) {
		case 'create':
			gatekeeper();
			$params = render_create_forms($page[2], $page[1]);
			break;

		case 'edit':
			gatekeeper();
			$params = render_edit_forms($page[1]);
			break;

		case 'topic':
			$params = render_forum_topics($page[2]);
			break;

		case 'view':
			$params = render_forums($page[1]);
			break;

		case 'group':
			$params = render_forums($page[1]);
			break;

		default:
			return false;
	}

	$body = elgg_view_layout('forum-content', $params);
	echo elgg_view_page($params['title'], $body);

}


function render_create_forms($entity_guid, $entity_type) {

	// this is the current form (new forum will be placed in this forum)
	$entity = get_entity($entity_guid);
	assemble_forum_breadcrumb($entity);
	$group_guid = gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID());
	elgg_set_page_owner_guid();
	$group_entity = get_entity($group_guid);
	$vars['current_entity'] = $entity_guid;
	$vars['entity_type'] = $entity_type;
	$vars['group_guid'] = $group_guid;

	$content = elgg_view_form('gcforums/create', array(), $vars);
	$title = elgg_echo('gcforums:edit:new_forum:heading', array(elgg_echo("gcforums:translate:{$entity_type}")));

	$return['filter'] = '';
	$return['title'] = $title;
	$return['content'] = $content;
	return $return;

}

function render_edit_forms($entity_guid, $entity_type = '') {

	$entity = get_entity($entity_guid);
	assemble_forum_breadcrumb($entity);
	elgg_set_page_owner_guid(gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID()));
	$vars['entity_guid'] = $entity_guid;

	$content = elgg_view_form('gcforums/edit', array(), $vars);
	$title = elgg_echo('gcforums:edit:edit_forum:heading', array(elgg_echo("gcforums:translate:{$entity->getSubtype()}")));
	
	$return['filter'] = '';
	$return['title'] = $title;
	$return['content'] = $content;
	return $return;

}

/// recursively go through the forums and return group entity
function gcforums_get_forum_in_group($entity_guid_static, $entity_guid) {
	$entity = get_entity($entity_guid);
	// (base) stop recursing when we reach group guid
	if ($entity instanceof ElggGroup)  
		return $entity_guid;
	else 
		return gcforums_get_forum_in_group($entity_guid_static, $entity->getContainerGUID());
}


function render_forum_topics($topic_guid) {
	elgg_load_css('gcforums-css');
	$entity = get_entity($topic_guid);
	$dbprefix = elgg_get_config('dbprefix');
	$base_url = elgg_get_site_entity()->getURL();
	$group_guid = gcforums_get_forum_in_group($topic_guid, $topic_guid);

	// set the breadcrumb trail
	assemble_forum_breadcrumb($entity);

	if ($entity->getSubtype() === 'hjforumtopic') {

		$options = render_edit_options($entity->getGUID(), $entity->getGUID());
		$topic = get_entity($topic_guid);
		$title = $topic->title;
		$description = $topic->description;

		/// owner information
		$owner = $topic->getOwnerEntity();
		$timestamp = date('Y-m-d H:i:s', $topic->time_created);
		$params = array(
			'entity' => $topic,
			'title' => false,
		);
		$params = $params;
		$summary = elgg_view('object/elements/summary', $params);

		$owner_icon = elgg_view_entity_icon($topic->getOwnerEntity(), 'medium');

		$content .= "
		<div class='topic-owner-information-content'>
			<div class='topic-information-options'>{$options} (guid: {$topic->getGUID()})</div>
			<div class='topic-owner-icon'>{$owner_icon}</div>
			<div class='topic-owner-information'><b>Name (username):</b> {$owner->name} ({$owner->username})</div>
			<div class='topic-owner-information'><b>Email:</b> {$owner->email}</div>
			<div class='topic-owner-information'><b>Posting:</b> {$timestamp}</div>
		</div>";

		$content .= "<div class='topic-content'>{$topic->description}</div>";
		$content .= "<h3>Comments</h3>";
		$comments = elgg_get_entities(array(
			'types' => 'object',
			'container_guids' => $topic->guid,
			'limit' => 0,
		));

		/// comments
		$content .= "<div class='topic-main-comments'>";
		foreach ($comments as $comment) {
			$options = render_edit_options($comment->getGUID(), $comment->getGUID());
			$owner_icon = elgg_view_entity_icon($topic->getOwnerEntity(), 'small');
			$content .= "
			<div class='topic-comments'>
				<div class='topic-comment-options'>{$options} (guid: {$comment->getGUID()})</div>
				<div class='comment-owner-information-content'>
					<div class='comment-owner-icon'>{$owner_icon} {$comment->getOwnerEntity()->email}</div>
				</div>
				<div class='topic-comment-content'>{$comment->description}</div>
			</div> <br/>";
		}
		$content .= "</div>";

		$vars['group_guid'] = $group_guid;
		$vars['topic_guid'] = $topic->guid;
		$vars['current_entity'] = $topic->guid;
		$vars['topic_access'] = $topic->access_id;
		$vars['entity_type'] = 'hjforumpost';
		$topic_content .= elgg_view_form('gcforums/create', array(), $vars);
		$content .= $topic_content;

		$return['filter'] = '';
		$return['title'] = $title;
		$return['content'] = $content;
		return $return;
	}

}



function assemble_forum_breadcrumb($entity) {
	$forum_guid = $entity->guid;
	if ($entity instanceof ElggGroup) {
		elgg_set_page_owner_guid($entity->getGUID());
		elgg_push_breadcrumb($entity->name, $entity->getURL());
		elgg_push_breadcrumb('Group Forums');

	} else {
		elgg_set_page_owner_guid(gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID()));
		$breadcrumb_array = array();
		$breadcrumb_array = assemble_nested_forums(array(), $forum_guid, $forum_guid);
		$breadcrumb_array = array_reverse($breadcrumb_array);

		foreach ($breadcrumb_array as $trail_id => $trail) {
			elgg_push_breadcrumb($trail[1], $trail[2]);
		}
	}
}

/**
 * Create list of options to modify forums
 *
 * @param int $forum_guid
 * 
 */
function render_forums($forum_guid) {

	elgg_load_css('gcforums-css');
	$entity = get_entity($forum_guid);
	$dbprefix = elgg_get_config('dbprefix');
	$base_url = elgg_get_site_entity()->getURL();

	$group_entity = get_entity(gcforums_get_forum_in_group($entity->getGUID(), $entity->getGUID()));
	// set the breadcrumb trail
	assemble_forum_breadcrumb($entity);

	// forums will always remain as content within a group
	elgg_set_page_owner_guid($group_entity->getGUID());
	$return = array();


	$query = "SELECT * FROM elggentities e, elggentity_subtypes es WHERE e.subtype = es.id AND e.container_guid = {$forum_guid} AND es.subtype = 'hjforumtopic'";
	$topics = get_data($query);

	$content .= "<div class='forums-menu-buttons'>".gcforums_menu_buttons($entity->getGUID(), $group_entity->getGUID())."</div> ";

	if (count($topics) > 0) {
		$content .= "
			<div class='topic-main-box'>
				<div style='background: #e6e6e6; width:100%;' >
					<div class='topic-header'>Topic
						<div class='topic-information'>options</div>
						<div class='topic-information'>".elgg_echo('gcforums:translate:last_posted')."</div>
						<div class='topic-information'>total replies</div>
						<div class='topic-information'>topic starter</div>
					</div>";


		/// topic
		foreach ($topics as $topic) {
			$topic = get_entity($topic->guid);
			$hyperlink = "<a href='{$base_url}gcforums/topic/view/{$topic->guid}'><strong>{$topic->title}</strong></a>";

			$query = "SELECT e.guid, ue.username, e.time_created
						FROM {$dbprefix}entities e, {$dbprefix}users_entity ue
						WHERE e.container_guid = {$topic->guid} AND e.owner_guid = ue.guid";
			$replies = get_data($query);


			$total_replies = count($replies);
			$topic_starter = get_user($topic->owner_guid)->username;
			$time_posted = $replies[count($total_replies) - 1]->time_created;
			$time_posted = date('Y-m-d H:i:s', $time_posted);

			$options = render_edit_options($topic->guid, $topic->guid);

			$last_post = ($total_replies <= 0) ? $last_post = elgg_echo('gcforums:no_posts') : "<div>{$replies[$total_replies - 1]->username}</div> <div>{$time_posted}</div>";

					$content .= "
					<div class='topic-info-header'>
						<div class='topic-description'>{$hyperlink} (guid:{$topic->guid})</div>
						<div class='topic-options-edit'>{$options}</div>
						<div class='topic-options'>{$last_post}</div>
						<div class='topic-options'>{$total_replies}</div>
						<div class='topic-options'>{$topic_starter}</div>
					</div>";
		}

		$content .= "</div> </div> </p> <br/>";
	}



	/// display the categories if the forum has this enabled
	if ($entity->enable_subcategories || $entity instanceof ElggGroup) {
		
		$categories = elgg_get_entities(array(
			'types' => 'object',
			'subtypes' => 'hjforumcategory',
			'limit' => false,
			'container_guid' => $forum_guid
		));

		/// category
		foreach ($categories as $category) {
			$options = render_edit_options($category->guid, 334);
			$content .= "
			<p>
				<div class='category-main-box'>
					<div class='category-options'>{$options} (guid:{$category->getGUID()})</div>
					<h1>{$category->title}</h1>
					<div class='category-description'>{$category->description}</div>
				</div>";


			$forums = elgg_get_entities_from_relationship(array(
				'relationship' => 'filed_in',
				'relationship_guid' => $category->getGUID(),
				'container_guid' => $entity->getGUID(),
				'inverse_relationship' => true,
				'limit' => false
			));
		
			if (sizeof($forums) > 0) {

				$content .= "<div class='forum-main-box'>
								<div style='background: #e6e6e6; width:100%;' >
									<div class='forum-header'>Forum
										<div class='forum-information'>options</div>
										<div class='forum-information'>total topics</div>
										<div class='forum-information'>total posts</div>
										<div class='forum-information'>recently posted</div>
									</div>";

				/// forums
				foreach ($forums as $forum) {
					$total_topics = get_forums_statistics_information($forum->guid, TOTAL_TOPICS);
					$total_posts = get_forums_statistics_information($forum->guid, TOTAL_POST);
					$recent_post = get_forums_statistics_information($forum->guid, RECENT_POST);
					$options = render_edit_options($forum->getGUID(), $forum->getGUID());

					$hyperlink = "<a href='{$base_url}gcforums/view/{$forum->getGUID()}'><strong>{$forum->title}</strong></a>";

					$content .= "<div class='forum-info-header'>
									<div class='forum-description'>{$hyperlink} (guid:{$forum->guid})
										<div class='forum-description-text'>{$forum->description}</div>
									</div>
									<div class='forum-options-edit'>{$options}</div>
									<div class='forum-options'>{$total_topics}</div>
									<div class='forum-options'>{$total_posts}</div>
									<div class='forum-options'>{$recent_post}</div>
								</div>";
				}
				$content .= "</div> </div> </p> <br/>";

			} else {
				$content .= "<div class='forum-empty'>No Forums created</div>";
			}
		}

	} else {

		/// display forums with no categories
		$forums = elgg_get_entities_from_relationship(array(
				'relationship' => 'descendant',
				'subtypes' => array('hjforum'),
				'relationship_guid' => $forum_guid,
				'inverse_relationship' => true,
				'types' => 'object',
				'limit' => 0,
			));

		if (sizeof($forums) > 0) {
				
			$content .= "
				<div class='forum-main-box'>
					<div style='background: #e6e6e6; width:100%;' >
						<div class='forum-header'>Forum
							<div class='forum-information'>options</div>
							<div class='forum-information'>total topics</div>
							<div class='forum-information'>total posts</div>
							<div class='forum-information'>recently posted</div>
						</div>";

			foreach ($forums as $forum) {
					$total_topics = get_forums_statistics_information($forum->guid, TOTAL_TOPICS);
					$total_posts = get_forums_statistics_information($forum->guid, TOTAL_POST);
					$recent_post = get_forums_statistics_information($forum->guid, RECENT_POST);
					$options = render_edit_options($forum->getGUID(), $forum->getGUID());

					$hyperlink = "<a href='{$base_url}gcforums/view/{$forum->getGUID()}'><strong>{$forum->title}</strong></a>";

					$content .= "
						<div class='forum-info-header'>
							<div class='forum-description'>{$hyperlink} (guid:{$forum->guid})
								<div class='forum-description-text'>{$forum->description}</div>
							</div>
							<div class='forum-options-edit'>{$options}</div>
							<div class='forum-options'>{$total_topics}</div>
							<div class='forum-options'>{$total_posts}</div>
							<div class='forum-options'>{$recent_post}</div>
						</div>";
			}

			$content .= "</div> </div> </p> <br/>";
		}
	}


	if (!$title) $title = $entity->title;

	$title_heading = $title;
	$return['filter'] = '';
	$return['title'] = $title_heading;
	$return['content'] = $content;
	return $return;
}


/**
 * TOTAL_POST : 1
 * TOTAL_TOPICS : 2
 * RECENT_POST : 3
 */
function get_forums_statistics_information($container_guid, $type) {
	$dbprefix = elgg_get_config('dbprefix');
	
	switch($type) {
		case 1:
			$query = "SELECT COUNT(r.guid_one) AS total
				FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es
				WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumpost' AND (e.access_id = 1 OR e.access_id = 2)";
			break;

		case 2:
			$query = "SELECT COUNT(r.guid_one) AS total
				FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es
				WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumtopic' AND (e.access_id = 1 OR e.access_id = 2)";
				break;
		
		case 3:
			$query = "SELECT r.guid_one, r.relationship, r.guid_two, e.subtype, es.subtype, max(e.time_created) AS time_created, ue.email, ue.username, ue.name
				FROM {$dbprefix}entity_relationships r, {$dbprefix}entities e, {$dbprefix}entity_subtypes es, {$dbprefix}users_entity ue
				WHERE r.guid_one = e.guid AND e.subtype = es.id AND r.guid_two = {$container_guid} AND es.subtype = 'hjforumtopic' AND ue.guid = e.owner_guid LIMIT 1";

			$post = get_data($query);
			$recent_poster = elgg_echo("gcforums:no_posts");
			if ($post[0]->email) {
				$timestamp = date('Y-m-d',$post[0]->time_created);
				// Output display name - nick
				$recent_poster = "<div>{$post[0]->username}</div> <div>{$timestamp}</div>"; 
			}
			return $recent_poster;
			break; // will never reach this break statement
		
		default:
	}

	$total = get_data($query);
	$total = $total[0]->total;

	return $total;
}


/// recursively go through the nested forums to create the breadcrumb
function assemble_nested_forums($breadcrumb, $forum_guid, $recurse_forum_guid) {
	$entity = get_entity($recurse_forum_guid);
	if ($entity instanceof ElggGroup && $entity->guid != $forum_guid) {
		$breadcrumb[$entity->getGUID()] = array($entity->guid, $entity->name, "profile/{$entity->guid}");
		return $breadcrumb;

	} else {
		$breadcrumb[$entity->guid] = array($entity->guid, $entity->title, "gcforums/view/{$entity->guid}");	
		return assemble_nested_forums($breadcrumb, $forum_guid, $entity->getContainerGUID());
	}
}





/**
 * Create list of options to modify forums
 *
 * @param int $object_guid
 * @param int $group_guid
 * 
 */
function render_edit_options($object_guid, $group_guid) {

	gatekeeper();
error_log(">>>>>>   {$group_guid}");
	$options = array();
	$group_entity = get_entity($group_guid);
	$entity = $group_entity;
	$current_user = elgg_get_logged_in_user_entity();
	$user = $current_user;
	$entity = get_entity($object_guid);
	$entity_type = $entity->getSubtype();

	if ($entity->getSubtype() !== 'hjforumpost')
		$options['access'] = '<strong>' . get_readable_access_level($entity->access_id) . '</strong>';
 
 	// subscription
	if (elgg_is_active_plugin('cp_notifications')) {
		$email_subscription = check_entity_relationship($current_user->getGUID(), 'cp_subscribed_to_email', $entity->getGUID());
		$site_subscription = check_entity_relationship($current_user->getGUID(), 'cp_subscribed_to_site_mail', $entity->getGUID());
		$btnSubscribe = ($email_subscription || $site_subscription) ? elgg_echo('gcforums:translate:unsubscribe') : elgg_echo('gcforums:translate:subscribe');

	} else {
		$subscription = check_entity_relationship($user->guid, 'subscribed', $object_guid);
		$btnSubscribe = ($subscription) ? elgg_echo('gcforums:translate:unsubscribe') : elgg_echo('gcforums:translate:subscribe');
	}

	if ($entity->getSubtype() !== 'hjforumcategory') {
	 	$url = elgg_add_action_tokens_to_url(elgg_get_site_url()."action/gcforums/subscribe?guid={$entity->getGUID()}");
	 	$options['subscription'] = "<div class='edit-options-{$entity_type}'><a href='{$url}'>{$btnSubscribe}</a></div>";
	}

	// checks if user is admin, group owner, or moderator
	if (elgg_is_admin_logged_in() || $entity->getOwnerGUID() == $current_user->guid /*|| check_entity_relationship($current_user->getGUID(), 'operator', $group_entity->getGUID())*/) {

		/// todo: use icons
		$object_menu_items = ($entity->getSubtype() === 'hjforum') ? array("new_subforum", "new_topic", "edit") : array('edit', 'delete');
		
		foreach ($object_menu_items as $menu_item) {
			// check if new posting link and it is disabled (enabled == disabled)
			if ($menu_item === 'new_topic' && $entity->enable_posting) {

			} else {
				$url = elgg_get_site_url()."gcforums/edit/{$object_guid}";
				$menu_label = elgg_echo("gcforums:translate:{$menu_item}");
				$options[$menu_item] = "<a href='{$url}'>{$menu_label}</a>";
			}
		}
	}

		
	foreach ($options as $key => $option) 
		$edit_options .= "<div class='edit-options-{$entity_type}'>{$option}</div>";


	if ($entity->getSubtype() !== 'hjforumpost' && $entity->getSubtype() !== 'hjforumtopic' && $entity->getSubtype() !== 'hjforumcategory') {
		$edit_options  .= elgg_view('alerts/delete', array('entity' => $entity));
	}

	return $edit_options;

}



function gcforums_menu_buttons($forum_guid, $group_guid, $is_topic=false) { // main page if forum_guid is not present

	elgg_load_css('gcforums-css');
	$group_entity = get_entity($group_guid);
	$entity = $group_entity;
	$current_user = elgg_get_logged_in_user_entity();
	$user = $current_user;
	$entity = get_entity($forum_guid);
	$entity_type = $entity->getSubtype();

	$current_entity_guid = get_entity($forum_guid);

	gatekeeper();
	// todo: check if it is a topic
	if (check_entity_relationship($current_user->getGUID(), 'member', $group_entity->getGUID())) {

		// check if postings is enabled and this is not the main first page of forum in group
		if (!$forum_object->enable_posting && $forum_guid) { 
			$btnNewForumTopic = elgg_view('output/url', array(
				"text" => elgg_echo('gcforums:new_hjforumtopic'), 
				"href" => "gcforums/create/hjforumtopic/{$group_guid}/{$forum_guid}", 
				'class' => 'elgg-button elgg-button-action btn btn-default'
			));
		}

		$isOperator = check_entity_relationship($current_user->getGUID(), 'operator', $group_entity->getGUID());
		// do not display the button menu if the object is a forum topic
		if (($current_user->isAdmin() || $isOperator) && !$is_topic) {

			$button_class = "elgg-button elgg-button-action btn btn-default";
			$gcforum_types = array('hjforumcategory', 'hjforumtopic', 'hjforum');

			$button_array = array();
			foreach ($gcforum_types as $gcforum_type) {
				$url = "gcforums/create/{$gcforum_type}/{$forum_guid}";
				if ($gcforum_type === 'hjforumcategory')
					$button_array[$gcforum_type] = ($entity->enable_subcategories || $forum_guid == $group_guid) ? elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforumcategory'), "href" => $url, 'class' => $button_class)) : "";
				
				if ($gcforum_type === 'hjforumtopic')
					$button_array[$gcforum_type] = (!$forum_object->enable_posting && $forum_guid) ? elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforumtopic'), "href" => $url, 'class' => $button_class)) : "";

				if ($gcforum_type === 'hjforum')
					$button_array[$gcforum_type] = elgg_view('output/url', array("text" => elgg_echo('gcforums:new_hjforum'), "href" => $url, 'class' => $button_class));
			}
			
			/// edit or delete current forum
			if ($forum_guid != $group_guid) {

				$url = "gcforums/edit/{$forum_guid}";
				$edit_forum_button = 
				$button_array['edit_forum'] = elgg_view('output/url', array("text" => elgg_echo('gcforums:edit_hjforum'), "href" => $url, 'class' => $button_class));
				$button_array['delete'] = elgg_view('alerts/delete', array('entity' => $entity, 'is_menu_buttons' => true));

			}

			foreach ($button_array as $key => $value)
				$menu_buttons .= " {$value} ";
			
			return "<div>{$menu_buttons}</div>";
		}
		return "<div class='gcforums-menu'>{$new_forum_topic_button}</div>";
	}
}



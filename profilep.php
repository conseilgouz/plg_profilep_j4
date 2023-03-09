<?php
 /**
 * @version   2.0 for Joomla 4.0
 * @author    ConseilGouz
 * @copyright 2021 ConseilGouz
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
  */

 defined('JPATH_BASE') or die;
 use Joomla\CMS\Factory;
 use Joomla\CMS\Form\Form;
 use Joomla\CMS\Language\Text;
 use Joomla\CMS\Log\Log;
 use Joomla\CMS\Plugin\CMSPlugin;
 use Joomla\Utilities\ArrayHelper;
  /**
   * An example custom profile plugin.
   *
   * @package		Joomla.Plugins
   * @subpackage	user.profile
   * @version		1.6
   */
  class plgUserProfilep extends CMSPlugin
  {
	/**
	 * @param	string	The context for the data
	 * @param	int		The user id
	 * @param	object
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile','com_users.registration','com_users.user','com_admin.profile'))){
			return true;
		}

		$userId = isset($data->id) ? $data->id : 0;

		// Load the profile data from the database.
		$db = Factory::getDbo();
		$db->setQuery(
			'SELECT profile_key, profile_value FROM #__user_profiles' .
			' WHERE user_id = '.(int) $userId .
			' AND profile_key LIKE \'profilep.%\'' .
			' ORDER BY ordering'
		);
		$results = $db->loadRowList();

		// Merge the profile data.
		$data->profilep = array();
		foreach ($results as $v) {
			$k = str_replace('profilep.', '', $v[0]);
			$data->profilep[$k] = json_decode($v[1], true);
		}

		return true;
	}

	/**
	 * @param	JForm	The form to be altered.
	 * @param	array	The associated data for the form.
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareForm($form, $data)
	{
		// Load user_profile plugin language
		$lang = Factory::getLanguage();
		$lang->load('plg_user_profilep', JPATH_ADMINISTRATOR);

		if (!($form instanceof Form)) {
			$this->app->enqueueMessage(Text::_('JERROR_NOT_A_FORM'), 'warning');
			return false;
		}
		// Check we are manipulating a valid form.
		if (!in_array($form->getName(), array('com_users.profile', 'com_users.registration','com_users.user','com_admin.profile'))) {
			return true;
		}
		if ($form->getName()=='com_users.profile')
		{
			// Add the profile fields to the form.
			Form::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);
	
			// Toggle whether the something field is required.
			if ($this->params->get('profile-require_position', 1) > 0) {
				$form->setFieldAttribute('position', 'required', $this->params->get('profile-require_position') == 2, 'profilep');
			} else {
				$form->removeField('position', 'profilep');
			}
		}

		//In this example, we treat the frontend registration and the back end user create or edit as the same. 
		elseif ($form->getName()=='com_users.registration' || $form->getName()=='com_users.user' )
		{		
			// Add the registration fields to the form.
			Form::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);
			
			// Toggle whether the function field is required.
			if ($this->params->get('register-require_position', 1) > 0) {
				$form->setFieldAttribute('position', 'required', $this->params->get('register-require_position') == 2, 'profilep');
			} else {
				$form->removeField('position', 'profilep');
			}
		}			
	}

	function onUserAfterSave($data, $isNew, $result, $error)
	{
		$userId	= ArrayHelper::getValue($data, 'id', 0, 'int');

		if ($userId && $result && isset($data['profilep']) && (count($data['profilep'])))
		{
			try
			{
				$db = Factory::getDbo();
				$query = $db->getQuery(true);
				$query->delete($db->quoteName('#__user_profiles'))
					  ->where($db->quoteName('user_id').' = '.$userId.' AND profile_key LIKE \'profilep.%\'');
				$db->setQuery($query);
				$result = $db->execute();
				
				$columns = array('user_id', 'profile_key', 'profile_value', 'ordering');
				$order	= 1;
				foreach ($data['profilep'] as $k => $v) {
					$tuples = array($userId.', '.$db->quote('profilep.'.$k).', '.$db->quote(json_encode($v)).', '.$order++);
					$query = $db->getQuery(true);
					$query->insert($db->quoteName('#__user_profiles'))
					->columns($db->quoteName($columns))
					->values(implode(',', $tuples));
					$db->setQuery($query);
					$db->execute();
				}
			}
			catch (\Exception $e) {
				Log::add(Text::_($e->getMessage()), Log::WARNING, 'jerror');
				return false;
			}
		}

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param	array		$user		Holds the user data
	 * @param	boolean		$success	True if user was succesfully stored in the database
	 * @param	string		$msg		Message
	 */
	function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success) {
			return false;
		}

		$userId	= ArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			try
			{
				$db = Factory::getDbo();
				$query = $db->getQuery(true)
						->delete('#__user_profiles')
						->where("user_id = ".$userId ." AND profile_key LIKE 'profilep.%'");
				$db->setQuery($query);
				$result = $db->execute();
			}
			catch (\Exception $e)
			{
				Log::add(Text::_($e->getMessage()), Log::WARNING, 'jerror');
				return false;
			}
		}

		return true;
	}


 }
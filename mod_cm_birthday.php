<?php
/**
* @version	$Id$
* @package	Joomla
* @subpackage	Module nok_cm_birthday
* @copyright	Copyright (c) 2014 Norbert Kuemin. All rights reserved.
* @license	http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE
* @author	Norbert Kuemin
* @authorEmail	momo_102@bluemail.ch
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Uri\Uri;

function getBirthdayData($fields, $where, $order) {
	$fields[0] = 'DISTINCT '.$fields[0]; //Ugly hack to eliminate duplicates
	$db = JFactory::getDBO();
	$query = $db->getQuery(true);
	$query->select($fields)
		->from($db->quoteName('#__nokCM_memberships','m'))
		->join('LEFT', $db->quoteName('#__nokCM_persons', 'p').' ON ('.$db->quoteName('m.person_id').'='.$db->quoteName('p.id').')')
		->where($where)
		->order($order);
	$db->setQuery($query);
	$data = $db->loadObjectList();
	return $data;
}

function displayBirthdays($type, $items, $cols, $colcount, $params, $cmparams, $bdtext='') {
	$name = array();
	$coloffset  = 4;
	foreach($items as $item) {
		$row = (array) $item;
		$birthday = array_pop($row);
		$birthdate = array_pop($row);
		$id = array_pop($row);
		$row = array_values($row);
		for($j=0;$j<$colcount;$j++) {
            $row[$j] = htmlspecialchars($row[$j], ENT_QUOTES, 'UTF-8');
		}
		$agetext = '';
		if ($params->get('column_next_age') == '1') {
			$age = JHTML::_('date', $birthday, 'Y') - JHTML::_('date', $birthdate, 'Y');
			$agetext = ", ".$age." ".JText::_('MOD_CM_BIRTHDAY_FIELD_UPCOMMING_AGE_YEARS');
		}
		if (!empty($bdtext)) {
			$birthday = ' <span class="cmbirth_'.$type.'_person_birthday">('.$bdtext.$agetext.')</span>';
		} else {
			switch ($params->get('column_next')) {
				case '1': //(Weekday)
					$birthday = ' <span class="cmbirth_'.$type.'_person_birthday">('.JHTML::_('date', $birthday, 'D').$agetext.')</span>';
					break;
				case '2': //(month/day)
					$birthday = ' <span class="cmbirth_'.$type.'_person_birthday">('.JHTML::_('date', $birthday, 'm/d').$agetext.')</span>';
					break;
				case '3': //(day.month)
					$birthday = ' <span class="cmbirth_'.$type.'_person_birthday">('.JHTML::_('date', $birthday, 'd.m').$agetext.')</span>';
					break;
				default:
					$birthday = '';
					break;
			}
		}
		$name[] = implode(' ',$row).$birthday;
	}
	return $name;
}
// Initialize
jimport('joomla.application.component.model');
JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_clubmanagement/models');
$memberModel = JModelLegacy::getInstance( 'Memberships', 'ClubManagementModel' );
$EOL = "\n";
$TAB = "\t";
$today = date('Y-m-d');
$cmparams = JComponentHelper::getParams('com_clubmanagement');
$days = intval($params->get('days_before'));
$title_today = $params->get('title_today');
$title_next = $params->get('title_next');
$details = false;
// Get columns
$cols = array();
for ($i=1;$i<=4;$i++) {
	$field = "column_".$i;
	if ($params->get( $field ) != "") {
		$cols[] = $params->get($field);
	}
}
$colcount = count($cols);
$cols[] = 'person_id';
$cols[] = 'person_birthday';
$cols[] = 'person_nextbirthday';
$fields = $memberModel->translateFieldsToColumns($cols,false);
// Get today records
$birthdateThisYear = 'DATE_ADD(`p`.`birthday`, INTERVAL(YEAR(NOW()) - YEAR(`p`.`birthday`)) YEAR)';
$birthdateNextYear = 'DATE_ADD(`p`.`birthday`, INTERVAL(YEAR(NOW()) - YEAR(`p`.`birthday`) + 1) YEAR)';
$next_birthday = 'IF('.$birthdateThisYear.' < CURDATE(), '.$birthdateNextYear.", ".$birthdateThisYear.')';
$calc_days = 'DATEDIFF('.$next_birthday.',\''.$today.'\')';
$order = $next_birthday.' ASC';
$whereList = array();
if ($params->get('memberstate') == 'current') {
	$whereList[] = '`m`.`end` IS NULL OR `m`.`end`=\'0000-00-00\'';
}
if ($params->get('memberstate') == 'closed') {
	$whereList[] = '`m`.`end` IS NOT NULL AND NOT `m`.`end`=\'0000-00-00\'';
}
$membertype = $params->get('membertype');
if (is_array($membertype)) {
    $whereList[] = '`m`.`type` IN (\''.implode('\', \'',$membertype).'\')';
} else {
    if (($membertype != '*') && ($membertype != '')) {
    	$whereList[] = '`m`.`type`=\''.$membertype.'\'';
    }
}
if ($params->get('publicity') == 'published') {
	$whereList[] = '`m`.`published`=1';
}
if ($params->get('publicity') == 'unpublished') {
	$whereList[] = '`m`.`published`=0';
}
$whereListToday = $whereList;
$whereListToday[] = $calc_days.' = 0';
$where = implode(' AND ',$whereListToday);
$dataToday = getBirthdayData($fields, $where, $order);
if ($days > 0) {
	$whereListNext = $whereList;
	$whereListNext[] = $calc_days.' BETWEEN 1 AND '.$days;
	$where = implode(' AND ',$whereListNext);
	$dataNext = getBirthdayData($fields, $where, $order);
} else {
	$dataNext = array();
}
// Display
if ($params->get('css') != '') {
	echo '<style type="text/css" media="screen">'.$EOL.$params->get('css').$EOL.'</style>'.$EOL;
}
echo '<div class="cmbirth">'.$EOL;
if (count($dataToday) > 0) {
	echo $TAB.'<div class="cmbirth_today">'.$EOL;
	if (!empty($title_today)) {
		echo $TAB.$TAB.'<div class="cmbirth_today_title">'.$title_today.'</div>'.$EOL;
	}
	$name = displayBirthdays('today', $dataToday, $cols, $colcount, $params, $cmparams, JText::_('MOD_CM_BIRTHDAY_TODAY'));
	echo $TAB.$TAB.'<div class="cmbirth_today_person">'.implode($params->get('delimiter'),$name).'</div>'.$EOL;
	echo $TAB.'</div>'.$EOL;
}
if (count($dataNext) > 0) {
	echo $TAB.'<div class="cmbirth_next">'.$EOL;
	if (!empty($title_next)) {
		echo $TAB.$TAB.'<div class="cmbirth_next_title">'.$title_next.'</div>'.$EOL;
	}
	$name = displayBirthdays('next', $dataNext, $cols, $colcount, $params, $cmparams);
	echo $TAB.$TAB.'<div class="cmbirth_next_person">'.implode($params->get('delimiter'),$name).'</div>'.$EOL;
	echo $TAB.'</div>'.$EOL;
}
echo '</div>'.$EOL;
?>
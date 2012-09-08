<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: class_warnings.php 5297 2010-12-28 22:01:14Z Tomm $
 */

class Warnings {
	public function get_type($tid)
	{
		global $db;
		
		$tid = intval($tid);
		if($tid <= 0)
		{
			return false;
		}
			
		$query = $db->simple_select("warningtypes", "*", "tid='".intval($tid)."'");
		$warning_type = $db->fetch_array($query);
		
		return $warning_type;
	}
	
	public function get($wid)
	{
		global $db;
		
		$wid = intval($wid);
		if($wid <= 0)
		{
			return false;
		}
			
		$query = $db->simple_select("warningtypes", "*", "wid='".intval($wid)."'");
		$warning = $db->fetch_array($query);
		
		return $warning;
	}
	
	public function create($array)
	{
		global $db;
		
		if(!is_array($array) || empty($array))
		{
			return false;
		}
		
		$id = $db->insert_query("warnings", $array);
		
		return $id;
	}
	
	public function update($array, $wid)
	{
		global $db;
		
		$wid = intval($wid);
		if($wid <= 0)
		{
			return false;
		}
		
		if(!is_array($array) || empty($array))
		{
			return false;
		}
		
		$db->update_query("warnings", $array, intval($wid));
	}

	public function check_max()
	{
		global $mybb, $db, $lang;
		
		if($mybb->usergroup['maxwarningsday'] != 0)
		{
			$timecut = TIME_NOW-60*60*24;
			$query = $db->simple_select("warnings", "COUNT(wid) AS given_today", "issuedby='{$mybb->user['uid']}' AND dateline>'$timecut'");
			$given_today = $db->fetch_field($query, "given_today");
			if($given_today >= $mybb->usergroup['maxwarningsday'])
			{
				error($lang->sprintf($lang->reached_max_warnings_day, $mybb->usergroup['maxwarningsday']));
			}
		}
	}
	
	public function find_warnlevels_to_check(&$query, &$max_expiration_times, &$check_levels)
	{
		global $db;
		
		// we have some warning levels we need to revoke
		$max_expiration_times = array(
			1 => -1,	// Ban
			2 => -1,	// Revoke posting
			3 => -1		// Moderate posting
		);
		$check_levels = array(
			1 => false,	// Ban
			2 => false,	// Revoke posting
			3 => false	// Moderate posting
		);
		while($warn_level = $db->fetch_array($query))
		{
			// revoke actions taken at this warning level
			$action = unserialize($warn_level['action']);
			if($action['type'] < 1 || $action['type'] > 3)	// prevent any freak-ish cases
			{
				continue;
			}
			
			$check_levels[$action['type']] = true;
			
			$max_exp_time = &$max_expiration_times[$action['type']];
			if($action['length'] && $max_exp_time != 0)
			{
				$expiration = $action['length'];
				if($expiration > $max_exp_time)
				{
					$max_exp_time = $expiration;
				}
			}
			else
			{
				$max_exp_time = 0;
			}
		}
	}
	
	/**
	 * Returns a friendly expiration time of a suspension/warning
	 *
	 * @param int The time period of the suspension/warning
	 * @return array An array of the time/period remaining
	 */
	public function fetch_friendly_expiration($time)
	{
		if($time == 0 || $time == -1)
		{
			return array("period" => "never");
		}
		else if($time % 2592000 == 0)
		{
			return array("time" => $time/2592000, "period" => "months");
		}
		else if($time % 604800 == 0)
		{
			return array("time" => $time/604800, "period" => "weeks");
		}
		else if($time % 86400 == 0)
		{
			return array("time" => $time/86400, "period" => "days");
		}
		else
		{
			return array("time" => ceil($time/3600), "period" => "hours");
		}
	}

	/**
	 * Figures out the length of a suspension/warning
	 *
	 * @param int The amount of time to calculate the length of suspension/warning
	 * @param string The period of time to calculate the length of suspension/warning
	 * @return int Length of the suspension/warning (in seconds)
	 */
	public function fetch_time_length($time, $period)
	{
		$time = intval($time);		

		if($period == "hours")
		{
			$time = $time*3600;
		}
		else if($period == "days")
		{
			$time = $time*86400;
		}
		else if($period == "weeks")
		{
			$time = $time*604800;
		}
		else if($period == "months")
		{
			$time = $time*2592000;
		}
		else if($period == "never" && $time == 0)
		{
			// User is permanentely banned
			$time = "-1";
		}
		else
		{
			$time = 0;
		}
		return $time;
	}
}
?>
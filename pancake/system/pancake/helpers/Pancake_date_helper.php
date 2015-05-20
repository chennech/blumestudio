<?php

function format_date($unix, $show_time = false)
{
	if ( ! is_numeric($unix))
	{
		return $unix;
	}
	if (empty($unix))
	{
		return 'n/a';
	}
	$return = date(PAN::setting('date_format'), $unix);
        
        if ($show_time) {
            $return = $return." ".format_time($unix);
        }
        
        return $return;
}

function format_seconds($seconds, $pad_hrs = false)
{
	$o = '';
	$hrs = intval(intval($seconds) / 3600);
	$o .= ($pad_hrs) ? str_pad($hrs, 2, '0', STR_PAD_LEFT) : $hrs;
	$o .= ':';
	$mns = intval(($seconds / 60) % 60);
	$o .= str_pad($mns, 2, '0', STR_PAD_LEFT);
	$o .= ':';
	$secs = intval($seconds % 60);
	$o .= str_pad($secs, 2, '0', STR_PAD_LEFT);
	return $o;
}

function read_date_picker($string)
{
    if (strlen($string) == 10) {
	# It's already a UNIX timestamp. JS timestamps are 13 digits long (3 extra digits for milliseconds).
	return $string;
    }
    return round($string / 1000);
}

/**
 * Turns dates that were POSTed back into readable dates.
 * Used when repopulating forms.
 * 
 * @param array $post
 * @return array 
 */
function reconvert_dates($post) {
    
    $return = array();
    
    foreach ($post as $key => $item) {
	if (is_array($item)) {
	    $item = reconvert_dates($item);
	} else {
	    
	    $buffer = (string) (int) $item;
	    
	    if (strlen($item) == 13 and ($item === $buffer)) {
		$item = format_date(read_date_picker($item));
	    }
	}
	
	$return[$key] = $item;
    }
    
    return $return;
}

function get_date_picker_format()
{
    $php_format = PAN::setting('date_format');
    $js_format = '';
    $parts = array();
    
    # Step 1 = Get rid of all literals in php format.
    preg_match('/\\\\./', $php_format, $parts);
    foreach ($parts as $key => $part) {
        $php_format = implode('{'.$key. '}', explode($part, $php_format, 2));
    }
    
    $length = strlen($php_format);
    $key = count($parts) - 1;
    
    $buffer = $php_format;
    $php_format = '';
    
    for ($i=0; $i<$length; $i++) {
        if (!in_array($buffer[$i], array('d', 'j', 'z', 'D', 'l', 'n', 'm', 'M', 'F', 'y', 'Y', 'U'))) {
            # It's a literal, let's take it out.
            $key = $key + 1;
            $parts[$key] = $buffer[$i];
            $php_format .= '{'.$key.'}';
        } else {
            # It's not a literal, let's leave it.
            $php_format .= $buffer[$i];
        }
    }
    
    $php_format = str_replace('d', 'dd', $php_format);
    $php_format = str_replace('j', 'd', $php_format);
    $php_format = str_replace('z', 'o', $php_format);
    $php_format = str_replace('D', 'D', $php_format);
    $php_format = str_replace('l', 'DD', $php_format);
    $php_format = str_replace('n', 'm', $php_format);
    $php_format = str_replace('m', 'mm', $php_format);
    $php_format = str_replace('M', 'M', $php_format);
    $php_format = str_replace('F', 'MM', $php_format);
    $php_format = str_replace('y', 'y', $php_format);
    $php_format = str_replace('Y', 'yy', $php_format);
    $php_format = str_replace('U', '@', $php_format);
    
    foreach ($parts as $key => $part) {
        $part = ($part == ' ') ? ' ' : "'$part'";
        $php_format = str_replace('{'.$key. '}', $part, $php_format);
    }
    
    # Run it over again, because it needs to.
    foreach ($parts as $key => $part) {
        $php_format = str_replace("'{''$key''}'", "'".str_replace('\\', '', $part)."'", $php_format);
    }
    
    return $php_format;
    
}

function format_time($unix) {
    if ($unix == '' || ! is_numeric($unix))
	{
		return $unix;
	}
        if (empty($unix) or $unix == '0') {return 'n/a';}
	$return = date(PAN::setting('time_format'), $unix);
        
        # Smart reformatting: Convert times like 10:00 PM to 10 PM 
        # (only for 12-hour clock users; a random "10" would be confusing
        # for 24-hour clock users).
        
        if (strstr(PAN::setting('time_format'), 'A') !== false) {
            $return = str_ireplace(':00', '', $return);
        }
        
        return $return;
}

function seconds_to_human($seconds = 1)
{
	$CI =& get_instance();
	$CI->lang->load('date');

	if ( ! is_numeric($seconds))
	{
		$seconds = 1;
	}

	
	$str = '';
	$years = floor($seconds / 31536000);

	if ($years > 0)
	{	
		$str .= $years.' '.$CI->lang->line((($years	> 1) ? 'date_years' : 'date_year')).', ';
	}	

	$seconds -= $years * 31536000;
	$months = floor($seconds / 2628000);

	if ($years > 0 OR $months > 0)
	{
		if ($months > 0)
		{	
			$str .= $months.' '.$CI->lang->line((($months	> 1) ? 'date_months' : 'date_month')).', ';
		}	

		$seconds -= $months * 2628000;
	}

	$weeks = floor($seconds / 604800);

	if ($years > 0 OR $months > 0 OR $weeks > 0)
	{
		if ($weeks > 0)
		{	
			$str .= $weeks.' '.$CI->lang->line((($weeks	> 1) ? 'date_weeks' : 'date_week')).', ';
		}
	
		$seconds -= $weeks * 604800;
	}			

	$days = floor($seconds / 86400);

	if ($months > 0 OR $weeks > 0 OR $days > 0)
	{
		if ($days > 0)
		{	
			$str .= $days.' '.$CI->lang->line((($days	> 1) ? 'date_days' : 'date_day')).', ';
		}

		$seconds -= $days * 86400;
	}

	$hours = floor($seconds / 3600);

	if ($days > 0 OR $hours > 0)
	{
		if ($hours > 0)
		{
			$str .= $hours.' '.$CI->lang->line((($hours	> 1) ? 'date_hours' : 'date_hour')).', ';
		}
	
		$seconds -= $hours * 3600;
	}

	$minutes = floor($seconds / 60);

	if ($days > 0 OR $hours > 0 OR $minutes > 0)
	{
		if ($minutes > 0)
		{	
			$str .= $minutes.' '.$CI->lang->line((($minutes	> 1) ? 'date_minutes' : 'date_minute')).', ';
		}
	
		$seconds -= $minutes * 60;
	}

	if ($str == '')
	{
		$str .= $seconds.' '.$CI->lang->line((($seconds	> 1) ? 'date_seconds' : 'date_second')).', ';
	}
		
	return substr(trim($str), 0, -1);
}

function days_ago($timestamp){
	$seconds = time() - $timestamp;
	$days = round($seconds/60/60/24);
	$seconds = $days*60*60*24;
	return seconds_to_human($seconds);
}

/**
 * Format a number of hours in HH:MM format, without regard to dates.
 * It's used for things like "how much time have I tracked in this task".
 * 
 * $total can be in decimal format, or in HH:MM format (in which case it is returned without processing).
 * 
 * Do NOT use this for date-related times, as it does not care about dates.
 * For example, if you pass it "13", it'll return 13:00,
 * not 1 PM, because 13 is the number of logged hours, and AM/PM don't matter.
 * For dates, ALWAYS use format_time(). It obeys time settings.
 * 
 * @param float|string $total
 * @return string
 */
function format_hours($total) {
    if (stristr($total, ':') === false) {
        $hours = floor($total);
        $remainder = $total - $hours;
        $minutes = round(60 * $remainder);
        return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
    } else {
        return $total;
    }
}

function better_timespan($unix, $include_ago_or_in = true) {
    get_instance()->load->helper('date');

    $time = time();

    if ($time <= $unix) {
        $result = timespan($time, $unix);
    } else {
        $result = timespan($unix, $time);
    }

    $result = explode(', ', $result);

    if (stristr($result[0], 'year') !== false or stristr($result[0], 'month') !== false or stristr($result[0], 'week') !== false) {
        $result = $result[0];
    } else {
        $result = $result[0] . (isset($result[1]) ? ' and ' . $result[1] : '');
    }

    $result = strtolower($result);

    if ($include_ago_or_in) {
        if ($unix > time()) {
            return "in $result";
        } else {
            return "$result ago";
        }
    } else {
        return $result;
    }
}

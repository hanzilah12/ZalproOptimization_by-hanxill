<?php


if (!function_exists('getConnectStatus')) {
	function getConnectStatus($id)
	{
		$ci = loadingInstance();
		$username = getUserInfo($id)->username;
		$ci->db->limit(1);
		$ci->db->order_by('radacctid', 'desc');
		$query = $ci->db->get_where('radacct', ['acctstoptime' => NULL, 'username' => $username]);

		if (0 < $query->num_rows()) {
			return true;
		}
		else {
			return false;
		}
	}
}

if (!function_exists('checkTokenStatus')) {
	function checkTokenStatus($username)
	{
		$ci = loadingInstance();
		$ci->db->limit(1);
		$ci->db->order_by('radacctid', 'desc');
		$query = $ci->db->get_where('radacct', ['username' => $username]);

		if (0 < $query->num_rows()) {
			return true;
		}
		else {
			return false;
		}
	}
}

if (!function_exists('getRadacctByUsername')) {
	function getRadacctByUsername($username = NULL, ?array $data = NULL)
	{
		$ci = loadingInstance();
		if (isset($data) && is_array($data)) {
			if (array_key_exists('limit', $data)) {
				$limit = $data['limit'];
				if (array_key_exists(0, $limit) && array_key_exists(1, $limit)) {
					$limitNumber = $limit[0];
					$limitOffset = $limit[1];
					$ci->db->limit($limitNumber, $limitOffset);
				}
				else if (array_key_exists(0, $limit)) {
					$ci->db->limit($limit[0]);
				}
			}

			if (array_key_exists('order_by', $data)) {
				$orderby = $data['order_by'];
				if (array_key_exists(0, $orderby) && array_key_exists(1, $orderby)) {
					$orderbyField = $orderby[0];
					$orderbySort = $orderby[1];
					$ci->db->order_by($orderbyField, $orderbySort);
				}
			}
			if (array_key_exists('username', $data) && isset($data['username']) && !empty($data['username'])) {
				$username = $data['username'];
				$ci->db->where('username', $username);
			}
			if (array_key_exists('acctstoptime', $data) && isset($data['acctstoptime']) && !empty($data['acctstoptime'])) {
				$acctstoptime = $data['acctstoptime'];
				if (($acctstoptime == 'not null') || ($acctstoptime == 'offline') || ($acctstoptime == 'IS NOT NULL')) {
					$ci->db->where('acctstoptime IS NOT NULL');
				}
				else {
					$ci->db->where('acctstoptime', $acctstoptime);
				}
			}

			$query = $ci->db->get('radacct');

			if (0 < $query->num_rows()) {
				return $query->result()[0];
			}
			else {
				return false;
			}
		}
		else {
			$ci->db->limit(1);
			$ci->db->order_by('radacctid', 'desc');
			$query = $ci->db->get_where('radacct', ['acctstoptime' => NULL, 'username' => $username]);

			if (0 < $query->num_rows()) {
				return $query->result()[0];
			}
			else {
				return false;
			}
		}

		return false;
	}
}

if (!function_exists('groupByID')) {
	function groupByID($id)
	{
		$ci = loadingInstance();
		$query = $ci->db->get_where('radgroupreply', ['id' => $id]);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

if (!function_exists('getRadcheckDataByUsername')) {
	function getRadcheckDataByUsername($username, $dataType = NULL)
	{
		$ci = loadingInstance();
		$query = $ci->db->get_where('radcheck', ['username' => $username]);

		if (0 < $query->num_rows()) {
			if (isset($dataType) && (($dataType == 'array') || ($dataType == 'Array'))) {
				return $query->result_array();
			}
			else {
				return $query->result();
			}
		}
		else {
			return false;
		}
	}
}

if (!function_exists('getRadcheckPassByUsername')) {
	function getRadcheckPassByUsername($username)
	{
		$ci = loadingInstance();
		$query = $ci->db->get_where('radcheck', ['username' => $username, 'attribute' => 'Cleartext-Password']);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

if (!function_exists('getRadcheckByUsername')) {
	function getRadcheckByUsername($username)
	{
		$ci = loadingInstance();
		$query = $ci->db->get_where('radcheck', ['username' => $username, 'attribute' => 'Expiration']);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

if (!function_exists('getRadcheckMacByUser')) {
	function getRadcheckMacByUser($username)
	{
		$ci = loadingInstance();
		$query = $ci->db->get_where('radcheck', ['username' => $username, 'attribute' => 'Calling-Station-Id']);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

if (!function_exists('getRadreplyIPByUser')) {
	function getRadreplyIPByUser($username)
	{
		$ci = loadingInstance();
		$query = $ci->db->get_where('radreply', ['username' => $username, 'attribute' => 'Framed-IP-Address']);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

// ========= ADDITION BY HAXILL - LASTLOGOFF FUNCTIONS =========

if (!function_exists('getLastLogoffByUsername')) {
	function getLastLogoffByUsername($username)
	{
		$ci = loadingInstance();
		$ci->db->limit(1);
		$ci->db->order_by('radacctid', 'desc');
		$query = $ci->db->get_where('radacct', ['username' => $username, 'acctstoptime !=' => NULL]);

		if (0 < $query->num_rows()) {
			$row = $query->result()[0];
			return isset($row->acctstoptime) ? $row->acctstoptime : 'N/A';
		}
		else {
			return 'N/A';
		}
	}
}

if (!function_exists('getLastLogoffByUsernameObject')) {
	function getLastLogoffByUsernameObject($username)
	{
		$ci = loadingInstance();
		$ci->db->limit(1);
		$ci->db->order_by('radacctid', 'desc');
		$query = $ci->db->get_where('radacct', ['username' => $username, 'acctstoptime !=' => NULL]);

		if (0 < $query->num_rows()) {
			return $query->result()[0];
		}
		else {
			return false;
		}
	}
}

// ============================================================

?>

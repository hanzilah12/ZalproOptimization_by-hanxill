<?php

defined('BASEPATH') || exit('No direct script access allowed');

class User extends CI_Controller
{
	public $settings;

	public function __construct()
	{
		parent::__construct();
		isLogin();
		isKena();
		ifClient();
		isPermitted('user_module', [11, 12, 13], true);
		$this->settings = settings()[0];
		$langFiles = ['global/global', 'home/home', 'user/users', 'user/tokens', 'token/token'];
		translation($langFiles);
	}

	public function index()
	{
		$this->all();
	}

	public function all()
	{
		isPermitted('user_all_module', [11, 12, 13], true);

		if (checkReseller()) {
			$currentRoleID = currentSessionData()['id'];
			$adminInfo = $this->main->getAdminByAdminID($currentRoleID);

			if ($adminInfo) {
				$adminCity = $adminInfo[0]->city;
				$adminArea = $adminInfo[0]->area;
				$data['reseller_city'] = $adminCity;
				$data['reseller_area'] = $adminArea;
				$data['reseller_subareas'] = $this->main->singleQuery('types', ['type' => 'subarea', 'type2' => $adminArea]);
			}
		}

		$data['cities'] = $this->main->getTypesCity();
		$data['areas'] = $this->main->getTypesArea();
		$data['subareas'] = $this->main->getTypesSubArea();
		$data['tokenPackages'] = $this->validPackages();
		$data['salespersons'] = $this->main->getSalePersonAdmins();
		$data['nases'] = $this->main->singleQuery('nas');
		$data['tokenCards'] = $this->main->singleQuery('token_cards');
		$this->load->view('themes/legacy/admin_portal/dashboard/header');
		$this->load->view('themes/legacy/admin_portal/users/all', $data);
		$this->load->view('themes/legacy/admin_portal/dashboard/footer');
	}

	public function online()
	{
		isPermitted('user_all_module', [11, 12, 13], true);
		$data['nases'] = NULL;
		$data['onlineusers'] = NULL;
		$data['staleSession'] = NULL;
		$this->load->view('themes/legacy/admin_portal/dashboard/header');
		$this->load->view('themes/legacy/admin_portal/users/online_users', $data);
		$this->load->view('themes/legacy/admin_portal/dashboard/footer');
	}

	public function stales()
	{
		isPermitted('user_all_module', [11, 12, 13], true);
		$data = [];
		$data['staleSession'] = NULL;
		$this->load->view('themes/legacy/admin_portal/dashboard/header');
		$this->load->view('themes/legacy/admin_portal/users/stales', $data);
		$this->load->view('themes/legacy/admin_portal/dashboard/footer');
	}

	public function view($id)
	{
		isPermitted('user_all_module', [11, 12, 13], true);
		$currentRoleID = $this->session->userdata('user_id');
		$userInfo = $this->main->getUserinfoByID($id);

		if ($userInfo) {
			$createdby = $userInfo[0]->createdby;
			$saleperson = $userInfo[0]->saleperson;
		}
		else {
			$this->session->set_flashdata('error', 'Oops! User Not Found');
			redirect('user/all');
		}
		if (checkSaleperson() || checkSubdealer()) {
			if ($currentRoleID != $saleperson) {
				$this->session->set_flashdata('error', 'You are not eligible to view this user\'s profile');
				redirect('home');
			}
		}
		else if (checkFranchise()) {
			$ids = [$currentRoleID];
			$this->db->where('franchise', $currentRoleID);
			$queryAdmins = $this->db->get('admin');

			if (0 < $queryAdmins->num_rows()) {
				foreach ($queryAdmins->result() as $row) {
					$ids[] = $row->adminid;
				}
			}

			$this->db->where_in('dealer', $ids);
			$queryAdmins = $this->db->get('admin');

			if (0 < $queryAdmins->num_rows()) {
				foreach ($queryAdmins->result() as $row) {
					$ids[] = $row->adminid;
				}
			}

			if (!in_array($saleperson, $ids)) {
				$this->session->set_flashdata('error', 'You are not eligible to view this user\'s profile');
				redirect('user/all');
			}
		}
		else if (checkDealer()) {
			$ids = [$currentRoleID];
			$this->db->where('dealer', $currentRoleID);
			$queryAdmins = $this->db->get('admin');

			if (0 < $queryAdmins->num_rows()) {
				foreach ($queryAdmins->result() as $row) {
					$ids[] = $row->adminid;
				}
			}

			if (!in_array($saleperson, $ids)) {
				$this->session->set_flashdata('error', 'You are not eligible to view this user\'s profile');
				redirect('user/all');
			}
		}

		$data['user'] = $userInfo;
		$data['lastInvoice'] = $this->main->getUserLastInvoice($id);
		$data['userstatus'] = $this->main->getTypesUserstatus();
		$data['packages'] = $this->validPackages();
		$data['salepersons'] = $this->main->getSalePersonAdmins();
		$data['staticips'] = $this->main->getAvailableStaticIPs();

		if (checkReseller()) {
			$currentRoleID = currentSessionData()['id'];
			$adminInfo = $this->main->getAdminByAdminID($currentRoleID);

			if ($adminInfo) {
				$adminCity = $adminInfo[0]->city;
				$adminArea = $adminInfo[0]->area;
				$data['subareas'] = $this->main->singleQuery('types', ['type' => 'subarea', 'type2' => $adminArea]);
			}
		}
		else {
			$data['areas'] = $this->main->getTypesArea();
			$data['subareas'] = $this->main->getTypesSubArea();
			$data['cities'] = $this->main->getTypesCity();
		}
		if (checkAdminOrStaff() || ($this->settings->nasvisibility == 1)) {
			$data['nases'] = $this->main->singleQuery('nas', '');
		}

		$data['radreply'] = $this->main->singleQuery('radreply', ['username' => $userInfo[0]->username]);
		$data['radcheck'] = $this->main->singleQuery('radcheck', ['username' => $userInfo[0]->username]);
		$data['documents'] = $this->main->singleQuery('documents', ['user_type' => 2, 'userID' => $id]);
		$data['notes'] = $this->main->singleQuery('notes', ['user_id' => $id, 'added_by' => $currentRoleID]);
		$data['profileViewingDoc'] = $this->userM->profileViewingDoc($id);
		$data['smsalerts'] = $this->smsM->getActiveTemplates();
		$data['janPayment'] = $this->userM->getTotalPaymentByMonth('01', date('Y'), $id);
		$data['febPayment'] = $this->userM->getTotalPaymentByMonth('02', date('Y'), $id);
		$data['marPayment'] = $this->userM->getTotalPaymentByMonth('03', date('Y'), $id);
		$data['aprPayment'] = $this->userM->getTotalPaymentByMonth('04', date('Y'), $id);
		$data['mayPayment'] = $this->userM->getTotalPaymentByMonth('05', date('Y'), $id);
		$data['junPayment'] = $this->userM->getTotalPaymentByMonth('06', date('Y'), $id);
		$data['julPayment'] = $this->userM->getTotalPaymentByMonth('07', date('Y'), $id);
		$data['augPayment'] = $this->userM->getTotalPaymentByMonth('08', date('Y'), $id);
		$data['sepPayment'] = $this->userM->getTotalPaymentByMonth('09', date('Y'), $id);
		$data['octPayment'] = $this->userM->getTotalPaymentByMonth('10', date('Y'), $id);
		$data['novPayment'] = $this->userM->getTotalPaymentByMonth('11', date('Y'), $id);
		$data['decPayment'] = $this->userM->getTotalPaymentByMonth('12', date('Y'), $id);
		$data['janBalance'] = $this->userM->getLastBalanceByMonth('01', date('Y'), $id);
		$data['febBalance'] = $this->userM->getLastBalanceByMonth('02', date('Y'), $id);
		$data['marBalance'] = $this->userM->getLastBalanceByMonth('03', date('Y'), $id);
		$data['aprBalance'] = $this->userM->getLastBalanceByMonth('04', date('Y'), $id);
		$data['mayBalance'] = $this->userM->getLastBalanceByMonth('05', date('Y'), $id);
		$data['junBalance'] = $this->userM->getLastBalanceByMonth('06', date('Y'), $id);
		$data['julBalance'] = $this->userM->getLastBalanceByMonth('07', date('Y'), $id);
		$data['augBalance'] = $this->userM->getLastBalanceByMonth('08', date('Y'), $id);
		$data['sepBalance'] = $this->userM->getLastBalanceByMonth('09', date('Y'), $id);
		$data['octBalance'] = $this->userM->getLastBalanceByMonth('10', date('Y'), $id);
		$data['novBalance'] = $this->userM->getLastBalanceByMonth('11', date('Y'), $id);
		$data['decBalance'] = $this->userM->getLastBalanceByMonth('12', date('Y'), $id);
		if ((3 <= $userInfo[0]->connectiontype) && ($userInfo[0]->connectiontype <= 5)) {
			$nasObj = $this->main->singleQuery('nas', ['id' => $userInfo[0]->nasid]);

			if ($nasObj) {
				$nasAPI = $nasObj[0]->nasapi;

				if ($nasAPI == 1) {
					$routerObj = mkUtilByID($userInfo[0]->nasid);
					$client = mkClientByID($userInfo[0]->nasid);

					if ($routerObj) {
						if ($userInfo[0]->connectiontype == 3) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ppp/active/print');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('name', $userInfo[0]->username));
							$apiPPPoEData = $client->sendSync($printRequest);
							$data['apiPPPoEData'] = $apiPPPoEData;
						}
						else if ($userInfo[0]->connectiontype == 4) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('user', $userInfo[0]->username));
							$apiHotspotData = $client->sendSync($printRequest);
							$data['apiHotspotData'] = $apiHotspotData;
						}
						else if ($userInfo[0]->connectiontype == 5) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ip/arp/print');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('mac-address', $userInfo[0]->macaddress));
							$apiStaticData = $client->sendSync($printRequest);
							$data['apiStaticData'] = $apiStaticData;
						}
					}
				}
			}
		}

		$this->db->reset_query();
		$userUpdateRes = $this->main->singleUpdate('usersinfo', ['last_profile_visit_time' => date('Y-m-d H:i:s')], ['id' => $id]);
		$this->load->view('themes/legacy/admin_portal/dashboard/header');
		$this->load->view('themes/legacy/admin_portal/users/profile', $data);
		$this->load->view('themes/legacy/admin_portal/dashboard/footer');
	}

	public function insert()
	{
		isPermitted('user_add_new', [11, 12, 13], true);
		$adminUsername = $this->session->user_username;
		$adminID = $this->session->user_id;
	

         	if (($this->settings->kenalimit <= totalUsers()) || ($this->settings->kenalimit == NULL)) {
			$this->session->set_flashdata('error', 'Your User Limit is Over. Please Contact Us For Upgrade.');
			redirect('support');
		}

		$salesperson = $this->input->post('salesperson', true);
		if (!isset($salesperson) || empty($salesperson)) {
			$salespersonPassedID = $adminID;
		}
		else {
			$salespersonPassedID = $salesperson;
		}

		$resellerSettings = resellersSettings($salespersonPassedID);
		if (!$resellerSettings || (($resellerSettings !== true) && isset($resellerSettings) && ($resellerSettings->resl_create_user != 1))) {
			$this->session->set_flashdata('error', 'Insufficient Permission To Create User.');
			redirect('user/all');
		}

		$checkUserLimitation = checkReslUserLimit($salespersonPassedID);
		if (($checkUserLimitation !== true) || (isset($checkUserLimitation) && is_array($checkUserLimitation) && !$checkUserLimitation['status'])) {
			$this->session->set_flashdata('error', $checkUserLimitation['message']);
			redirect('user/all');
		}

		$data = [];
		$this->form_validation->set_rules('name', 'Name', 'trim|required');

		if ($this->settings->random_username != 1) {
			if (checkReseller()) {
				$this->form_validation->set_rules('username', 'User Name', 'trim|required');
			}
			else {
				$this->form_validation->set_rules('username', 'User Name', 'trim|required|is_unique[usersinfo.username]');
			}
		}

		if ($this->settings->random_password != 1) {
			$this->form_validation->set_rules('portalpass', 'Portal Password', 'trim|required');
		}

		if ($this->settings->allow_user_dup_nic == 1) {
			$this->form_validation->set_rules('nic', 'NIC', 'trim|required');
		}
		else {
			$this->form_validation->set_rules('nic', 'NIC', 'trim|required|is_unique[usersinfo.nic]');
		}

		if ($this->settings->allow_user_dup_phone == 1) {
			$this->form_validation->set_rules('mobile', 'Mobile', 'trim|required');
		}
		else {
			$this->form_validation->set_rules('mobile', 'Mobile', 'trim|required|is_unique[usersinfo.mobile]');
		}

		$email = $this->input->post('email', true);

		if ($this->settings->allow_user_dup_email == 1) {
			$this->form_validation->set_rules('email', 'Email', 'trim|valid_email');
		}
		else if (isset($email) && !empty($email)) {
			$this->form_validation->set_rules('email', 'Email', 'trim|valid_email|is_unique[usersinfo.email]');
		}

		$this->form_validation->set_rules('phone', 'Phone', 'trim|is_unique[usersinfo.phone]');

		if ($this->form_validation->run()) {
			$adminData = getAdminByID($adminID);

			if (!$adminData) {
				$this->session->set_flashdata('error', 'Oops! Something Went Wrong.');
				redirect('user/all');
			}

			$salesperson = $this->input->post('salesperson', true);
			if (!isset($salesperson) || empty($salesperson)) {
				$salesperson = $adminID;
			}

			if (checkReseller()) {
				$package = $this->input->post('package', true);
				$fpackagePermission = $this->packagesM->profilePackagePermission($package, $adminID);
				if (!isset($fpackagePermission) || !is_array($fpackagePermission) || !$fpackagePermission[0]) {
					$this->session->set_flashdata('error', $fpackagePermission[1]);
					redirect('user/all');
				}
			}

			$username = $this->input->post('username', true);
			if (empty($username) && ($this->settings->random_username == 1)) {
				$usernameAvaility = 0;
				$x = 0;

				while ($x == $usernameAvaility) {
					$username = random_str($this->settings->random_username_limit);
					$checkUsernameRes = $this->main->getUserByUsername($username);

					if (!$checkUsernameRes) {
						$usernameAvaility = 1;
					}
				}
			}

			$password = $this->input->post('portalpass', true);
			if (empty($password) && ($this->settings->random_password == 1)) {
				$password = random_str($this->settings->random_password_limit);
			}

			if (checkReseller()) {
				if (strpos($username, $adminUsername . $this->settings->prefix_characters) !== false) {
					$data['username'] = preg_replace('/\\s+/', '', $username);
				}
				else if ($this->settings->userprefix == 1) {
					$data['username'] = $adminUsername . $this->settings->prefix_characters . preg_replace('/\\s+/', '', $username);
				}
				else {
					$data['username'] = preg_replace('/\\s+/', '', $username);
				}
			}
			else {
				$data['username'] = preg_replace('/\\s+/', '', $username);
			}

			$data['name'] = $this->input->post('name', true);
			$data['password'] = $password;
			$data['portalpass'] = md5($password);
			$data['status'] = 1;
			$data['smsstatus'] = 1;
			$data['maclock'] = 0;
			$data['ispid'] = $adminData->ispid;
			$data['package'] = $this->input->post('package', true);
			$data['saleperson'] = $salesperson;
			$data['nic'] = $this->input->post('nic', true);
			$data['mobile'] = $this->input->post('mobile', true);
			$data['phone'] = $this->input->post('phone', true);
			$data['email'] = $this->input->post('email', true);
			$data['address'] = $this->input->post('address', true);

			if (checkAdminOrStaff()) {
				$city = $this->input->post('city', true);
				if (isset($city) && !empty($city)) {
					$data['city'] = $city;
				}

				$area = $this->input->post('area', true);
				if (isset($area) && !empty($area)) {
					$data['area'] = $area;
				}

				$subarea = $this->input->post('subarea', true);
				if (isset($subarea) && !empty($subarea)) {
					$data['subarea'] = $subarea;
				}
			}
			else {
				$data['city'] = $adminData->city;
				$data['area'] = $adminData->area;
				$subarea = $this->input->post('subarea', true);
				if (isset($subarea) && !empty($subarea)) {
					$data['subarea'] = $subarea;
				}
			}
			if (latLonCheck($this->input->post('lat', true)) || latLonCheck($this->input->post('lon', true))) {
				$data['lat'] = $this->input->post('lat', true);
				$data['lon'] = $this->input->post('lon', true);
			}

			$data['joindate'] = date('Y-m-d H:i:s');
			$data['createdby'] = $this->session->userdata('user_id');
			$data['photo'] = '';
			$data['c_package'] = 0;
			$data['connectiontype'] = $this->input->post('connectiontype', true);
			$data['connectionstatus'] = 0;
			if (checkAdminOrStaff() || ($this->settings->nasvisibility == 1)) {
				$data['nasid'] = $this->input->post('nasid', true);
			}
			else {
				$data['nasid'] = $adminData->nas;
			}

			$data['boxnumber'] = $this->input->post('boxnumber', true);
			$data['boxaddress'] = $this->input->post('boxaddress', true);
			$data['uplinkport'] = $this->input->post('uplinkport', true);
			$data['fibercode'] = $this->input->post('fibercode', true);
			$data['fibercolor'] = $this->input->post('fibercolor', true);
			$data['switchboard'] = $this->input->post('switchboard', true);
			$data['switchport'] = $this->input->post('switchport', true);
			$data['backupconnection'] = $this->input->post('backupconnection', true);
			$data['electricitysocket'] = $this->input->post('electricitysocket', true);
			$data['cabletype'] = $this->input->post('cabletype', true);
			$true = $this->db->insert('usersinfo', $data);
			$userid = $this->db->insert_id();
			$this->main->insertActivity('User Insert');

			// =====================================================
			// AUTO-ADD CLEARTEXT-PASSWORD RADIUS ATTRIBUTE
			// =====================================================
			if ($true && !empty($userid)) {
				$radCheckData = [
					'username'  => $data['username'],
					'attribute' => 'Cleartext-Password',
					'op'        => ':=',
					'value'     => $password
				];
				$this->main->singleInsert('radcheck', $radCheckData);
			}

			if ($true) {
				if (if_SMSEnable()) {
					if (chkSMSAlert(8) && getSMSAlert(8)) {
						$userData = getUserInfo($userid);

						if ($userData) {
							if (getUserSMSStatus($userid) && getUserSalespersonSms($userid, $userData->saleperson)) {
								$mobile = $userData->mobile;
								$smsText = getSMSAlert(8)->template;
								$smsText = str_replace('{password}', $this->input->post('portalpass', true), $smsText);
								$smsText = getFinalSMSText($smsText, 2, $userid);
								$smsRes = sendSMS($mobile, $smsText);
								$smsData = ['smsAlert' => 8, 'destination' => $mobile, 'message' => $smsText, 'userID' => $userid, 'adminID' => 0];
								$responseSmsDelivery = $this->smsM->insertDelivery($smsRes, $smsData);
							}
						}
					}
				}
			}


			if ($true) {
				$this->session->set_flashdata('success', 'User Successfully Created');
				redirect('user/all');
			}
			else {
				$this->session->set_flashdata('error', 'Oops! Something Went Wrong.');
				redirect('user/all');
			}
		}
		else {
			$this->session->set_flashdata('error', preg_replace('/\\s+/', ' ', strip_tags(validation_errors())));
			redirect('user/all');
		}
	}

	public function update()
	{
		isPermitted('user_edit_profile', [11, 12, 13], true);
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		checkUserPermission($userID);
		$adminID = $this->main->getUserinfoByID($userID)[0]->saleperson;
		$data = [];
		$this->form_validation->set_rules('name', 'Name', 'trim|required');

		if ($this->settings->allow_user_dup_nic == 1) {
			$this->form_validation->set_rules('nic', 'NIC', 'trim|required');
		}
		else {
			$this->form_validation->set_rules('nic', 'NIC', 'trim|required|callback__is_unique_nic[' . $userID . ']');
		}

		if ($this->settings->allow_user_dup_phone == 1) {
			$this->form_validation->set_rules('mobile', 'Mobile', 'trim|required');
		}
		else {
			$this->form_validation->set_rules('mobile', 'Mobile', 'trim|required|callback__is_unique_mobile[' . $userID . ']');
		}

		$email = $this->input->post('email', true);

		if ($this->settings->allow_user_dup_email == 1) {
			$this->form_validation->set_rules('email', 'Email', 'trim|valid_email');
		}
		else if (isset($email) && !empty($email)) {
			$this->form_validation->set_rules('email', 'Email', 'trim|valid_email|callback__is_unique_email[' . $userID . ']');
		}

		if ($this->form_validation->run()) {
			$data['name'] = $this->input->post('name', true);
			$data['nic'] = $this->input->post('nic', true);
			$data['mobile'] = $this->input->post('mobile', true);
			$data['phone'] = $this->input->post('phone', true);
			$data['email'] = $this->input->post('email', true);
			$data['address'] = $this->input->post('address', true);

			if (checkAdminOrStaff()) {
				$city = $this->input->post('city', true);
				if (isset($city) && !empty($city)) {
					$data['city'] = $city;
				}

				$area = $this->input->post('area', true);
				if (isset($area) && !empty($area)) {
					$data['area'] = $area;
				}

				$subarea = $this->input->post('subarea', true);
				if (isset($subarea) && !empty($subarea)) {
					$data['subarea'] = $subarea;
				}
			}
			else {
				$subarea = $this->input->post('subarea', true);
				if (isset($subarea) && !empty($subarea)) {
					$data['subarea'] = $subarea;
				}
			}
			if (latLonCheck($this->input->post('lat', true)) || latLonCheck($this->input->post('lon', true))) {
				$data['lat'] = $this->input->post('lat', true);
				$data['lon'] = $this->input->post('lon', true);
			}

			$joinDate = $this->input->post('joindate', true);
			if (checkAdminOrStaff() && isset($joinDate) && !empty($joinDate)) {
				$data['joindate'] = date('Y-m-d H:i:s', strtotime($joinDate));
			}

			$data['updatedate'] = date('Y-m-d H:i:s');
			$data['updatedby'] = $this->session->userdata('user_id');

			if (checkAdminOrStaff()) {
				$data['connectiontype'] = $this->input->post('connectiontype', true);
			}
			else {
				$data['connectiontype'] = $this->main->getUserinfoByID($userID)[0]->connectiontype;
			}
			if (checkAdminOrStaff() || ($this->settings->nasvisibility == 1)) {
				$data['nasid'] = $this->input->post('nasid', true);
			}
			else {
				$data['nasid'] = getAdminByID($adminID)->nas;
			}

			$data['boxnumber'] = $this->input->post('boxnumber', true);
			$data['boxaddress'] = $this->input->post('boxaddress', true);
			$data['uplinkport'] = $this->input->post('uplinkport', true);
			$data['fibercode'] = $this->input->post('fibercode', true);
			$data['fibercolor'] = $this->input->post('fibercolor', true);
			$data['switchboard'] = $this->input->post('switchboard', true);
			$data['switchport'] = $this->input->post('switchport', true);
			$data['backupconnection'] = $this->input->post('backupconnection', true);
			$data['electricitysocket'] = $this->input->post('electricitysocket', true);
			$data['cabletype'] = $this->input->post('cabletype', true);
			$this->db->where('id', $userID);
			$true = $this->db->update('usersinfo', $data);
			$this->main->insertActivity('User Update', $userID);

			if ($true) {
				$this->session->set_flashdata('success', 'User Successfully Update');
				redirect('user/profile/' . $userID);
			}
		}
		else {
			$this->session->set_flashdata('error', preg_replace('/\\s+/', ' ', strip_tags(validation_errors())));
			redirect('user/profile/' . $userID);
		}
	}

	public function _is_unique_nic($nicValue, $userID)
	{
		$result = $this->db->where('id !=', $userID)->where('nic', $nicValue)->get('usersinfo')->row_array();

		if ($result) {
			$this->form_validation->set_message(__FUNCTION__, '%s Already Exists!');
			return false;
		}

		return true;
	}

	public function _is_unique_mobile($mobileValue, $userID)
	{
		$result = $this->db->where('id !=', $userID)->where('mobile', $mobileValue)->get('usersinfo')->row_array();

		if ($result) {
			$this->form_validation->set_message(__FUNCTION__, '%s Already Exists!');
			return false;
		}

		return true;
	}

	public function _is_unique_email($emailValue, $userID)
	{
		$result = $this->db->where('id !=', $userID)->where('email', $emailValue)->get('usersinfo')->row_array();

		if ($result) {
			$this->form_validation->set_message(__FUNCTION__, '%s Already Exists!');
			return false;
		}

		return true;
	}

	public function delete($id)
	{
		$errorMessages = [];
		$successMessages = [];
		isPermitted('user_delete_profile', [11, 12, 13], true);
		$resellerSettings = resellersSettings();
		if (checkReseller() && $resellerSettings && ($resellerSettings->resl_delete_user != 1)) {
			$this->session->set_flashdata('error', 'Insufficient Permission To Delete User.');
			redirect('user/all');
		}

		checkUserPermission($id);
		$userOnlineStatus = getConnectStatus($id);

		if ($userOnlineStatus) {
			$this->session->set_flashdata('error', 'You Can\'t Delete Online User.');
			redirect('user/all');
		}

		$this->db->flush_cache();

		if ($this->main->getUserinfoByID($id)) {
			$userData = $this->main->getUserinfoByID($id)[0];
			$username = $userData->username;

			try {
				$disconnectRes = $this->radiusM->kickOutUsers($username);

				if ($disconnectRes) {
					$radacctData['acctstoptime'] = date('Y-m-d H:i:s');
					$radacctData['acctterminatecause'] = 'User Deleted & Sesssion Stopped';
					$result = $this->main->singleUpdate('radacct', $radacctData, ['username' => $userData->username, 'acctstoptime' => NULL]);
					array_push($successMessages, 'User Successfully Disconnectd');
				}
				else {
					array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
				}
			}
			catch (Exception $e) {
				$errorMessage = 'Disconnect Error: ' . $e->getMessage();
				array_push($errorMessages, $errorMessage);
			}

			$username = $this->main->getUserinfoByID($id)[0]->username;
			$this->db->flush_cache();
			$num_rows = $this->db->get_where('radcheck', ['username' => $username])->num_rows();

			if (0 < $num_rows) {
				$this->db->where('username', $username);
				$delete4 = $this->db->delete('radcheck');
			}

			$this->db->flush_cache();
			$num_rows = $this->db->get_where('radreply', ['username' => $username])->num_rows();

			if (0 < $num_rows) {
				$this->db->where('username', $username);
				$delete6 = $this->db->delete('radreply');
			}

			$this->db->flush_cache();
			$num_rows = $this->db->get_where('radusergroup', ['username' => $username])->num_rows();

			if (0 < $num_rows) {
				$this->db->where('username', $username);
				$delete7 = $this->db->delete('radusergroup');
			}

			$this->db->flush_cache();
			$this->db->where('id', $id);
			$delete11 = $this->db->delete('usersinfo');
			$this->db->flush_cache();
			$this->main->insertActivity('User Deleted', $id);
			$this->session->set_flashdata('successMessages', $successMessages);
			$this->session->set_flashdata('errorMessages', $errorMessages);

			if (isset($delete11)) {
				$this->session->set_flashdata('success', 'User Successfully Deleted');
			}
			else {
				$this->session->set_flashdata('error', 'Oops! Something Wrong');
			}
		}
		else {
			$this->session->set_flashdata('error', 'User Not Found');
		}

		redirect('user/all/');
	}

	public function changephoto()
	{
		isPermitted('user_change_photo', [11, 12, 13], true);
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		isUserAccessible($userID);
		$data = [];
		$imagePath = realpath(APPPATH . '../assets/images');
		$config['upload_path'] = $imagePath;
		$config['allowed_types'] = 'jpg|png|jpeg|gif';
		$config['file_name'] = date('Ymd_his_') . rand(10, 99) . rand(10, 99) . rand(10, 99);
		$config['max_size'] = 2048;
		$this->load->library('upload', $config);

		if ($this->upload->do_upload('photo')) {
			$uploadData = $this->upload->data();
			$data['photo'] = $uploadData['file_name'];
			$config['image_library'] = 'gd2';
			$config['source_image'] = $uploadData['full_path'];
			$config['new_image'] = $imagePath . '/final';
			$config['quality'] = '60%';
			$config['maintain_ratio'] = false;

			if ($uploadData['image_height'] < $uploadData['image_width']) {
				$config['width'] = $uploadData['image_height'];
				$config['height'] = $uploadData['image_height'];
				$config['x_axis'] = ($uploadData['image_width'] / 2) - ($config['width'] / 2);
			}
			else {
				$config['height'] = $uploadData['image_width'];
				$config['width'] = $uploadData['image_width'];
				$config['y_axis'] = ($uploadData['image_height'] / 2) - ($config['height'] / 2);
			}

			$this->image_lib->clear();
			$this->image_lib->initialize($config);
			$this->image_lib->crop();
			$config['source_image'] = $imagePath . '/crop' . '/' . $uploadData['file_name'];
			$config['new_image'] = $imagePath . '/final';
			$config['maintain_ratio'] = true;
			$config['width'] = 150;
			$config['height'] = 150;
			$this->image_lib->clear();
			$this->image_lib->initialize($config);
			$this->image_lib->resize();
			unlink($uploadData['full_path']);
			$this->db->where('id', $userID);
			$true = $this->db->update('usersinfo', $data);
			$this->main->insertActivity('User Update', $userID);

			if ($true) {
				$this->session->set_flashdata('success', 'Photo Successfully Update');
				redirect('user/profile/' . $userID);
			}
			else {
				$this->session->set_flashdata('error', 'Opps, Somethig Went Wrong');
				redirect('user/profile/' . $userID);
			}
		}
		else {
			$uploadError = ['error' => $this->upload->display_errors()];
			$uploadErrorText = $uploadError['error'];
			$this->session->set_flashdata('error', $uploadErrorText);
			redirect('user/profile/' . $userID);
		}
	}

	public function changepassword()
	{
		isPermitted('user_change_password', [11, 12, 13], true);
		$errorMessages = [];
		$successMessages = [];
		$userID = $this->input->post('userID', true);


		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		if ($this->main->getUserinfoByID($userID)) {
			isUserAccessible($userID);
			$userData = $this->main->getUserinfoByID($userID)[0];
			$data = [];
			$this->form_validation->set_rules('password', 'Password', 'trim|required|max_length[20]');
			$this->form_validation->set_rules('conpassword', 'Confirm Password', 'trim|required|matches[password]');

			if ($this->form_validation->run()) {
				$newPassword = $this->input->post('password', true);
				$data['password'] = $newPassword;
				$data['portalpass'] = md5($newPassword);
				$this->db->where('id', $userID);
				$true = $this->db->update('usersinfo', $data);
				$username = $userData->username;

				// =====================================================
				// AUTO-UPDATE CLEARTEXT-PASSWORD RADIUS ATTRIBUTE
				// =====================================================
				$radQuery = $this->main->singleQuery('radcheck', ['username' => $username, 'attribute' => 'Cleartext-Password']);

				if ($radQuery) {
					// UPDATE existing Cleartext-Password
					$data2['value'] = $newPassword;
					$this->main->singleUpdate('radcheck', $data2, ['username' => $username, 'attribute' => 'Cleartext-Password']);
				} else {
					// INSERT if doesn't exist
					$radCheckData = [
						'username'  => $username,
						'attribute' => 'Cleartext-Password',
						'op'        => ':=',
						'value'     => $newPassword
					];
					$this->main->singleInsert('radcheck', $radCheckData);
				}

				// Kick out user if connected (regardless of status)
				if (($userData->status == 2) && ($userData->connectionstatus == 1)) {
					try {
						$disconnectRes = $this->radiusM->kickOutUsers($username);

						if ($disconnectRes) {
							array_push($successMessages, 'User Successfully Disconnectd');
						}
						else {
							array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
						}
					}
					catch (Exception $e) {
						$errorMessage = 'Disconnect Error: ' . $e->getMessage();
						array_push($errorMessages, $errorMessage);
					}
				}

				$this->main->insertActivity('User Update', $userID);

				if (if_SMSEnable()) {
					if (chkSMSAlert(3) && getSMSAlert(3)) {
						$userData = getUserInfo($userID);

						if ($userData) {
							if (getUserSMSStatus($userID) && getUserSalespersonSms($userID, $userData->saleperson)) {
								$mobile = $userData->mobile;
								$smsText = getSMSAlert(3)->template;
								$smsText = str_replace('{password}', $this->input->post('password', true), $smsText);
								$smsText = getFinalSMSText($smsText, 2, $userID);
								$smsRes = sendSMS($mobile, $smsText);
								$smsData = ['smsAlert' => 3, 'destination' => $mobile, 'message' => $smsText, 'userID' => $userID, 'adminID' => 0];
								$responseSmsDelivery = $this->smsM->insertDelivery($smsRes, $smsData);
							}
						}
					}
				}

				$this->session->set_flashdata('successMessages', $successMessages);
				$this->session->set_flashdata('errorMessages', $errorMessages);

				if ($true) {
					$this->session->set_flashdata('success', 'User Password Successfully Updated');
					redirect('user/profile/' . $userID);
				}
				else {
					$this->session->set_flashdata('error', 'Oops! Something Wrong');
					redirect('user/profile/' . $userID);
				}
			}
			else {
				$this->session->set_flashdata('error', preg_replace('/\\s+/', ' ', strip_tags(validation_errors())));
				redirect('user/profile/' . $userID);
			}
		}
		else {
			$this->session->set_flashdata('error', 'No User Found');
			redirect('user/profile/' . $userID);
		}
	}

	public function enableconnection($id)
	{
		isPermitted('user_disable_net', [11, 12, 13], true);
		checkUserPermission($id);
		$userData = $this->main->getUserinfoByID($id)[0];
		$nasObj = $this->main->singleQuery('nas', ['id' => $userData->nasid]);
		if (($userData->connectiontype == 1) || ($userData->connectiontype == 2)) {
			$data['connectionstatus'] = 1;
			$this->db->where('id', $id);
			$this->db->update('usersinfo', $data);
			$this->db->reset_query();
			$this->main->singleDelete('radcheck', ['username' => $userData->username, 'attribute' => 'Auth-Type', 'value' => 'Reject']);
			$this->main->insertActivity('User Connection Enabled', $id);
			$this->session->set_flashdata('success', 'User Connection Successfully Enabled.');
			redirect('user/profile/' . $id);
		}
		else if (($userData->connectiontype == 3) || ($userData->connectiontype == 4) || ($userData->connectiontype == 5)) {
			if ($nasObj) {
				$nasAPI = $nasObj[0]->nasapi;

				if ($nasAPI == 1) {
					$routerObj = mkUtilByID($userData->nasid);
					$client = mkClientByID($userData->nasid);

					if ($routerObj) {
						if ($userData->connectiontype == 3) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ppp/secret/print');
							$printRequest->setArgument('.proplist', '.id');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('name', $userData->username));
							$apiID = $client->sendSync($printRequest)->getProperty('.id');
							$disableRequest = new PEAR2\Net\RouterOS\Request('/ppp/secret/enable');
							$disableRequest->setArgument('numbers', $apiID);
							$client->sendSync($disableRequest);
						}
						else if ($userData->connectiontype == 4) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print');
							$printRequest->setArgument('.proplist', '.id');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('name', $userData->username));
							$apiID = $client->sendSync($printRequest)->getProperty('.id');
							$disableRequest = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/enable');
							$disableRequest->setArgument('numbers', $apiID);
							$client->sendSync($disableRequest);
						}
						else if ($userData->connectiontype == 5) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ip/arp/print');
							$printRequest->setArgument('.proplist', '.id');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('mac-address', $userData->macaddress));
							$apiID = $client->sendSync($printRequest)->getProperty('.id');
							$disableRequest = new PEAR2\Net\RouterOS\Request('/ip/arp/enable');
							$disableRequest->setArgument('numbers', $apiID);
							$client->sendSync($disableRequest);
						}

						$data['connectionstatus'] = 1;
						$this->db->where('id', $id);
						$this->db->update('usersinfo', $data);
						$this->db->reset_query();
						$this->main->insertActivity('User Connection Enabled', $id);
						$this->session->set_flashdata('success', 'User Connection Successfully Enabled.');
						redirect('user/profile/' . $id);
					}
					else {
						$this->session->set_flashdata('error', 'Oops! Somethig Wrong with NAS. Make Sure its Active.');
						redirect('user/profile/' . $id);
					}
				}
				else {
					$this->session->set_flashdata('error', 'NAS API is Not Enable. Please, Make Sure NAS API Enable & Active.');
					redirect('user/profile/' . $id);
				}
			}
			else {
				$this->session->set_flashdata('error', 'User NAS is Not Found! Please, Update User NAS.');
				redirect('user/profile/' . $id);
			}
		}
	}

	public function disableconnection($id)
	{
		isPermitted('user_disable_net', [11, 12, 13], true);
		checkUserPermission($id);
		$errorMessages = [];
		$successMessages = [];
		$data = [];
		$userData = $this->main->getUserinfoByID($id)[0];
		$nasObj = $this->main->singleQuery('nas', ['id' => $userData->nasid]);
		if (($userData->connectiontype == 1) || ($userData->connectiontype == 2)) {
			if ($nasObj) {
				$data['connectionstatus'] = 0;
				$this->db->where('id', $id);
				$this->db->update('usersinfo', $data);
				$this->db->reset_query();
				$radcheck['username'] = $userData->username;
				$radcheck['attribute'] = 'Auth-Type';
				$radcheck['op'] = ':=';
				$radcheck['value'] = 'Reject';
				$this->main->singleInsert('radcheck', $radcheck);
				$this->main->insertActivity('User Connection Disabled', $id);

				try {
					$disconnectRes = $this->radiusM->kickOutUsers($userData->username);

					if ($disconnectRes) {
						$radacctData['acctstoptime'] = date('Y-m-d H:i:s');
						$radacctData['acctterminatecause'] = 'User Deleted & Sesssion Stopped';
						$result = $this->main->singleUpdate('radacct', $radacctData, ['username' => $userData->username, 'acctstoptime' => NULL]);
						array_push($successMessages, 'User Successfully Disconnectd');
					}
					else {
						array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
					}
				}
				catch (Exception $e) {
					$errorMessage = 'Disconnect Error: ' . $e->getMessage();
					array_push($errorMessages, $errorMessage);
				}

				$this->session->set_flashdata('successMessages', $successMessages);
				$this->session->set_flashdata('errorMessages', $errorMessages);
				$this->session->set_flashdata('success', 'User Connection Successfully Disabled.');
				redirect('user/profile/' . $id);
			}
			else {
				$this->session->set_flashdata('error', 'User NAS is Not Found! Please, Update User NAS.');
				redirect('user/profile/' . $id);
			}
		}
		else if (($userData->connectiontype == 3) || ($userData->connectiontype == 4) || ($userData->connectiontype == 5)) {
			if ($nasObj) {
				$nasAPI = $nasObj[0]->nasapi;

				if ($nasAPI == 1) {
					$routerObj = mkUtilByID($userData->nasid);
					$client = mkClientByID($userData->nasid);

					if ($routerObj) {
						if ($userData->connectiontype == 3) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ppp/secret/print');
							$printRequest->setArgument('.proplist', '.id');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('name', $userData->username));
							$apiID = $client->sendSync($printRequest)->getProperty('.id');
							$disableRequest = new PEAR2\Net\RouterOS\Request('/ppp/secret/disable');
							$disableRequest->setArgument('numbers', $apiID);
							$client->sendSync($disableRequest);
						}
						else if ($userData->connectiontype == 4) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print');
							$printRequest->setArgument('.proplist', '.id');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('name', $userData->username));
							$apiID = $client->sendSync($printRequest)->getProperty('.id');
							$disableRequest = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/disable');
							$disableRequest->setArgument('numbers', $apiID);
							$client->sendSync($disableRequest);
						}
						else if ($userData->connectiontype == 5) {
							$printRequest = new PEAR2\Net\RouterOS\Request('/ip/arp/print');
							$printRequest->setArgument('.proplist', '.id');
							$printRequest->setQuery(PEAR2\Net\RouterOS\Query::where('mac-address', $userData->macaddress));
							$apiID = $client->sendSync($printRequest)->getProperty('.id');
							$disableRequest = new PEAR2\Net\RouterOS\Request('/ip/arp/disable');
							$disableRequest->setArgument('numbers', $apiID);
							$client->sendSync($disableRequest);
						}

						$data['connectionstatus'] = 0;
						$data['status'] = 0;
						$this->db->where('id', $id);
						$this->db->update('usersinfo', $data);
						$this->main->insertActivity('User Connection Disabled', $id);
						$this->db->reset_query();
						$this->session->set_flashdata('success', 'User Connection Successfully Disabled.');
						redirect('user/profile/' . $id);
					}
					else {
						$this->session->set_flashdata('error', 'Oops! Somethig Wrong with NAS. Make Sure its Active.');
						redirect('user/profile/' . $id);
					}
				}
				else {
					$this->session->set_flashdata('error', 'NAS API is Not Enable. Please, Make Sure NAS API Enable & Active.');
					redirect('user/profile/' . $id);
				}
			}
			else {
				$this->session->set_flashdata('error', 'User NAS is Not Found! Please, Update User NAS.');
				redirect('user/profile/' . $id);
			}
		}
	}

	public function disconnect($id)
	{
		isPermitted('user_disable_net', [11, 12, 13], true);
		if (isset($id) && !empty($id)) {
			$errorMessages = [];
			$successMessages = [];
			$data = [];
			checkUserPermission($id);
			isUserAccessible($id);
			$userData = $this->main->getUserinfoByID($id)[0];
			$nasObj = $this->main->singleQuery('nas', ['id' => $userData->nasid]);
			if (($userData->connectiontype == 1) || ($userData->connectiontype == 2)) {
				if ($nasObj) {
					try {
						$disconnectRes = $this->radiusM->kickOutUsers($userData->username);

						if ($disconnectRes) {
							$radacctData['acctstoptime'] = date('Y-m-d H:i:s');
							$radacctData['acctterminatecause'] = 'User Deleted & Sesssion Stopped';
							$result = $this->main->singleUpdate('radacct', $radacctData, ['username' => $userData->username, 'acctstoptime' => NULL]);
							array_push($successMessages, 'User Successfully Disconnectd');
						}
						else {
							array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
						}
					}
					catch (Exception $e) {
						$errorMessage = 'Disconnect Error: ' . $e->getMessage();
						array_push($errorMessages, $errorMessage);
					}

					$this->session->set_flashdata('successMessages', $successMessages);
					$this->session->set_flashdata('errorMessages', $errorMessages);
					$this->main->insertActivity('User Connection Disconnected', $id);
					redirect('user/profile/' . $id);
				}
				else {
					$this->session->set_flashdata('error', 'User NAS is Not Found! Please, Update User NAS.');
					redirect('user/profile/' . $id);
				}
			}
		}
		else {
			$this->session->set_flashdata('error', 'No User Found, Maybe Its Token User.');
			redirect('user/profile/' . $id);
		}
	}

	public function clearSession($id)
	{
		isPermitted('user_disable_net', [11, 12, 13], true);
		if (isset($id) && !empty($id)) {
			$errorMessages = [];
			$successMessages = [];
			$data = [];
			checkUserPermission($id);
			isUserAccessible($id);
			$userData = $this->main->getUserinfoByID($id)[0];
			$nasObj = $this->main->singleQuery('nas', ['id' => $userData->nasid]);
			if (($userData->connectiontype == 1) || ($userData->connectiontype == 2)) {
				if ($nasObj) {
					try {
						$disconnectRes = $this->radiusM->kickOutUsers($userData->username);

						if ($disconnectRes) {
							array_push($successMessages, 'User Successfully Disconnectd');
						}
						else {
							array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
						}
					}
					catch (Exception $e) {
						$errorMessage = 'Disconnect Error: ' . $e->getMessage();
						array_push($errorMessages, $errorMessage);
					}

					$radacctData['acctstoptime'] = date('Y-m-d H:i:s');
					$radacctData['acctterminatecause'] = 'User Deleted & Sesssion Stopped';
					$result = $this->main->singleUpdate('radacct', $radacctData, ['username' => $userData->username, 'acctstoptime' => NULL]);
					$this->session->set_flashdata('successMessages', $successMessages);
					$this->session->set_flashdata('errorMessages', $errorMessages);
					$this->main->insertActivity('User Connection Disconnected', $id);
					redirect('user/profile/' . $id);
				}
				else {
					$this->session->set_flashdata('error', 'User NAS is Not Found! Please, Update User NAS.');
					redirect('user/profile/' . $id);
				}
			}
		}
		else {
			$this->session->set_flashdata('error', 'No User Found, Maybe Its Token User.');
			redirect('user/profile/' . $id);
		}
	}

	public function userProfileDisable($id)
	{
		if (isset($id) && !empty($id)) {
			$errorMessages = [];
			$successMessages = [];
			$data = [];
			checkUserPermission($id);
			isUserAccessible($id);
			$userData = $this->main->getUserinfoByID($id)[0];
			$nasObj = $this->main->singleQuery('nas', ['id' => $userData->nasid]);
			if (($userData->connectiontype == 1) || ($userData->connectiontype == 2)) {
				if ($nasObj) {
					try {
						$disconnectRes = $this->radiusM->kickOutUsers($userData->username);

						if ($disconnectRes) {
							$radacctData['acctstoptime'] = date('Y-m-d H:i:s');
							$radacctData['acctterminatecause'] = 'User Deleted & Sesssion Stopped';
							$result = $this->main->singleUpdate('radacct', $radacctData, ['username' => $userData->username, 'acctstoptime' => NULL]);
							array_push($successMessages, 'User Successfully Disconnectd');
						}
						else {
							array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
						}
					}
					catch (Exception $e) {
						$errorMessage = 'Disconnect Error: ' . $e->getMessage();
						array_push($errorMessages, $errorMessage);
					}

					$this->main->singleUpdate('usersinfo', ['status' => 0], ['id' => $id]);
					$this->main->insertActivity('User Profile Disabled', $id);
					$this->session->set_flashdata('successMessages', $successMessages);
					$this->session->set_flashdata('errorMessages', $errorMessages);
					$this->main->insertActivity('User Connection Disconnected', $id);
					redirect('user/profile/' . $id);
				}
				else {
					$this->session->set_flashdata('error', 'User NAS is Not Found! Please, Update User NAS.');
					redirect('user/profile/' . $id);
				}
			}
		}
		else {
			$this->session->set_flashdata('error', 'No User Found, Maybe Its Token User.');
			redirect('user/profile/' . $id);
		}
	}

	public function userProfileEnable($id)
	{
		if (isset($id) && !empty($id)) {
			$errorMessages = [];
			$successMessages = [];
			$data = [];
			checkUserPermission($id);
			isUserAccessible($id);
			$userData = $this->main->getUserinfoByID($id)[0];
			if ((!isset($userData->renew_date) && !isset($userData->activation_date)) || (empty($userData->activation_date) && empty($userData->renew_date)) || (($userData->status == 1) && !checkAdminOrStaff())) {
				$this->session->set_flashdata('error', 'Insufficient Permission To Enable User Profile.');
				redirect('user/profile/' . $id);
			}

			$response = $this->main->singleUpdate('usersinfo', ['status' => 2], ['id' => $id]);
			$this->main->insertActivity('User Profile Enabled', $id);

			if ($response) {
				$this->session->set_flashdata('success', 'User Profile Succesfully Enabled/Activated.');
			}
			else {
				$this->session->set_flashdata('error', 'Oops! Something Went Wrong.');
			}

			redirect('user/profile/' . $id);
		}
		else {
			$this->session->set_flashdata('error', 'No User Found, Maybe Its Token User.');
			redirect('user/profile/' . $id);
		}
	}

	public function adddocument()
	{
		isPermitted('user_documents');
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		isUserAccessible($userID);
		$data = [];
		$data['userID'] = $userID;
		$data['user_type'] = 2;
		$data['added_by'] = $this->session->user_id;
		$data['filename'] = $this->input->post('filename', true);
		$data['document_type'] = $this->input->post('document_type', true);
		$data['datetime'] = date('Y-m-d H:i:s');
		$filePath = realpath(APPPATH . '../assets/user/doc');
		$config['upload_path'] = $filePath;
		$config['allowed_types'] = '*';
		$config['file_name'] = date('Ymd_his_') . rand(10, 99) . rand(10, 99) . rand(10, 99);
		$config['max_size'] = 5000;
		$this->load->library('upload', $config);

		if ($this->upload->do_upload('file')) {
			$uploadData = $this->upload->data();
			$data['file'] = $uploadData['file_name'];
			$data['file_type'] = $uploadData['file_ext'];
			$true = $this->main->singleInsert('documents', $data);
			$this->main->insertActivity('User Document Added', $userID);

			if ($true) {
				$this->session->set_flashdata('success', 'User Document Added Successfully');
				redirect('user/profile/' . $userID);
			}
			else {
				$this->session->set_flashdata('error', 'Opps, Somethig Went Wrong');
				redirect('user/profile/' . $userID);
			}
		}
		else {
			$uploadError = ['error' => $this->upload->display_errors()];
			$uploadErrorText = $uploadError['error'];
			$this->session->set_flashdata('error', $uploadErrorText);
			redirect('user/profile/' . $userID);
		}
	}

	public function deletedocument($id)
	{
		isPermitted('user_documents');
		$documentObj = $this->main->singleQuery('documents', ['documentID' => $id]);
		$userID = $documentObj[0]->userID;
		isUserAccessible($userID);
		$true = $this->main->singleDelete('documents', ['documentID' => $id]);

		if ($true) {
			$this->session->set_flashdata('success', 'User Document Deleted Successfully');
			redirect('user/profile/' . $userID);
		}
		else {
			$this->session->set_flashdata('error', 'Opps, Somethig Went Wrong');
			redirect('user/profile/' . $userID);
		}
	}

	public function addattribute()
	{
		isPermitted('user_radius_attributes');
		$data = [];
		$type = $this->input->post('type', true);
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		isUserAccessible($userID);
		$userObj = $this->main->singleQuery('usersinfo', ['id' => $userID]);
		$username = $userObj[0]->username;
		$data['username'] = $username;
		$data['attribute'] = $this->input->post('attribute', true);
		$data['op'] = $this->input->post('op', true);
		$data['value'] = $this->input->post('value', true);

		if ($type == 1) {
			$true = $this->main->singleInsert('radreply', $data);
		}
		else {
			$true = $this->main->singleInsert('radcheck', $data);
		}

		if ($true) {
			$this->session->set_flashdata('success', 'User Radius Attribute Added Successfully');
			redirect('user/profile/' . $userID);
		}
		else {
			$this->session->set_flashdata('error', 'Opps, Somethig Went Wrong');
			redirect('user/profile/' . $userID);
		}
	}

	public function deletereplyattribute($id)
	{
		isPermitted('user_radius_attributes');
		$radreplyObj = $this->main->singleQuery('radreply', ['id' => $id]);
		$username = $radreplyObj[0]->username;
		$userObj = $this->main->singleQuery('usersinfo', ['username' => $username]);
		$userID = $userObj[0]->id;
		$true = $this->main->singleDelete('radreply', ['id' => $id]);

		if ($true) {
			$this->session->set_flashdata('success', 'User Attribute Deleted Successfully');
			redirect('user/profile/' . $userID);
		}
		else {
			$this->session->set_flashdata('error', 'Opps, Somethig Went Wrong');
			redirect('user/profile/' . $userID);
		}
	}

	public function deletecheckattribute($id)
	{
		isPermitted('user_radius_attributes');
		$radreplyObj = $this->main->singleQuery('radcheck', ['id' => $id]);
		$username = $radreplyObj[0]->username;
		$userObj = $this->main->singleQuery('usersinfo', ['username' => $username]);
		$userID = $userObj[0]->id;
		$true = $this->main->singleDelete('radcheck', ['id' => $id]);

		if ($true) {
			$this->session->set_flashdata('success', 'User Attribute Deleted Successfully');
			redirect('user/profile/' . $userID);
		}
		else {
			$this->session->set_flashdata('error', 'Opps, Somethig Went Wrong');
			redirect('user/profile/' . $userID);
		}
	}

	public function totalDataUsage($id)
	{
		isPermitted('user_all_module', [11, 12, 13], true);

		if (getUser($id)) {
			$username = getUser($id)->username;
			$lastInvoice = $this->main->getUserLastInvoice($id);
			$this->db->flush_cache();
			if (isset($lastInvoice) && $lastInvoice) {
				$lastRenewed = $lastInvoice->createdate;
				$this->db->where('acctstarttime >=', date('Y-m-d', strtotime($lastRenewed)));
			}
			else {
				$this->db->where('month(acctstarttime)', date('m'));
				$this->db->where('year(acctstarttime)', date('Y'));
			}

			$this->db->select('username, acctinputoctets, acctoutputoctets');
			$this->db->order_by('acctoutputoctets', 'desc');
			$this->db->where('username', $username);
			$queryTotalUsage = $this->db->get('radacct')->result();
			$this->db->flush_cache();
			$totalUsageVolume = 0;
			$totalDownLoad = [];
			$totalUpLoad = [];

			foreach ($queryTotalUsage as $totalUsage) {
				$totalDownLoad[] = $totalUsage->acctinputoctets / 1024;
				$totalUpLoad[] = $totalUsage->acctoutputoctets / 1024;
			}

			$totalDownLoad = array_sum($totalDownLoad) / 1024 / 1024;
			$totalUpLoad = array_sum($totalUpLoad) / 1024 / 1024;
			return $totalUsageVolume = number_format($totalDownLoad + $totalUpLoad, 2);
		}
		else {
			return 0;
		}
	}

	public function realtimetxrx()
	{
		$nasid = $this->input->post('nasid', true);
		$userid = $this->input->post('userid', true);
		$user = getUser($userid);

		if ($user) {
			$username = $user->username;
			$profileLastVisit = $user->last_profile_visit_time;

			if ($this->settings->usagegraph == 1) {
				$this->db->order_by('id', 'DESC');
				$this->db->limit(1);
				$livegraphObj = $this->db->get_where('livegraph', ['username' => $username]);

				if (0 < $livegraphObj->num_rows()) {
					$stalesession = (int) $this->settings->stalesession;
					$uploadData = $livegraphObj->result()[0]->upload / $stalesession / 1024;
					$downloadData = $livegraphObj->result()[0]->download / $stalesession / 1024;
					$dataArray = [
						'data'  => ['upload' => $uploadData, 'download' => $downloadData],
						'error' => ''
					];
					echo json_encode($dataArray);
				}
				else {
					$error['error'] = 'No New Data Found.';
					echo json_encode($error);
				}
			}
			else if ($this->settings->usagegraph == 2) {
				if (strtotime('+ 15 minutes', strtotime($profileLastVisit)) <= time()) {
					$error['error'] = 'Live Graph Time Over, Refresh Page To See Live Graph Again';
					echo json_encode($error);
				}
				else {
					$nasObj = $this->main->singleQuery('nas', ['id' => $nasid]);

					if ($nasObj) {
						$nasAPI = $nasObj[0]->nasapi;

						if ($nasAPI == 1) {
							$routerObj = mkUtilByID($nasid);
							$client = mkClientByID($nasid);
							if ($routerObj && $client) {
								if (($user->connectiontype == 1) || ($user->connectiontype == 3)) {
									$interfaceName = '<pppoe-' . $username . '>';
									$request = new PEAR2\Net\RouterOS\Request('/interface/monitor-traffic');
									$request->setArgument('interface', $interfaceName);
									$request->setArgument('once', '');
									$txRxObj = $client->sendSync($request);
									$txValue = round($txRxObj->getProperty('tx-bits-per-second') / 1000 / 1000);
									$rxValue = round($txRxObj->getProperty('rx-bits-per-second') / 1000 / 1000);
									$txRxValuesArr = ['txvalue' => $txValue, 'rxvalue' => $rxValue, 'error' => ''];
									echo json_encode($txRxValuesArr);
								}
								else if (($user->connectiontype == 2) || ($user->connectiontype == 4)) {
									$queueSimple = $routerObj->setMenu('/queue simple')->get('<hotspot-' . $username . '>', 'rate');
									$rateArray = explode('/', $queueSimple);
									$txRxValuesArr = ['txvalue' => $rateArray[1] / 1024, 'rxvalue' => $rateArray[0] / 1024, 'error' => ''];
									echo json_encode($txRxValuesArr);
								}
								else if ($user->connectiontype == 5) {
								}
							}
							else {
								$error['error'] = 'Oops! Somethig Wrong with NAS. Make Sure its Active.';
								echo json_encode($error);
							}
						}
						else {
							$error['error'] = 'NAS API is Not Enable. Please, Make Sure NAS API Enable & Active.';
							echo json_encode($error);
						}
					}
					else {
						$error['error'] = 'Oops! Somethig Wrong with NAS. Please, Make Sure NAS API Enable & Active.';
						echo json_encode($error);
					}
				}
			}
		}
		else {
			$error['error'] = 'User Not Found';
			echo json_encode($error);
		}
	}

	public function validPackages()
	{
		$currentRole = currentSessionData()['role'];
		$currentRoleID = currentSessionData()['id'];
		if (($currentRole == 11) || ($currentRole == 12) || ($currentRole == 13)) {
			return $this->main->getJoinFPackagesByAdminID($currentRoleID);
		}
		else {
			return $this->main->getJoinAdminPackages();
		}
	}

	public function massdelete()
	{
		isPermitted('user_mass_delete');
		$resellerSettings = resellersSettings();
		if (checkReseller() && $resellerSettings && ($resellerSettings->resl_delete_user != 1)) {
			$this->session->set_flashdata('error', 'Insufficient Permission To Delete User.');
			redirect('user/all');
		}

		$errorMessages = [];
		$successMessages = [];
		$userIDs = $this->input->post('usersids', true);
		$userIDsArr = explode(',', $userIDs);
		$i = 0;

		for ($x = 0; $x < count($userIDsArr); $x++) {
			$id = $userIDsArr[$x];
			if ((10 < strlen($id)) || (strpos($id, ':') !== false)) {
				if (decryptInputPost($id)) {
					$id = decryptInputPost($id);
				}
				else {
					$id = NULL;
				}
			}
			if (isset($id) && !empty($id)) {
				$userData = $this->main->getUserinfoByID($id);

				if ($userData) {
					isUserAccessible($id);
					$userData = $userData[0];
					$createdby = $userData->createdby;
					$saleperson = $userData->saleperson;
					$username = $userData->username;
					$currentRoleID = currentSessionData()['id'];

					if (checkReseller()) {
						if ($currentRoleID == $saleperson) {
							try {
								$disconnectRes = $this->radiusM->kickOutUsers($username);

								if ($disconnectRes) {
									array_push($successMessages, 'User Successfully Disconnectd');
								}
								else {
									array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
								}
							}
							catch (Exception $e) {
								$errorMessage = 'Disconnect Error: ' . $e->getMessage();
								array_push($errorMessages, $errorMessage);
							}

							$num_rows = $this->db->get_where('radcheck', ['username' => $username])->num_rows();

							if (0 < $num_rows) {
								$this->db->where('username', $username);
								$delete4 = $this->db->delete('radcheck');
							}

							$this->db->flush_cache();
							$num_rows = $this->db->get_where('radreply', ['username' => $username])->num_rows();

							if (0 < $num_rows) {
								$this->db->where('username', $username);
								$delete6 = $this->db->delete('radreply');
							}

							$this->db->flush_cache();
							$num_rows = $this->db->get_where('radusergroup', ['username' => $username])->num_rows();

							if (0 < $num_rows) {
								$this->db->where('username', $username);
								$delete7 = $this->db->delete('radusergroup');
							}

							$this->db->flush_cache();
							$this->db->where('id', $id);
							$delete11 = $this->db->delete('usersinfo');
							$this->db->flush_cache();
							$this->main->insertActivity('User Deleted', $id);

							if (isset($delete11)) {
								$i++;
							}
						}

						continue;
					}

					try {
						$disconnectRes = $this->radiusM->kickOutUsers($username);

						if ($disconnectRes) {
							array_push($successMessages, 'User Successfully Disconnectd');
						}
						else {
							array_push($errorMessages, 'Failed To Disconnect! Manual Disconnect Action Required.');
						}
					}
					catch (Exception $e) {
						$errorMessage = 'Disconnect Error: ' . $e->getMessage();
						array_push($errorMessages, $errorMessage);
					}

					$num_rows = $this->db->get_where('radcheck', ['username' => $username])->num_rows();

					if (0 < $num_rows) {
						$this->db->where('username', $username);
						$delete4 = $this->db->delete('radcheck');
					}

					$this->db->flush_cache();
					$num_rows = $this->db->get_where('radreply', ['username' => $username])->num_rows();

					if (0 < $num_rows) {
						$this->db->where('username', $username);
						$delete6 = $this->db->delete('radreply');
					}

					$this->db->flush_cache();
					$num_rows = $this->db->get_where('radusergroup', ['username' => $username])->num_rows();

					if (0 < $num_rows) {
						$this->db->where('username', $username);
						$delete7 = $this->db->delete('radusergroup');
					}

					$this->db->flush_cache();
					$this->db->where('id', $id);
					$delete11 = $this->db->delete('usersinfo');
					$this->db->flush_cache();
					$this->main->insertActivity('User Deleted', $id);

					if (isset($delete11)) {
						$i++;
					}
				}
			}
		}

		$this->session->set_flashdata('successMessages', $successMessages);
		$this->session->set_flashdata('errorMessages', $errorMessages);
		$this->session->set_flashdata('success', $i . ' Users Successfully Deleted');
		redirect('user/all');
	}

	public function clearstalesession()
	{
		isPermitted('user_all_module', [11, 12, 13], true);

		if (checkFranchise()) {
			$onlineusers = $this->franchiseM->staleSessionUsers();
		}
		else if (checkDealer()) {
			$onlineusers = $this->dealerM->staleSessionUsers();
		}
		else if (checkSubdealer()) {
			$onlineusers = $this->subdealerM->staleSessionUsers();
		}
		else {
			$onlineusers = $this->adminM->staleSessionUsers();
		}

		if ($onlineusers) {
			$i = 0;

			foreach ($onlineusers as $user) {
				$username = $user->username;
				$currentTime = date('Y-m-d H:i:s');
				$userData = $this->main->getUserByUsername($username);

				if ($userData) {
					$last_interim_update = $userData->last_interim_update;
					if (isset($last_interim_update) && !empty($last_interim_update)) {
						$currentMinusTime = date('Y-m-d H:i:s', strtotime('-' . $this->settings->stalesession . ' minutes'));

						if (strtotime($last_interim_update) <= strtotime($currentMinusTime)) {
							$data = [];
							$data['acctstoptime'] = $currentTime;
							$data['acctterminatecause'] = 'Stale Session Clear';
							$result = $this->main->singleUpdate('radacct', $data, ['username' => $username, 'acctstoptime' => NULL]);

							if ($result) {
								$i++;
							}
						}
					}
					else {
						$userName = $user->username;
						$acctUpdateTime = $user->acctupdatetime;
						$currentTime = date('Y-m-d H:i:s');
						$acctUpdateTime = date('Y-m-d H:i:s', strtotime('+' . $this->settings->stalesession . ' minutes', strtotime($acctUpdateTime)));

						if (strtotime($acctUpdateTime) <= strtotime($currentTime)) {
							$data = [];
							$data['acctstoptime'] = $currentTime;
							$data['acctterminatecause'] = 'Stale Session Clear';
							$result = $this->main->singleUpdate('radacct', $data, ['username' => $userName, 'acctstoptime' => NULL]);

							if ($result) {
								$i++;
							}
						}
					}
				}
				else {
					$userName = $user->username;
					$acctUpdateTime = $user->acctupdatetime;
					$currentTime = date('Y-m-d H:i:s');
					$acctUpdateTime = date('Y-m-d H:i:s', strtotime('+' . $this->settings->stalesession . ' minutes', strtotime($acctUpdateTime)));

					if (strtotime($acctUpdateTime) <= strtotime($currentTime)) {
						$data = [];
						$data['acctstoptime'] = $currentTime;
						$data['acctterminatecause'] = 'Stale Session Clear';
						$result = $this->main->singleUpdate('radacct', $data, ['username' => $userName, 'acctstoptime' => NULL]);

						if ($result) {
							$i++;
						}
					}
				}
			}
			if (isset($i) && (0 < $i)) {
				$this->session->set_flashdata('success', 'Removed ' . $i . ' Stale Sessions.');
				redirect('user/stales/');
			}
			else {
				$this->session->set_flashdata('error', 'Removed 0 Stale Sessions.');
				redirect('user/stales/');
			}
		}
		else {
			$this->session->set_flashdata('error', 'No Stale Sessions Found.');
			redirect('user/stales/');
		}
	}

	public function checkusername()
	{
		$csrf = $this->security->get_csrf_hash();
		$adminUsername = $this->session->userdata('user_username');

		if (checkReseller()) {
			if (strpos($this->input->post('username', true), $adminUsername . $this->settings->prefix_characters) !== false) {
				$username = preg_replace('/\\s+/', '', $this->input->post('username', true));
			}
			else if ($this->settings->userprefix == 1) {
				$username = $adminUsername . $this->settings->prefix_characters . preg_replace('/\\s+/', '', $this->input->post('username', true));
			}
			else {
				$username = preg_replace('/\\s+/', '', $this->input->post('username', true));
			}

			if (!empty($username)) {
				$query = $this->db->get_where('usersinfo', ['username' => $username]);

				if (0 < $query->num_rows()) {
					$status = 1;
				}
				else {
					$status = 0;
				}
			}
			else {
				$status = 0;
			}

			echo json_encode(['status' => $status, 'csrf' => $csrf]);
		}
		else {
			$username = preg_replace('/\\s+/', '', $this->input->post('username', true));

			if (!empty($username)) {
				$query = $this->db->get_where('usersinfo', ['username' => $username]);

				if (0 < $query->num_rows()) {
					$status = 1;
				}
				else {
					$status = 0;
				}
			}
			else {
				$status = 0;
			}

			echo json_encode(['status' => $status, 'csrf' => $csrf]);
		}
	}

	public function getAreaByAjax()
	{
		$city = $this->input->post('city', true);
		$querySubarea = $this->db->get_where('types', ['type' => 'area', 'type2' => $city]);
		$html = '';
		$html .= '<option value="">Select Area</option>';

		if (0 < $querySubarea->num_rows()) {
			foreach ($querySubarea->result() as $row) {
				$html .= '<option value="' . $row->data . '">' . $row->description . '</option>';
			}
		}

		if (0 < $querySubarea->num_rows()) {
			echo json_encode($html);
		}
		else {
			echo json_encode(0);
		}
	}

	public function getSubAreaByAjax()
	{
		$area = $this->input->post('area', true);
		$querySubarea = $this->db->get_where('types', ['type' => 'subarea', 'type2' => $area]);
		$html = '';
		$html .= '<option value="">Select Subarea</option>';

		if (0 < $querySubarea->num_rows()) {
			foreach ($querySubarea->result() as $row) {
				$html .= '<option value="' . $row->data . '">' . $row->description . '</option>';
			}
		}

		if (0 < $querySubarea->num_rows()) {
			echo json_encode($html);
		}
		else {
			echo json_encode(0);
		}
	}

	public function getActivityLog()
	{
		$requestData = $_REQUEST;
		if (!isset($requestData) || empty($requestData)) {
			$this->session->set_flashdata('error', 'Forbidden! You Can\'t Direct Access.');
			redirect('home');
		}

		$columns = ['id'];
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			echo json_encode('Plz, Don\\t Try To Teamper Data.');
		}

		$from = $this->input->post('from', true);
		$to = $this->input->post('to', true);
		if (isset($from) && isset($to) && (empty($from) || empty($to))) {
			$from = date('Y-m-d');
			$to = date('Y-m-d');
		}

		if (!empty($requestData['search']['value'])) {
			$this->db->group_start();
			$this->db->like('datetime', $requestData['search']['value']);
			$this->db->or_like('userid', $requestData['search']['value']);
			$this->db->or_like('activity', $requestData['search']['value']);
			$this->db->or_like('stationip', $requestData['search']['value']);
			$this->db->group_end();
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('activitylog', ['userid' => $userID]);
			$totalData = $query->num_rows();
			$totalFiltered = $totalData;
			$this->db->order_by('id', $requestData['order'][0]['dir']);
			$this->db->limit($requestData['length'], $requestData['start']);
			$this->db->group_start();
			$this->db->like('datetime', $requestData['search']['value']);
			$this->db->or_like('userid', $requestData['search']['value']);
			$this->db->or_like('activity', $requestData['search']['value']);
			$this->db->or_like('stationip', $requestData['search']['value']);
			$this->db->group_end();
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('activitylog', ['userid' => $userID]);
			$this->db->flush_cache();
		}
		else {
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('activitylog', ['userid' => $userID]);
			$totalData = $query->num_rows();
			$totalFiltered = $totalData;
			$this->db->order_by('id', $requestData['order'][0]['dir']);
			$this->db->limit($requestData['length'], $requestData['start']);
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('activitylog', ['userid' => $userID]);
			$this->db->flush_cache();
		}

		$data = [];
		if (isset($query) && !empty($query)) {
			foreach ($query->result() as $row) {
				$adminData = getAdminByID($row->adminid);
				$nestedData = [];
				$nestedData[] = $row->datetime;
				$nestedData[] = ($adminData ? $adminData->username : 'N/A');
				$nestedData[] = $row->activity;
				$nestedData[] = $row->stationip;
				$data[] = $nestedData;
			}
		}
		else {
			$nestedData = [];
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$data[] = $nestedData;
		}

		$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) $totalData, 'recordsFiltered' => (int) $totalFiltered, 'data' => $data, 'csrf' => $this->security->get_csrf_hash()];
		echo json_encode($json_data);
	}

	public function getLedgerReport()
	{
		$requestData = $_REQUEST;
		if (!isset($requestData) || empty($requestData)) {
			$this->session->set_flashdata('error', 'Forbidden! You Can\'t Direct Access.');
			redirect('home');
		}

		$columns = ['id'];
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			echo json_encode('Plz, Don\\t Try To Teamper Data.');
		}

		$from = $this->input->post('from', true);
		$to = $this->input->post('to', true);
		if (isset($from) && isset($to) && (empty($from) || empty($to))) {
			$from = date('Y-m-d');
			$to = date('Y-m-d');
		}

		if (!empty($requestData['search']['value'])) {
			$this->db->group_start();
			$this->db->like('datetime', $requestData['search']['value']);
			$this->db->or_like('transid', $requestData['search']['value']);
			$this->db->or_like('amount', $requestData['search']['value']);
			$this->db->or_like('balance', $requestData['search']['value']);
			$this->db->group_end();
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('ledger', ['userid' => $userID, 'usertype' => 1]);
			$totalData = $query->num_rows();
			$totalFiltered = $totalData;
			$this->db->order_by('lgid', $requestData['order'][0]['dir']);
			$this->db->limit($requestData['length'], $requestData['start']);
			$this->db->group_start();
			$this->db->like('datetime', $requestData['search']['value']);
			$this->db->or_like('transid', $requestData['search']['value']);
			$this->db->or_like('amount', $requestData['search']['value']);
			$this->db->or_like('balance', $requestData['search']['value']);
			$this->db->group_end();
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('ledger', ['userid' => $userID, 'usertype' => 1]);
			$this->db->flush_cache();
		}
		else {
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('ledger', ['userid' => $userID, 'usertype' => 1]);
			$totalData = $query->num_rows();
			$totalFiltered = $totalData;
			$this->db->order_by('lgid', $requestData['order'][0]['dir']);
			$this->db->limit($requestData['length'], $requestData['start']);
			$this->db->where('datetime >=', date('Y-m-d 00:00:00', strtotime($from)));
			$this->db->where('datetime <=', date('Y-m-d 23:59:59', strtotime($to)));
			$query = $this->db->get_where('ledger', ['userid' => $userID, 'usertype' => 1]);
			$this->db->flush_cache();
		}

		$data = [];
		if (isset($query) && !empty($query)) {
			foreach ($query->result() as $row) {
				if ($row->usertype == 1) {
					$resellerLinkColor = 'default';
					$userLinkColor = 'success';
				}
				else {
					$resellerLinkColor = 'success';
					$userLinkColor = 'default';
				}

				$adminProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                            <span class="label label-default">N/A</span>' . "\n" . '                        </a>';

				if (getAdminByID($row->adminid)) {
					$payerRole = getAdminByID($row->adminid)->role;

					if ($payerRole == 11) {
						$adminProfile = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'franchise/profile/' . $row->adminid . '" target="_blank">' . "\n" . '                                <span class="label label-' . $resellerLinkColor . '">' . getAdminByID($row->adminid)->username . ' (FR)</span>' . "\n" . '                            </a>';
					}
					else if ($payerRole == 12) {
						$adminProfile = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'dealer/profile/' . $row->adminid . '" target="_blank">' . "\n" . '                                <span class="label label-' . $resellerLinkColor . '">' . getAdminByID($row->adminid)->username . ' (DR)</span>' . "\n" . '                            </a>';
					}
					else if ($payerRole == 13) {
						'<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'subdealer/profile/' . $row->adminid . '" target="_blank">' . "\n" . '                                <span class="label label-' . $resellerLinkColor . '">' . getAdminByID($row->adminid)->username . ' (SDR)</span>' . "\n" . '                            </a>';
					}
					else {
						$adminProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                                <span class="label label-' . $resellerLinkColor . '">' . getAdminByID($row->adminid)->username . ' </span>' . "\n" . '                            </a>';
					}
				}

				$rowStatus = true;

				if (checkFranchise()) {
					if (getAdminByID($row->adminid)) {
						$upperAdminRole = getAdminByID($row->adminid)->role;

						if (11 <= $upperAdminRole) {
							$rowStatus = true;
						}
						else {
							$rowStatus = false;
						}
					}
				}
				else if (checkDealer()) {
					if (getAdminByID($row->adminid)) {
						$upperAdminRole = getAdminByID($row->adminid)->role;

						if (12 <= $upperAdminRole) {
							$rowStatus = true;
						}
						else {
							$rowStatus = false;
						}
					}
				}
				else if (checkSubdealer()) {
					if (getAdminByID($row->adminid)) {
						$upperAdminRole = getAdminByID($row->adminid)->role;

						if (13 <= $upperAdminRole) {
							$rowStatus = true;
						}
						else {
							$rowStatus = false;
						}
					}
				}

				if ($row->usertype == 1) {
					if (getUserInfo($row->userid)) {
						$userProfile = '<a data-toggle="tooltip" title="View Profile" target="_blank" href="' . base_url() . 'user/profile/' . $row->userid . '">' . "\n" . '                            <span class="label label-' . $userLinkColor . '">' . getUserInfo($row->userid)->username . '</span></a>';
					}
					else {
						$userProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                            <span class="label label-default">N/A</span></a>';
					}
				}
				else if ($row->userid_type == 1) {
					if (getUserInfo($row->userid)) {
						$userProfile = '<a data-toggle="tooltip" title="View Profile" target="_blank" href="' . base_url() . 'user/profile/' . $row->userid . '">' . "\n" . '                                                <span class="label label-' . $userLinkColor . '">' . getUserInfo($row->userid)->username . '</span></a>';
					}
					else {
						$userProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                                                <span class="label label-default">N/A</span></a>';
					}
				}
				else if (getAdminByID($row->adminid)) {
					$resellerRoleData = getAdminByID($row->userid);

					if ($resellerRoleData) {
						if (array_key_exists('role', $resellerRoleData)) {
							$payerRole = $resellerRoleData->role;

							if ($payerRole == 11) {
								$userProfile = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'franchise/profile/' . $row->userid . '/" target="_blank">' . "\n" . '                                                    <span class="label label-default">' . $resellerRoleData->username . '(FR)</span></a>';
							}
							else if ($payerRole == 12) {
								$userProfile = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'dealer/profile/' . $row->userid . '/" target="_blank">' . "\n" . '                                                    <span class="label label-default">' . $resellerRoleData->username . '(DR)</span></a>';
							}
							else if ($payerRole == 13) {
								$userProfile = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'subdealer/profile/' . $row->userid . '/" target="_blank">' . "\n" . '                                                    <span class="label label-default">' . $resellerRoleData->username . '(SDR)</span></a>';
							}
							else {
								$userProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                                                    <span class="label label-default">' . $resellerRoleData->username . '</span></a>';
							}
						}
						else {
							$userProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                                                    <span class="label label-default">N/A</span></a>';
						}
					}
					else {
						$userProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                                                    <span class="label label-default">N/A</span></a>';
					}
				}
				else {
					$userProfile = '<a data-toggle="tooltip" title="View Profile" href="#" target="_blank">' . "\n" . '                                                    <span class="label label-default">N/A</span></a>';
				}

				if ($row->lgtype == 1) {
					if ($row->amount < 0) {
						$ledgerType = '<span class="label label-warning">Payment</span>';
					}
					else {
						$ledgerType = '<span class="label label-success">Payment</span>';
					}
				}
				else if ($row->lgtype == 2) {
					$ledgerType = '<span class="label label-danger">Withdraw</span>';
				}
				else if ($row->lgtype == 3) {
					$ledgerType = '<span class="label label-warning">Activation</span>';
				}
				else if ($row->lgtype == 4) {
					$ledgerType = '<span class="label label-success">Profit</span>';
				}
				else if ($row->lgtype == 5) {
					$invoiceID = (invoiceByTrxID($row->transid) ? invoiceByTrxID($row->transid)->invoiceID : '');

					if (!empty($invoiceID)) {
						$ledgerType = '<a data-toggle="tooltip" title="View Invoice" target="_blank" href="' . base_url() . 'accounting/invoice/view/' . $invoiceID . '"><span class="label label-warning">Invoice</span></a>';
					}
					else {
						$ledgerType = '<span class="label label-warning">Invoice</span>';
					}
				}
				else if ($row->lgtype == 6) {
					$ledgerType = '<span class="label label-warning">Token</span>';
				}
				else {
					$ledgerType = '<span class="label label-default">Unknown</span>';
				}

				$nestedData = [];
				$nestedData[] = $row->datetime;
				$nestedData[] = $row->transid;
				$nestedData[] = $ledgerType;
				$nestedData[] = $adminProfile;
				$nestedData[] = $userProfile;
				$nestedData[] = number_format($row->amount, 2);
				$nestedData[] = number_format($row->balance, 2);
				$data[] = $nestedData;
			}
		}
		else {
			$nestedData = [];
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$nestedData[] = '';
			$data[] = $nestedData;
		}

		$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) $totalData, 'recordsFiltered' => (int) $totalFiltered, 'data' => $data, 'csrf' => $this->security->get_csrf_hash()];
		echo json_encode($json_data);
	}

	public function addnote()
	{
		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		isUserAccessible($userID);
		$currentRoleID = $this->session->user_id;
		$data = [];
		$data['user_id'] = $userID;
		$data['description'] = $this->input->post('description', true);
		$data['added_by'] = $this->session->user_id;
		$globalNoteData = $this->input->post('global_note', true);
		if (isset($globalNoteData) && !empty($globalNoteData)) {
			$globalUpdateRes = $this->main->singleUpdate('usersinfo', ['global_note' => $globalNoteData], ['id' => $userID]);
		}

		$checkingQuery = $this->main->singleQuery('notes', ['user_id' => $userID, 'added_by' => $currentRoleID]);
		if ($checkingQuery && (0 < count($checkingQuery))) {
			if ($checkingQuery && (0 < count($checkingQuery))) {
				$data['updated_at'] = date('Y-m-d H:i:s');
				$privateUpdateRes = $this->main->singleUpdate('notes', $data, ['user_id' => $userID, 'added_by' => $currentRoleID]);
			}
		}
		else {
			$data['created_at'] = date('Y-m-d H:i:s');
			$insertResponse = $this->main->singleInsert('notes', $data);
		}
		if (($globalUpdateRes || $privateUpdateRes) && $insertResponse) {
			$this->session->set_flashdata('success', 'Note Successfully Addedd & Updated.');
		}
		else if ($globalUpdateRes || $privateUpdateRes) {
			$this->session->set_flashdata('success', 'Note Successfully Updated.');
		}
		else if ($insertResponse) {
			$this->session->set_flashdata('success', 'Note Successfully Added.');
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Went Wrong.');
		}

		redirect('user/profile/' . $userID);
	}

	public function smsSend()
	{
		$fail = [];
		$success = [];

		if (!if_SMSEnable()) {
			$this->session->set_flashdata('error', 'Oops! System SMS Status Disable.');
			redirect('home');
		}

		$userID = $this->input->post('userID', true);

		if (decryptInputPost($userID)) {
			$userID = decryptInputPost($userID);
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Something Wrong');
			redirect('home');
		}

		if (checkReseller()) {
			$this->session->set_flashdata('error', 'Oops! You are not eligible to send SMS');
			redirect('home');
		}

		$userData = $this->main->getUserinfoByID($userID);

		if (!$userData) {
			$this->session->set_flashdata('error', 'Oops! User Not Found!');
			redirect('home');
		}

		$userData = $userData[0];

		if (!getUserSMSStatus($userID)) {
			$this->session->set_flashdata('error', 'Oops! User Does Not Have SMS Permission.');
			redirect('home');
		}

		if (!getUserSalespersonSms($userID, $userData->saleperson)) {
			$this->session->set_flashdata('error', 'Oops! Reseller Does Not Have SMS Permission.');
			redirect('home');
		}

		$numberType = $this->input->post('number_type', true);
		$formNumber = $this->input->post('number', true);
		$textType = $this->input->post('text_type', true);
		$templateID = $this->input->post('template', true);
		$formMessage = strip_tags($this->input->post('message', true));
		if (isset($textType) && !empty($textType) && ($textType == 1)) {
			$smsTemplateData = getSMSAlert($templateID);

			if ($smsTemplateData) {
				$smsText = $smsTemplateData->template;
				$userCurrentBalance = getLGBLByUserID($userID);

				if ($userData->status == 1) {
					$status = 'Pending';
				}
				else if ($userData->status == 2) {
					$status = 'Active';
				}
				else {
					$status = 'Disable';
				}
				if (($templateID == 3) || ($templateID == 8)) {
					$smsText = str_replace('{password}', $userData->password, $smsText);
				}

				if ($templateID == 11) {
					$smsText = str_replace('{amount}', $userCurrentBalance, $smsText);
				}

				if ($templateID == 12) {
					$smsText = str_replace('{status}', $status, $smsText);
				}

				$smsText = getFinalSMSText($smsText, 2, $userID);
			}
			else {
				$this->session->set_flashdata('error', 'Oops! SMS Template Not Found.');
				redirect('home');
			}
		}
		else {
			$smsText = $formMessage;
		}
		if (isset($numberType) && !empty($numberType) && ($numberType == 1)) {
			$destinationNumber = $userData->mobile;
			$smsResponse = sendSMS($destinationNumber, $smsText);
			$smsDeliveryData = ['smsAlert' => $templateID, 'destination' => $destinationNumber, 'message' => $smsText, 'userID' => $userID, 'adminID' => 0];
			$responseSmsDelivery = $this->smsM->insertDelivery($smsResponse, $smsDeliveryData);
			$this->session->set_flashdata('success', ' Successfully SMS Template Send');
			redirect('user/profile/' . $userID);
		}
		else if (isset($formNumber) && !empty($formNumber)) {
			$customNumbers = explode(',', $formNumber);
			if (isset($customNumbers) && is_array($customNumbers) && (0 < count($customNumbers))) {
				for ($x = 0; $x < count($customNumbers); $x++) {
					$destinationNumber = $customNumbers[$x];
					$smsResponse = sendSMS($destinationNumber, $smsText);

					if ($smsResponse['status']) {
						$success[] = $smsResponse['status'];
					}
					else {
						$fail[] = $smsResponse['status'];
					}

					$smsDeliveryData = ['smsAlert' => $templateID, 'destination' => $destinationNumber, 'message' => $smsText, 'userID' => $userID, 'adminID' => 0];
					$responseSmsDelivery = $this->smsM->insertDelivery($smsResponse, $smsDeliveryData);
				}

				$this->session->set_flashdata('success', count($success) . ' SMS Successfully Send & ' . count($fail) . ' SMS Failed.');
				redirect('user/profile/' . $userID);
			}
			else {
				$this->session->set_flashdata('error', 'Oops! Invalid Mobile Number.');
				redirect('home');
			}
		}
		else {
			$this->session->set_flashdata('error', 'Oops! Invalid Mobile Number.');
			redirect('home');
		}

		redirect('user/profile/' . $userID);
	}

	public function getServerSideusers()
	{
		$requestData = $_REQUEST;
		if (!isset($requestData) || empty($requestData)) {
			$this->session->set_flashdata('error', 'Forbidden! You Can\'t Direct Access.');
			redirect('home');
		}

		$filterType = $this->input->post('filterType', true);
		$filterExpiring = $this->input->post('filterExpiring', true);
		$filterPackage = $this->input->post('filterPackage', true);
		$filterSalesperson = $this->input->post('filterSalesperson', true);
		$filterCity = $this->input->post('filterCity', true);
		$filterArea = $this->input->post('filterArea', true);
		$filterSubarea = $this->input->post('filterSubarea', true);
		$filterNas = $this->input->post('filterNas', true);
		$filterJoinFrom = $this->input->post('filterJoinFrom', true);
		$filterJoinTo = $this->input->post('filterJoinTo', true);
		$filterActivationFrom = $this->input->post('filterActivationFrom', true);
		$filterActivationTo = $this->input->post('filterActivationTo', true);
		$columns = ['id', 'username', 'package', 'mobile', 'saleperson', 'connectiontype'];

		if (checkFranchise()) {
			$users = $this->franchiseM->getUsersByFranchise();
		}
		else if (checkDealer()) {
			$users = $this->main->getUsersByDealer();
		}
		else if (checkSubdealer()) {
			$users = $this->main->getUsersBySubdealer();
		}
		else {
			$users = $this->main->getAllUsersinfo();
		}

		if ($users) {
			$userIDs = [];
			$userNames = [];

			foreach ($users as $user) {
				$userIDs[] = $user->id;
				$userNames[] = $user->username;
			}

			$totalData = count($users);
			$totalFiltered = $totalData;
			$filterData = [];
			$filterData['filterType'] = $filterType;
			$filterData['filterPackage'] = $filterPackage;
			$filterData['filterSalesperson'] = $filterSalesperson;
			$filterData['filterExpiring'] = $filterExpiring;
			$filterData['filterCity'] = $filterCity;
			$filterData['filterArea'] = $filterArea;
			$filterData['filterSubarea'] = $filterSubarea;
			$filterData['userIDs'] = $userIDs;
			$filterData['filterNas'] = $filterNas;
			$filterData['filterJoinFrom'] = $filterJoinFrom;
			$filterData['filterJoinTo'] = $filterJoinTo;
			$filterData['filterActivationFrom'] = $filterActivationFrom;
			$filterData['filterActivationTo'] = $filterActivationTo;

			if (!empty($requestData['search']['value'])) {
				if (0 < count($userIDs)) {
					$this->db->group_start();
					$ids_chunk = array_chunk($userIDs, 25);

					foreach ($ids_chunk as $ids) {
						$this->db->or_where_in('id', $ids);
					}

					$this->db->group_end();
					$this->db->group_start();
					$this->db->like('id', $requestData['search']['value']);
					$this->db->or_like('name', $requestData['search']['value']);
					$this->db->or_like('username', $requestData['search']['value']);
					$this->db->or_like('mobile', $requestData['search']['value']);
					$this->db->or_like('phone', $requestData['search']['value']);
					$this->db->or_like('nic', $requestData['search']['value']);
					$this->db->or_like('email', $requestData['search']['value']);
					$this->db->or_like('address', $requestData['search']['value']);
					$this->db->group_end();
					$query = $this->db->get('usersinfo');
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('id', $requestData['search']['value']);
					$this->db->or_like('name', $requestData['search']['value']);
					$this->db->or_like('username', $requestData['search']['value']);
					$this->db->or_like('mobile', $requestData['search']['value']);
					$this->db->or_like('phone', $requestData['search']['value']);
					$this->db->or_like('nic', $requestData['search']['value']);
					$this->db->or_like('email', $requestData['search']['value']);
					$this->db->or_like('address', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$ids_chunk = array_chunk($userIDs, 25);

					foreach ($ids_chunk as $ids) {
						$this->db->or_where_in('id', $ids);
					}

					$this->db->group_end();
					$query = $this->db->get('usersinfo');
					$this->db->flush_cache();
				}
			}
			else if ($filterType == 1) {
				$response = $this->userM->generalUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 2) {
				$filterData['usernames'] = $userNames;
				$response = $this->userM->activeUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 4) {
				$filterData['usernames'] = $userNames;
				$response = $this->userM->disabledUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 3) {
				$response = $this->userM->expiredUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 5) {
				$filterData['usernames'] = $userNames;
				$response = $this->userM->problematicUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 6) {
				$response = $this->userM->onlineUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 7) {
				$filterData['usernames'] = $userNames;
				$response = $this->userM->offlineUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 8) {
				$response = $this->userM->staleUsersFilterModal($filterData, $requestData);
				$totalFiltered = $response['totalFiltered'];
				$query = $response['query'];
			}
			else if ($filterType == 9) {
				if ((0 < count($userIDs)) && (isset($filterJoinFrom) && isset($filterJoinTo)) && !empty($filterJoinFrom) && !empty($filterJoinTo)) {
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$ids_chunk = array_chunk($userIDs, 25);

					foreach ($ids_chunk as $ids) {
						$this->db->or_where_in('id', $ids);
					}

					$this->db->group_end();
					if (isset($filterJoinFrom) && isset($filterJoinTo) && !empty($filterJoinFrom) && !empty($filterJoinTo)) {
						$this->db->where('joindate >=', date('Y-m-d 00:00:00', strtotime($filterJoinFrom)));
						$this->db->where('joindate <=', date('Y-m-d 23:59:59', strtotime($filterJoinTo)));
					}

					$query = $this->db->get('usersinfo');
					$this->db->flush_cache();
				}
			}
			else if (0 < count($userIDs)) {
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$ids_chunk = array_chunk($userIDs, 25);

				foreach ($ids_chunk as $ids) {
					$this->db->or_where_in('id', $ids);
				}

				$this->db->group_end();
				$query = $this->db->get('usersinfo');
				$this->db->flush_cache();
			}
			if (isset($query) && !empty($query)) {
				$data = [];
				$totalOffline = 0;

				foreach ($query->result() as $row) {
					$userID = $row->id;
					if (isset($userID) && !empty($userID)) {
						$onlineStatusCheck = getConnectStatus($row->id);

						if (0 < getLGBLByUserID($row->id)) {
							$userBalance = '<span data-toggle="tooltip" title="User Credit Balance" class="label label-primary">' . number_format(getLGBLByUserID($row->id), 2) . '</span></td>';
						}
						else {
							$userBalance = '<span data-toggle="tooltip" title="User Due Balance" class="label label-warning">' . number_format(getLGBLByUserID($row->id), 2) . '</span></td>';
						}

						if ($row->connectiontype == 1) {
							$connectionType = '<span data-toggle="tooltip" title="Radius PPPoE" class="label label-primary">PPPoE</span>';
						}
						else if ($row->connectiontype == 2) {
							$connectionType = '<span data-toggle="tooltip" title="Radius Hotspot" class="label label-primary">Hotspot</span>';
						}
						else if ($row->connectiontype == 3) {
							$connectionType = '<span data-toggle="tooltip" title="API PPPoE" class="label label-info">PPPoE</span>';
						}
						else if ($row->connectiontype == 4) {
							$connectionType = '<span data-toggle="tooltip" title="API Hotspot" class="label label-info">Hotspot</span>';
						}
						else if ($row->connectiontype == 5) {
							$connectionType = '<span data-toggle="tooltip" title="API Static IP" class="label label-info">Static IP</span>';
						}
						else {
							$connectionType = '<span data-toggle="tooltip" title="Nothing Found" class="label label-default">N/A</span>';
						}

						if ($onlineStatusCheck == 1) {
							$onlineStatus = '<span class="label label-success">Online</span>';
						}
						else {
							$onlineStatus = '<span class="label label-default">Offline</span>';
						}

						if ($onlineStatusCheck == 1) {
							$onlineData = getRadacctByUsername($row->username);

							if ($onlineData) {
								$onlineUptime = '' . date('Y-m-d H:i:s', strtotime($onlineData->acctstarttime)) . '';
								$onlineDowntime = '' . date('Y-m-d H:i:s', strtotime($onlineData->acctstoptime)) . '';
							}
							else {
								$onlineUptime = 'N/A';
								$onlineDowntime = 'N/A';
							}
						}
						else {
							$onlineData = getRadacctByUsername($row->username, [
								'username'     => $row->username,
								'limit'        => [1],
								'order_by'     => ['radacctid', 'desc'],
								'acctstoptime' => 'offline'
							]);

							if ($onlineData) {
								$onlineUptime = date('Y-m-d H:i:s', strtotime($onlineData->acctstarttime));
								$onlineDowntime = date('Y-m-d H:i:s', strtotime($onlineData->acctstoptime));
							}
							else {
								$onlineUptime = 'N/A';
								$onlineDowntime = 'N/A';
							}
						}

						$ipPool = '';

						if (getPackageByID($user->package)) {
							if (getRadcheckByUsername($user->username)) {
								$expireTime = getRadcheckByUsername($user->username)->value;

								if (strtotime($expireTime) <= strtotime(date('d M Y H:i:s'))) {
									if (!empty(getPackageByID($user->package)->expirepool)) {
										$ipPool = getPackageByID($user->package)->expirepool;
									}
								}
								else if (!empty(getPackageByID($user->package)->pool)) {
									$ipPool = getPackageByID($user->package)->pool;
								}
							}
						}

						if (getPackageByID($user->package)) {
							$groupname = getPackageByID($user->package)->groupname;
							$policy = (0 < countRow2('radusergroup', 'groupname', $groupname, 'username', $user->username) ? getPackageByID($user->package)->groupname : 'N/A');
						}
						else {
							$policy = 'N/A';
						}

						$usageData = number_format($row->qt_used / 1024 / 1024 / 1024, 2);
						$sessionTime = (int) $row->qt_session;
						$usageTime = sprintf('%dd %02dh %02dm %02ds', $sessionTime / 86400, ($sessionTime / 3600) % 24, ($sessionTime / 60) % 60, $sessionTime % 60);
						$userStatusData = getTypesByData($row->status, 'userstatus');

						if ($userStatusData) {
							if ($userStatusData->description == 'Registered') {
								$expiryStatus = '<span class="label label-default">Registered</span>';
							}
							else if ($userStatusData->description == 'Active') {
								if (getRadcheckByUsername($row->username)) {
									$expireTime = getRadcheckByUsername($row->username)->value;

									if (strtotime($expireTime) <= strtotime(date('d M Y H:i:s'))) {
										$expiryStatus = '<span class="label label-danger">' . $expireTime . '</span>';
									}
									else {
										$expiryStatus = '<span class="label label-success">' . $expireTime . '</span>';
									}
								}
								else {
									$expiryStatus = '<span class="label label-primary">No Expiry</span>';
								}
							}
							else {
								$expiryStatus = '<span class="label label-default">N/A</span>';
							}
						}
						else {
							$expiryStatus = '<span class="label label-default">N/A</span>';
						}

						$userRadCheckData = getRadcheckByUsername($row->username);

						if ($userStatusData) {
							if ($userStatusData->description == 'Registered') {
								$status = '<span data-toggle="tooltip" title="Registered/Pending/New User" class="label label-primary"><i class="fas fa-check-square"></i></span>';
							}
							else if ($userStatusData->description == 'Active') {
								if ($userRadCheckData) {
									$expireTime = $userRadCheckData->value;

									if (strtotime($expireTime) <= strtotime(date('d M Y H:i:s'))) {
										$status = '<span data-toggle="tooltip" title="Expired User" class="label label-danger"><i class="fas fa-ban"></i></span>';
									}
									else if (getRadcheckPassByUsername($row->username)) {
										$status = '<span data-toggle="tooltip" title="Active User" class="label label-success"><i class="fas fa-check-square"></i></span>';
									}
									else {
										$status = '<span data-toggle="tooltip" title="Password Not Found" class="label label-danger"><i class="fas fa-ban"></i></span>';
									}
								}
								else {
									$status = '<span data-toggle="tooltip" title="No Expiry User" class="label label-primary"><i class="fas fa-clock"></i></span>';
								}
							}
							else if ($userStatusData->description == 'Disabled') {
								$status = '<span data-toggle="tooltip" title="Disabled User" class="label label-warning"><i class="fas fa-toggle-off"></i></span>';
							}
							else {
								$status = '<span data-toggle="tooltip" title="Unknown User" class="label label-default"><i class="fas fa-question-circle"></i></span>';
							}
						}
						else {
							$status = '<span data-toggle="tooltip" title="Unknown User" class="label label-default"><i class="fas fa-question-circle"></i></span>';
						}

						$payment = '<a class="user-payment-btn" data-userid="' . $this->encryption->encrypt('userID:' . $row->id) . '" data-toggle="modal" data-target=".payment_modal"><span data-toggle="tooltip" title="Add Payment" class="label label-primary"><i class="fab fa-paypal"></i> Payment</span></a>';
						$select = '<a class="user-select-btn" data-userid="' . $row->id . '"><span data-toggle="tooltip" title="Select User For Action" class="label label-white"><input class="toggle-btn" type="checkbox" data-toggle="toggle"></span></a>';

						if ($userStatusData) {
							if ($userStatusData->description == 'Registered') {
								$activation = '<a class="user-activation-btn" data-userid="' . $this->encryption->encrypt('userID:' . $row->id) . '" data-toggle="modal" data-target=".activation_modal"><span data-toggle="tooltip" title="Activate Connection" class="label label-primary"><i class="fas fa-satellite-dish"></i> Activate</span></a>';
							}
							else {
								$activation = '<a class="user-activation-btn" data-userid="' . $this->encryption->encrypt('userID:' . $row->id) . '" data-toggle="modal" data-target=".activation_modal"><span data-toggle="tooltip" title="Renew Connection" class="label label-success"><i class="fas fa-sync"></i> Renew</span></a>';
							}
						}

						$nestedData = [];
						$nestedData[] = $row->id;
						if (!empty($row->photo) && file_exists('assets/images/final/' . $row->photo)) {
							$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $row->id . '/"><img class="profile_photo" src="' . base_url('assets/images/final/' . $row->photo) . '" alt="' . $row->username . '"></a>';
						}
						else {
							$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $row->id . '/"><img class="profile_photo" src="' . base_url('assets/system/images/user.png') . '" alt="' . $row->username . '"></a>';
						}

						$userCity = getTypesByData($row->city, 'city');
						$userArea = getTypesByData($row->area, 'area');
						$userSubarea = getTypesByData($row->subarea, 'subarea');
						$address = (!empty($row->address) ? $row->address . ', ' : '');
						$subarea = ($userSubarea ? $userSubarea->description . ', ' : '');
						$area = ($userArea ? $userArea->description . ', ' : '');
						$city = ($userCity ? $userCity->description . ', ' : '');
						$address = $address . $subarea . $area . $city;
						$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $row->id . '/"><span class="label label-success">' . $row->username . '</span></a>';
						$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $row->id . '/"><span class="label label-default">' . $row->name . '</span></a>';
						$nestedData[] = $row->nic;
						$nestedData[] = $row->mobile;
						$nestedData[] = $address;
						$nestedData[] = (getPackageByID($row->package) ? getPackageByID($row->package)->name : 'N/A');
						$nestedData[] = (getAdminByID($row->saleperson) ? getAdminByID($row->saleperson)->username : 'N/A');
						$nestedData[] = $userBalance;
						$nestedData[] = $connectionType;
						$nestedData[] = $onlineStatus;
						$nestedData[] = $onlineUptime . '<br>' . $onlineDowntime;
						$nestedData[] = $usageData . ' GB <br>' . $usageTime;
						$nestedData[] = $expiryStatus;
						$nestedData[] = $row->staticip . '<br>' . $row->macaddress;
						$nestedData[] = ($row->smsstatus == 1 ? 'On' : 'Off');
						$nestedData[] = $policy;
						$nestedData[] = $ipPool;
						$nestedData[] = (getNas($user->nasid) ? getNas($user->nasid)[0]->nasname : 'N/A');
						$nestedData[] = number_format($row->discount, 2);
						$nestedData[] = $row->joindate;
						$nestedData[] = '<div class=\'all-users-table-action\'>' . $payment . ' ' . $activation . ' ' . $status . ' ' . $select . '</div>';
						if (($filterType == 7) && ($onlineStatusCheck == 1)) {
							$totalOffline++;
						}
						else {
							$data[] = $nestedData;
						}
					}
				}
				if (($filterType == 7) && ($onlineStatusCheck == 1)) {
					$totalData = $totalData - $totalOffline;
					$totalFiltered = $totalFiltered - $totalOffline;
				}

				$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) $totalData, 'recordsFiltered' => (int) $totalFiltered, 'data' => $data, 'csrf' => $this->security->get_csrf_hash()];
				echo json_encode($json_data);
			}
			else {
				$data = [];
				$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) 0, 'recordsFiltered' => (int) 0, 'data' => $data, 'csrf' => $this->security->get_csrf_hash()];
				echo json_encode($json_data);
			}
		}
		else {
			$data = [];
			$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) 0, 'recordsFiltered' => (int) 0, 'data' => $data, 'csrf' => $this->security->get_csrf_hash()];
			echo json_encode($json_data);
		}
	}

	public function getServerSideOnlineUsers()
	{
		$requestData = $_REQUEST;
		$columns = ['username'];
		$totalData = 0;
		$totalFiltered = 0;
		$totalStaleSession = 0;

		if (!empty($requestData['search']['value'])) {
			if (checkAdminOrStaff()) {
				$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->group_start();
				$this->db->like('username', $requestData['search']['value']);
				$this->db->or_like('nasipaddress', $requestData['search']['value']);
				$this->db->or_like('nasportid', $requestData['search']['value']);
				$this->db->or_like('acctstarttime', $requestData['search']['value']);
				$this->db->or_like('acctupdatetime', $requestData['search']['value']);
				$this->db->or_like('acctstoptime', $requestData['search']['value']);
				$this->db->or_like('calledstationid', $requestData['search']['value']);
				$this->db->or_like('callingstationid', $requestData['search']['value']);
				$this->db->or_like('acctterminatecause', $requestData['search']['value']);
				$this->db->or_like('framedipaddress', $requestData['search']['value']);
				$this->db->group_end();
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$totalFiltered = $query->num_rows();
				$this->db->flush_cache();
				$this->db->group_start();
				$this->db->like('username', $requestData['search']['value']);
				$this->db->or_like('nasipaddress', $requestData['search']['value']);
				$this->db->or_like('nasportid', $requestData['search']['value']);
				$this->db->or_like('acctstarttime', $requestData['search']['value']);
				$this->db->or_like('acctupdatetime', $requestData['search']['value']);
				$this->db->or_like('acctstoptime', $requestData['search']['value']);
				$this->db->or_like('calledstationid', $requestData['search']['value']);
				$this->db->or_like('callingstationid', $requestData['search']['value']);
				$this->db->or_like('acctterminatecause', $requestData['search']['value']);
				$this->db->or_like('framedipaddress', $requestData['search']['value']);
				$this->db->group_end();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$this->db->flush_cache();
			}
			else if (checkFranchise()) {
				$users = $this->franchiseM->getUsersByFranchise();

				if ($users) {
					$userIDs = [];
					$userNames = [];

					foreach ($users as $user) {
						$userIDs[] = $user->id;
						$userNames[] = $user->username;
					}

					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$this->db->flush_cache();
				}
			}
			else if (checkDealer()) {
				$users = $this->main->getUsersByDealer();

				if ($users) {
					$userIDs = [];
					$userNames = [];

					foreach ($users as $user) {
						$userIDs[] = $user->id;
						$userNames[] = $user->username;
					}

					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$this->db->flush_cache();
				}
			}
			else if (checkSubdealer()) {
				$users = $this->main->getUsersBySubdealer();

				if ($users) {
					$userIDs = [];
					$userNames = [];

					foreach ($users as $user) {
						$userIDs[] = $user->id;
						$userNames[] = $user->username;
					}

					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$this->db->flush_cache();
				}
			}
		}
		else if (checkAdminOrStaff()) {
			$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
			$this->db->flush_cache();
			$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
			$this->db->limit($requestData['length'], $requestData['start']);
			$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
			$totalFiltered = $totalData;
		}
		else if (checkFranchise()) {
			$users = $this->franchiseM->getUsersByFranchise();

			if ($users) {
				$userIDs = [];
				$userNames = [];

				foreach ($users as $user) {
					$userIDs[] = $user->id;
					$userNames[] = $user->username;
				}

				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('username', $usernames);
				}

				$this->db->group_end();
				$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('username', $usernames);
				}

				$this->db->group_end();
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$totalFiltered = $totalData;
			}
		}
		else if (checkDealer()) {
			$users = $this->main->getUsersByDealer();

			if ($users) {
				$userIDs = [];
				$userNames = [];

				foreach ($users as $user) {
					$userIDs[] = $user->id;
					$userNames[] = $user->username;
				}

				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('username', $usernames);
				}

				$this->db->group_end();
				$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('username', $usernames);
				}

				$this->db->group_end();
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$totalFiltered = $totalData;
			}
		}
		else if (checkSubdealer()) {
			$users = $this->main->getUsersBySubdealer();

			if ($users) {
				$userIDs = [];
				$userNames = [];

				foreach ($users as $user) {
					$userIDs[] = $user->id;
					$userNames[] = $user->username;
				}

				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('username', $usernames);
				}

				$this->db->group_end();
				$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('username', $usernames);
				}

				$this->db->group_end();
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$totalFiltered = $totalData;
			}
		}
		if (isset($query) && $query) {
			if (checkFranchise()) {
				$staleSessions = $this->franchiseM->staleSessionUsers();
			}
			else if (checkDealer()) {
				$staleSessions = $this->dealerM->staleSessionUsers();
			}
			else if (checkSubdealer()) {
				$staleSessions = $this->subdealerM->staleSessionUsers();
			}
			else {
				$staleSessions = $this->adminM->staleSessionUsers();
			}
			if ($staleSessions && is_array($staleSessions)) {
				$totalStaleSession = count($staleSessions);
			}

			$data = [];
			$i = 0;

			foreach ($query->result() as $row) {
				$i++;
				$username = $row->username;
				$uploadKIB = $row->acctinputoctets / 1024;
				$downloadKIB = $row->acctoutputoctets / 1024;
				$uploadMB = $uploadKIB / 1024;
				$downloadMB = $downloadKIB / 1024;
				$uploadGB = $uploadMB / 1024;
				$downloadGB = $downloadMB / 1024;
				$date1 = new DateTime(date('Y-m-d H:i:s', strtotime(getLocalTime())));
				$date2 = new DateTime(date('Y-m-d H:i:s', strtotime($row->acctstarttime)));
				$interval = $date1->diff($date2);
				$currentTime = date('Y-m-d H:i:s');
				$acctUpdateTime = $row->acctupdatetime;
				$acctUpdateTime = date('Y-m-d H:i:s', strtotime('+' . $this->settings->stalesession . ' minutes', strtotime($acctUpdateTime)));
				$userData = $this->main->getUserByUsername($row->username);
				$login = NULL;
				$uptime = NULL;
				$update = NULL;

				if ($userData) {
					$last_interim_update = $userData->last_interim_update;
					if (isset($last_interim_update) && !empty($last_interim_update)) {
						$currentMinusTime = date('Y-m-d H:i:s', strtotime('-' . $this->settings->stalesession . ' minutes'));
						if ((strtotime($last_interim_update) <= strtotime($currentMinusTime)) && ($this->settings->radiusstalesession == 1)) {
							if (0 < $interval->d) {
								$login = $row->acctstarttime;
								$uptime = $interval->format('%Dd %Hh %Im %Ss');
								$update = '<span class="text-danger">' . $userData->last_interim_update . ' (Stale)</span>';
							}
							else {
								$login = $uptime = $row->acctstarttime;
								$uptime = $interval->format('%Hh %Im %Ss');
								$update = '<span class="text-danger">' . $userData->last_interim_update . ' (Stale)</span>';
							}
						}
						else if (0 < $interval->d) {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Dd %Hh %Im %Ss');
							$update = $userData->last_interim_update;
						}
						else {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Hh %Im %Ss');
							$update = $userData->last_interim_update;
						}
					}
					else if ((strtotime($acctUpdateTime) <= strtotime($currentTime)) && ($this->settings->radiusstalesession == 1)) {
						if (0 < $interval->d) {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Dd %Hh %Im %Ss');
							$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
						}
						else {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Hh %Im %Ss');
							$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
						}
					}
					else if (0 < $interval->d) {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Dd %Hh %Im %Ss');
						$update = $row->acctupdatetime;
					}
					else {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Hh %Im %Ss');
						$update = $row->acctupdatetime;
					}
				}
				else if ((strtotime($acctUpdateTime) <= strtotime($currentTime)) && ($this->settings->radiusstalesession == 1)) {
					if (0 < $interval->d) {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Dd %Hh %Im %Ss');
						$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
					}
					else {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Hh %Im %Ss');
						$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
					}
				}
				else if (0 < $interval->d) {
					$login = $row->acctstarttime;
					$uptime = $interval->format('%Dd %Hh %Im %Ss');
					$update = $row->acctupdatetime;
				}
				else {
					$login = $row->acctstarttime;
					$uptime = $interval->format('%Hh %Im %Ss');
					$update = $row->acctupdatetime;
				}
				if ((1024 < $uploadKIB) && ($uploadKIB < 1048576)) {
					$up = number_format($uploadMB, 2) . ' MB';
				}
				else if (1048576 < $uploadKIB) {
					$up = number_format($uploadGB, 2) . ' GB';
				}
				else {
					$up = number_format($uploadKIB, 2) . ' KB';
				}
				if ((1024 < $downloadKIB) && ($downloadKIB < 1048576)) {
					$down = number_format($downloadMB, 2) . ' MB';
				}
				else if (1048576 < $downloadKIB) {
					$down = number_format($downloadGB, 2) . ' GB';
				}
				else {
					$down = number_format($downloadKIB, 2) . ' KB';
				}

				$nestedData = [];
				$nestedData[] = $i;

				if ($userData) {
					$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $userData->id . '/"><span class="label label-success">' . $row->username . '</span></a>';
					$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $userData->id . '/"><span class="label label-default">' . $userData->name . '</span></a>';
				}
				else {
					$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="#"><span class="label label-default">' . $row->username . '</span></a>';
					$nestedData[] = 'N/A';
				}

				$nestedData[] = $login;
				$nestedData[] = $uptime;
				$nestedData[] = $update;
				$nestedData[] = $row->callingstationid;
				$nestedData[] = $row->framedipaddress;
				$nestedData[] = $up;
				$nestedData[] = $down;
				$nestedData[] = $row->nasipaddress;

				if ($userData) {
					$nestedData[] = '<div class="all-users-table-action"><a class="disable-user-connection" href="' . base_url() . 'user/disconnect/' . $userData->id . '"><span class="label label-danger" data-toggle="tooltip" title="Disconnect User"><i class="fas fa-user-alt-slash"></i></span></a><a class="disable-user-connection" href="' . base_url() . 'user/clear-session/' . $userData->id . '"><span class="label label-danger" data-toggle="tooltip" title="Disconnect User & Force To Clear Session"><i class="fas fa-user-alt-slash"></i></span></a></div>';
				}
				else {
					$nestedData[] = '<a class="disable-user-connection" href="#"><span class="label label-danger" data-toggle="tooltip" title="Disconnect User"><i class="fas fa-user-alt-slash"></i></span></a>';
				}

				$data[] = $nestedData;
			}

			$json_data = [
				'draw'            => (int) $requestData['draw'],
				'recordsTotal'    => (int) $totalData,
				'recordsFiltered' => (int) $totalFiltered,
				'data'            => $data,
				'counter'         => ['totalOnline' => $totalData, 'totalStale' => $totalStaleSession]
			];
			echo json_encode($json_data);
		}
		else {
			$data = [];
			$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) 0, 'recordsFiltered' => (int) 0, 'data' => $data];
			echo json_encode($json_data);
		}
	}

	public function getServerSideStaleUsers()
	{
		$requestData = $_REQUEST;
		$columns = ['radacct.username'];
		$totalData = 0;
		$totalFiltered = 0;
		$staleClearTime = $this->settings->stalesession;
		$this->db->flush_cache();

		if (!empty($requestData['search']['value'])) {
			if (checkAdminOrStaff()) {
				$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
				$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->group_start();
				$this->db->like('username', $requestData['search']['value']);
				$this->db->or_like('nasipaddress', $requestData['search']['value']);
				$this->db->or_like('nasportid', $requestData['search']['value']);
				$this->db->or_like('acctstarttime', $requestData['search']['value']);
				$this->db->or_like('acctupdatetime', $requestData['search']['value']);
				$this->db->or_like('acctstoptime', $requestData['search']['value']);
				$this->db->or_like('calledstationid', $requestData['search']['value']);
				$this->db->or_like('callingstationid', $requestData['search']['value']);
				$this->db->or_like('acctterminatecause', $requestData['search']['value']);
				$this->db->or_like('framedipaddress', $requestData['search']['value']);
				$this->db->group_end();
				$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$totalFiltered = $query->num_rows();
				$this->db->flush_cache();
				$this->db->group_start();
				$this->db->like('username', $requestData['search']['value']);
				$this->db->or_like('nasipaddress', $requestData['search']['value']);
				$this->db->or_like('nasportid', $requestData['search']['value']);
				$this->db->or_like('acctstarttime', $requestData['search']['value']);
				$this->db->or_like('acctupdatetime', $requestData['search']['value']);
				$this->db->or_like('acctstoptime', $requestData['search']['value']);
				$this->db->or_like('calledstationid', $requestData['search']['value']);
				$this->db->or_like('callingstationid', $requestData['search']['value']);
				$this->db->or_like('acctterminatecause', $requestData['search']['value']);
				$this->db->or_like('framedipaddress', $requestData['search']['value']);
				$this->db->group_end();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
				$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
				$this->db->flush_cache();
			}
			else if (checkFranchise()) {
				$users = $this->franchiseM->getUsersByFranchise();

				if ($users) {
					$userIDs = [];
					$userNames = [];

					foreach ($users as $user) {
						$userIDs[] = $user->id;
						$userNames[] = $user->username;
					}

					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$this->db->flush_cache();
				}
			}
			else if (checkDealer()) {
				$users = $this->main->getUsersByDealer();

				if ($users) {
					$userIDs = [];
					$userNames = [];

					foreach ($users as $user) {
						$userIDs[] = $user->id;
						$userNames[] = $user->username;
					}

					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$this->db->flush_cache();
				}
			}
			else if (checkSubdealer()) {
				$users = $this->main->getUsersBySubdealer();

				if ($users) {
					$userIDs = [];
					$userNames = [];

					foreach ($users as $user) {
						$userIDs[] = $user->id;
						$userNames[] = $user->username;
					}

					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$totalData = $this->db->get_where('radacct', ['acctstoptime' => NULL])->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$totalFiltered = $query->num_rows();
					$this->db->flush_cache();
					$this->db->group_start();
					$this->db->like('username', $requestData['search']['value']);
					$this->db->or_like('nasipaddress', $requestData['search']['value']);
					$this->db->or_like('nasportid', $requestData['search']['value']);
					$this->db->or_like('acctstarttime', $requestData['search']['value']);
					$this->db->or_like('acctupdatetime', $requestData['search']['value']);
					$this->db->or_like('acctstoptime', $requestData['search']['value']);
					$this->db->or_like('calledstationid', $requestData['search']['value']);
					$this->db->or_like('callingstationid', $requestData['search']['value']);
					$this->db->or_like('acctterminatecause', $requestData['search']['value']);
					$this->db->or_like('framedipaddress', $requestData['search']['value']);
					$this->db->group_end();
					$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
					$this->db->limit($requestData['length'], $requestData['start']);
					$this->db->group_start();
					$usernames_chunk = array_chunk($userNames, 25);

					foreach ($usernames_chunk as $usernames) {
						$this->db->or_where_in('username', $usernames);
					}

					$this->db->group_end();
					$this->db->where('acctupdatetime <=', date('Y-m-d H:i:s', strtotime('+' . $staleClearTime . ' minutes')));
					$query = $this->db->get_where('radacct', ['acctstoptime' => NULL]);
					$this->db->flush_cache();
				}
			}
		}
		else if (checkAdminOrStaff()) {
			$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
			$this->db->where('radacct.acctstoptime', NULL);
			$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
			$totalData = $this->db->get('radacct')->num_rows();
			$this->db->flush_cache();
			$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
			$this->db->limit($requestData['length'], $requestData['start']);
			$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
			$this->db->where('radacct.acctstoptime', NULL);
			$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
			$query = $this->db->get('radacct');
			$this->db->flush_cache();
			$totalFiltered = $totalData;
		}
		else if (checkFranchise()) {
			$users = $this->franchiseM->getUsersByFranchise();

			if ($users) {
				$userIDs = [];
				$userNames = [];

				foreach ($users as $user) {
					$userIDs[] = $user->id;
					$userNames[] = $user->username;
				}

				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('radacct.username', $usernames);
				}

				$this->db->group_end();
				$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
				$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
				$totalData = $this->db->get_where('radacct', ['radacct.acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('radacct.username', $usernames);
				}

				$this->db->group_end();
				$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
				$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
				$query = $this->db->get_where('radacct', ['radacct.acctstoptime' => NULL]);
				$totalFiltered = $totalData;
			}
		}
		else if (checkDealer()) {
			$users = $this->main->getUsersByDealer();

			if ($users) {
				$userIDs = [];
				$userNames = [];

				foreach ($users as $user) {
					$userIDs[] = $user->id;
					$userNames[] = $user->username;
				}

				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('radacct.username', $usernames);
				}

				$this->db->group_end();
				$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
				$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
				$totalData = $this->db->get_where('radacct', ['radacct.acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('radacct.username', $usernames);
				}

				$this->db->group_end();
				$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
				$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
				$query = $this->db->get_where('radacct', ['radacct.acctstoptime' => NULL]);
				$totalFiltered = $totalData;
			}
		}
		else if (checkSubdealer()) {
			$users = $this->main->getUsersBySubdealer();

			if ($users) {
				$userIDs = [];
				$userNames = [];

				foreach ($users as $user) {
					$userIDs[] = $user->id;
					$userNames[] = $user->username;
				}

				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('radacct.username', $usernames);
				}

				$this->db->group_end();
				$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
				$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
				$totalData = $this->db->get_where('radacct', ['radacct.acctstoptime' => NULL])->num_rows();
				$this->db->flush_cache();
				$this->db->order_by($columns[$requestData['order'][0]['column']], $requestData['order'][0]['dir']);
				$this->db->limit($requestData['length'], $requestData['start']);
				$this->db->group_start();
				$usernames_chunk = array_chunk($userNames, 25);

				foreach ($usernames_chunk as $usernames) {
					$this->db->or_where_in('radacct.username', $usernames);
				}

				$this->db->group_end();
				$this->db->join('usersinfo', 'usersinfo.username = radacct.username', 'left');
				$this->db->where('usersinfo.last_interim_update <=', date('Y-m-d H:i:s', strtotime('-' . $staleClearTime . ' minutes')));
				$query = $this->db->get_where('radacct', ['radacct.acctstoptime' => NULL]);
				$totalFiltered = $totalData;
			}
		}
		if (isset($query) && $query) {
			$data = [];
			$i = 0;

			foreach ($query->result() as $row) {
				$i++;
				$username = $row->username;
				$uploadKIB = $row->acctinputoctets / 1024;
				$downloadKIB = $row->acctoutputoctets / 1024;
				$uploadMB = $uploadKIB / 1024;
				$downloadMB = $downloadKIB / 1024;
				$uploadGB = $uploadMB / 1024;
				$downloadGB = $downloadMB / 1024;
				$date1 = new DateTime(date('Y-m-d H:i:s', strtotime(getLocalTime())));
				$date2 = new DateTime(date('Y-m-d H:i:s', strtotime($row->acctstarttime)));
				$interval = $date1->diff($date2);
				$currentTime = date('Y-m-d H:i:s');
				$acctUpdateTime = $row->acctupdatetime;
				$acctUpdateTime = date('Y-m-d H:i:s', strtotime('+' . $this->settings->stalesession . ' minutes', strtotime($acctUpdateTime)));
				$userData = $this->main->getUserByUsername($row->username);
				$login = NULL;
				$uptime = NULL;
				$update = NULL;

				if ($userData) {
					$last_interim_update = $userData->last_interim_update;
					if (isset($last_interim_update) && !empty($last_interim_update)) {
						$currentMinusTime = date('Y-m-d H:i:s', strtotime('-' . $this->settings->stalesession . ' minutes'));

						if (strtotime($last_interim_update) <= strtotime($currentMinusTime)) {
							if (0 < $interval->d) {
								$login = $row->acctstarttime;
								$uptime = $interval->format('%Dd %Hh %Im %Ss');
								$update = '<span class="text-danger">' . $userData->last_interim_update . ' (Stale)</span>';
							}
							else {
								$login = $row->acctstarttime;
								$uptime = $interval->format('%Hh %Im %Ss');
								$update = '<span class="text-danger">' . $userData->last_interim_update . ' (Stale)</span>';
							}
						}
						else if (0 < $interval->d) {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Dd %Hh %Im %Ss');
							$update = $userData->last_interim_update;
						}
						else {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Hh %Im %Ss');
							$update = $userData->last_interim_update;
						}
					}
					else if (strtotime($acctUpdateTime) <= strtotime($currentTime)) {
						if (0 < $interval->d) {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Dd %Hh %Im %Ss');
							$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
						}
						else {
							$login = $row->acctstarttime;
							$uptime = $interval->format('%Hh %Im %Ss');
							$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
						}
					}
					else if (0 < $interval->d) {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Dd %Hh %Im %Ss');
						$update = $row->acctupdatetime;
					}
					else {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Hh %Im %Ss');
						$update = $row->acctupdatetime;
					}
				}
				else if (strtotime($acctUpdateTime) <= strtotime($currentTime)) {
					if (0 < $interval->d) {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Dd %Hh %Im %Ss');
						$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
					}
					else {
						$login = $row->acctstarttime;
						$uptime = $interval->format('%Hh %Im %Ss');
						$update = '<span class="text-danger">' . $row->acctupdatetime . ' (Stale)</span>';
					}
				}
				else if (0 < $interval->d) {
					$login = $row->acctstarttime;
					$uptime = $interval->format('%Dd %Hh %Im %Ss');
					$update = $row->acctupdatetime;
				}
				else {
					$login = $row->acctstarttime;
					$uptime = $interval->format('%Hh %Im %Ss');
					$update = $row->acctupdatetime;
				}
				if ((1024 < $uploadKIB) && ($uploadKIB < 1048576)) {
					$up = number_format($uploadMB, 2) . ' MB';
				}
				else if (1048576 < $uploadKIB) {
					$up = number_format($uploadGB, 2) . ' GB';
				}
				else {
					$up = number_format($uploadKIB, 2) . ' KB';
				}
				if ((1024 < $downloadKIB) && ($downloadKIB < 1048576)) {
					$down = number_format($downloadMB, 2) . ' MB';
				}
				else if (1048576 < $downloadKIB) {
					$down = number_format($downloadGB, 2) . ' GB';
				}
				else {
					$down = number_format($downloadKIB, 2) . ' KB';
				}

				$nestedData = [];
				$nestedData[] = $i;

				if ($userData) {
					$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $userData->id . '/"><span class="label label-success">' . $row->username . '</span></a>';
					$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="' . base_url() . 'user/profile/' . $userData->id . '/"><span class="label label-default">' . $userData->name . '</span></a>';
				}
				else {
					$nestedData[] = '<a data-toggle="tooltip" title="View Profile" href="#"><span class="label label-default">' . $row->username . '</span></a>';
					$nestedData[] = 'N/A';
				}

				$nestedData[] = $login;
				$nestedData[] = $uptime;
				$nestedData[] = $update;
				$nestedData[] = $row->callingstationid;
				$nestedData[] = $row->framedipaddress;
				$nestedData[] = $up;
				$nestedData[] = $down;
				$nestedData[] = $row->nasipaddress;

				if ($userData) {
					$nestedData[] = '<a class="disable-user-connection" href="' . base_url() . 'user/disconnect/' . $userData->id . '"><span class="label label-danger" data-toggle="tooltip" title="Disconnect User"><i class="fas fa-user-alt-slash"></i></span></a>';
				}
				else {
					$nestedData[] = '<a class="disable-user-connection" href="#"><span class="label label-danger" data-toggle="tooltip" title="Disconnect User"><i class="fas fa-user-alt-slash"></i></span></a>';
				}

				$data[] = $nestedData;
			}

			$json_data = [
				'draw'            => (int) $requestData['draw'],
				'recordsTotal'    => (int) $totalData,
				'recordsFiltered' => (int) $totalFiltered,
				'data'            => $data,
				'counter'         => ['totalStale' => $totalData]
			];
			echo json_encode($json_data);
		}
		else {
			$data = [];
			$json_data = ['draw' => (int) $requestData['draw'], 'recordsTotal' => (int) 0, 'recordsFiltered' => (int) 0, 'data' => $data];
			echo json_encode($json_data);
		}
	}
}

?>

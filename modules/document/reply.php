<?php
	// modules/document/reply.php
	header("content-type: text/html; charset=UTF-8");
	// inint
	include '../../bin/inint.php';
	// ตรวจสอบ referer
	if (gcms::isReferer()) {
		// ค่าที่ส่งมา
		$email = $db->sql_trim_str($_POST['reply_email']);
		$password = $db->sql_trim_str($_POST['reply_password']);
		$detail = gcms::txtClean($_POST['reply_detail']);
		$index_id = (int)$_POST['index_id'];
		$module_id = (int)$_POST['module_id'];
		// แก้ไขคำตอบ
		$id = (int)$_POST['reply_id'];
		if ($id > 0) {
			// แก้ไขคำตอบ อ่านข้อมูลจาก คำตอบ
			$sql = "SELECT R.`member_id`,Q.`id`,Q.`comments`,Q.`can_reply`,R.`module_id`,M.`module`,C.`category_id`";
			$sql .= ",(CASE WHEN ISNULL(U.`id`) THEN R.`email` ELSE (CASE WHEN U.`displayname`='' THEN U.`email` ELSE U.`displayname` END) END) AS `commentator`";
			$sql .= " FROM `".DB_COMMENT."` AS R";
			$sql .= " LEFT JOIN `".DB_USER."` AS U ON U.`id`=R.`member_id`";
			$sql .= " INNER JOIN `".DB_INDEX."` AS Q ON Q.`id`='$index_id'";
			$sql .= " INNER JOIN `".DB_MODULES."` AS M ON M.`id`='$module_id'";
			$sql .= " LEFT JOIN `".DB_CATEGORY."` AS C ON C.`category_id`=Q.`category_id`";
			$sql .= " WHERE R.`id`='$id' AND R.`index_id`='$index_id' LIMIT 1";
		} else {
			// ตอบคำถามใหม่ ตรวจสอบคำถาม
			$sql = "SELECT Q.`id`,Q.`comments`,Q.`module_id`,Q.`can_reply`,M.`module`,M.`config`,C.`category_id`";
			$sql .= " FROM `".DB_INDEX."` AS Q";
			$sql .= " INNER JOIN `".DB_MODULES."` AS M ON M.`id`='$module_id'";
			$sql .= " LEFT JOIN `".DB_CATEGORY."` AS C ON C.`category_id`=Q.`category_id`";
			$sql .= " WHERE Q.`id`='$index_id' AND Q.`module_id`='$module_id' LIMIT 1";
		}
		$index = $db->customQuery($sql);
		if (sizeof($index) == 1) {
			$index = $index[0];
			// สมาชิก
			$isMember = gcms::isMember();
			// true แสดงความคิดเห็นได้
			$canReply = $index['can_reply'] == 1;
			// config
			gcms::r2config($index['config'], $index);
			// can reply
			$can_reply = explode(',', $index['can_reply']);
			// true = guest โพสต์ได้
			$guest = in_array(-1, $can_reply);
			// ผู้ดูแล
			$moderator = $isMember && gcms::canConfig(explode(',', $index['moderator']));
			$canReply = $moderator || $canReply;
			// login ใช้ email และ password ของคน login
			if ($isMember) {
				$email = $_SESSION['login']['email'];
				$password = $_SESSION['login']['password'];
			}
		} else {
			$index = false;
		}
		// ตรวจสอบค่าที่ส่งมา
		$post = array();
		$q = array();
		$ret = array();
		if (!$index || !$canReply) {
			// ไม่พบ หรือ ปิดการ post
			$ret['error'] = 'ACTION_ERROR';
		} elseif ($_POST['reply_antispam'] != $_SESSION[$_POST['reply_antispamid']]) {
			$ret['ret_reply_antispam'] = 'this';
			$ret['input'] = 'reply_antispam';
		} elseif ($detail == '') {
			$ret['error'] = 'DETAIL_EMPTY';
			$ret['input'] = 'reply_detail';
			$ret['ret_reply_detail'] = 'DETAIL_EMPTY';
		} elseif ($id == 0) {
			// แสดงความคิ ดเห็นใหม่
			if ($email == '') {
				// ไม่ได้กรอกอีเมล
				$ret['error'] = 'EMAIL_EMPTY';
				$ret['input'] = 'reply_email';
				$ret['ret_reply_email'] = 'EMAIL_EMPTY';
			} elseif ($password == '' && !$guest) {
				// สมาชิกเท่านั้น ไม่ได้กรอก รหัสผ่าน
				$ret['error'] = 'PASSWORD_EMPTY';
				$ret['input'] = 'reply_password';
				$ret['ret_reply_password'] = 'PASSWORD_EMPTY';
			} elseif ($email != '' && $password != '') {
				// ตรวจสอบสมาชิกหากมีการกรอก email และ password
				$user = gcms::CheckLogin($email, $password);
				if ($user == 0 || $user == 3) {
					$ret['error'] = 'EMAIL_OR_PASSWORD_INCORRECT';
					$ret['input'] = $user == 0 ? 'reply_email' : 'reply_password';
					$ret['ret_'.$ret['input']] = 'EMAIL_OR_PASSWORD_INCORRECT';
				} elseif ($user == 1) {
					$ret['error'] = 'MEMBER_NO_ACTIVATE';
					$ret['input'] = 'reply_email';
					$ret['ret_reply_email'] = 'MEMBER_NO_ACTIVATE';
				} elseif ($user == 2) {
					$ret['error'] = 'MEMBER_BAN';
					$ret['input'] = 'reply_email';
					$ret['ret_reply_email'] = 'MEMBER_BAN';
				} elseif (!in_array($user['status'], $can_reply)) {
					$ret['error'] = 'DO_NOT_REPLY';
					$ret['input'] = 'reply_email';
					$ret['ret_reply_email'] = 'DO_NOT_REPLY';
				} else {
					// ชื่อสมาชิกใช้งานได้
					$sender = $user['displayname'] == '' ? $user['email'] : $user['displayname'];
					$post['member_id'] = $user['id'];
					$post['email'] = $user['email'];
				}
			} elseif ($guest) {
				// ตรวจสอบอีเมลซ้ำกับสมาชิก สำหรับบุคคลทั่วไป
				$sql = "SELECT `id` FROM `".DB_USER."` WHERE `email`='$email' LIMIT 1";
				$user2 = $db->customQuery($sql);
				if (sizeof($user2) > 0) {
					// ต้องการรหัสผ่าน
					$ret['error'] = 'PASSWORD_EMPTY';
					$ret['input'] = 'reply_password';
					$ret['ret_reply_email'] = 'PASSWORD_EMPTY';
				} elseif (!gcms::validMail($email)) {
					// อีเมลที่กรอกไม่ถูกต้อง
					$ret['error'] = 'REGISTER_INVALID_EMAIL';
					$ret['input'] = 'reply_email';
					$ret['ret_reply_email'] = 'REGISTER_INVALID_EMAIL';
				} else {
					// ผู้มาเยือน
					$sender = $email;
					$post['member_id'] = 0;
					$post['email'] = $email;
				}
			} else {
				$ret['error'] = 'MEMBER_ONLY';
			}
		} elseif (!($index['member_id'] == $login['id'] || $moderator)) {
			// แก้ไขความคิดเห็น ตรวจสอบ เจ้าของหรือผู้ดูแล
			$ret['error'] = 'ACTION_ERROR';
		}
		if (sizeof($ret) == 0 && $detail != '') {
			// ตรวจสอบโพสต์ซ้ำภายใน 1 วัน
			$sql = "SELECT `id` FROM `".DB_COMMENT."`";
			$sql .= " WHERE `detail`='".addslashes($detail)."' AND `email`='$post[email]'";
			$sql .= " AND `module_id`='$index[module_id]' AND `last_update`>".($mmktime - 86400);
			$sql .= " LIMIT 1";
			$flood = $db->customQuery($sql);
			if (sizeof($flood) > 0) {
				$ret['error'] = 'FLOOD_COMMENT';
			}
		}
		if (sizeof($ret) == 0) {
			// post ได้
			$post['detail'] = $detail;
			$post['last_update'] = $mmktime;
			if ($id > 0) {
				// แก้ไขความคิดเห็น
				$db->edit(DB_COMMENT, $id, $post);
				// อัปเดตคำถาม
				$q['commentator'] = $index['commentator'];
				$q['commentator_id'] = $index['member_id'];
				$q['comment_id'] = $id;
				// แก้ไขเรียบร้อย
				$ret['error'] = 'EDIT_SUCCESS';
			} else {
				// ความคิดเห็นใหม่
				$post['ip'] = gcms::getip();
				$post['index_id'] = $index['id'];
				$post['module_id'] = $index['module_id'];
				$id = $db->add(DB_COMMENT, $post);
				// อัปเดตคำถาม
				$q['commentator'] = $sender;
				$q['commentator_id'] = $post['member_id'];
				$q['comments'] = $index['comments'] + 1;
				$q['comment_id'] = $id;
				// อัปเดตสมาชิก
				if ($post['member_id'] > 0) {
					// อัปเดต reply
					$db->query("UPDATE `".DB_USER."` SET `reply`=`reply`+1 WHERE `id`='$post[member_id]' LIMIT 1");
				}
				if ($index['category_id'] > 0) {
					// อัปเดตจำนวนเรื่อง และ ความคิดเห็น ในหมวด
					$sql1 = "SELECT COUNT(*) FROM `".DB_INDEX."` WHERE `category_id`=C.`category_id` AND `module_id`='$index[module_id]' AND `index`='0'";
					$sql2 = "SELECT `id` FROM `".DB_INDEX."` WHERE `category_id`=C.`category_id` AND `module_id`='$index[module_id]' AND `index`='0'";
					$sql2 = "SELECT COUNT(*) FROM `".DB_COMMENT."` WHERE `index_id` IN ($sql2) AND `module_id`='$index[module_id]'";
					$sql = "UPDATE `".DB_CATEGORY."` AS C SET C.`c1`=($sql1),C.`c2`=($sql2) WHERE C.`module_id`='$index[module_id]'";
					$db->query($sql);
				}
				// โพสต์เรียบร้อย
				$ret['error'] = 'COMMENT_SUCCESS';
			}
			// อัปเดตคำถาม (comment ล่าสุด)
			$q['comment_date'] = $mmktime;
			$db->edit(DB_INDEX, $index['id'], $q);
			// เคลียร์ antispam
			unset($_SESSION[$_POST['reply_antispamid']]);
			// คืนค่า url ของ คำถาม
			$location = WEB_URL."/index.php?module=$index[module]&id=$index[id]&visited=$mmktime";
			$location .= $config['use_ajax'] == 1 ? "&scrollto=R_$id" : "#R_$id";
			$ret['location'] = rawurlencode($location);
		}
		// คืนค่าเป็น JSON
		echo gcms::array2json($ret);
	}

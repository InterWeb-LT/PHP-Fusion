<?php
/*-------------------------------------------------------+
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| http://www.php-fusion.co.uk/
+--------------------------------------------------------+
| Filename: infusions_include.php
| Author: Hans Kristian Flaatten (Starefossen)
| Co-Author: Robert Gaudyn (Wooya)
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/
if (!defined("IN_FUSION")) { die("Access Denied"); }

// Protect filename from uploader by renaming file.
if (!function_exists('random_filename')) {
	function random_filename($filename) {
		$secret_rand = rand(1000000, 9999999);
		$ext = strrchr($filename, ".");
		return substr(md5($secret_rand), 8, 8).$ext;
	}
}

if (!function_exists('filename_exists')) {
	/**
	 * Creates an unique filename if file already exists
	 * @param string $file       path of file if basename is empty. Otherwise path of parent directory
	 * @param string $basename   The name of file if $file is a directory. Otherwise leave it empty.
	 * @param string $dateFormat If you want date in directory path
	 * @return string  New unique filepath
	 * @options array -
	 *                           $options['dateformat'] d,m,y, php date format constant
	 *                           $options['hash'] '0' by default, '1' to add hash string
	 */
	function filename_exists($directory, $file = '', $options = FALSE) {
		$parts = pathinfo($directory.$file)+array('dirname' => '',
				'basename' => '',
				'extension' => '',
				'filename' => '');
		if ($parts['extension']) {
			//check if filename starts with dot
			if ($parts['filename']) {
				$parts['extension'] = '.'.strtolower($parts['extension']);
			} else {
				$parts['filename'] = '.'.$parts['extension'];
				$parts['extension'] = '';
			}
			$parts['basename'] = $parts['filename'].$parts['extension'];
		}
		if (isset($options['dateformat']) && $options['dateFormat']) {
			$parts['dirname'] .= '/'.rtrim(date($options['dateFormat']) ? : '.', '/');
		}
		$hash = isset($options['hash']) && $options['hash'] ? '_'.substr(md5(uniqid()), 8) : '';
		//create directory folder if not exists - secondary to current intention.
		$dir = array_filter(explode('/', $directory));
		$parent_dir = '';
		foreach ($dir as $_dir) {
			if (!file_exists($parent_dir.$_dir)) {
				//print_p("Created ".$parent_dir.$_dir." at 0755 ");
				mkdir($parent_dir.$_dir, 0755, TRUE);
				if (!file_exists($parent_dir.$_dir."index.php")) {
					//print_p("Created an index.php file in ".$parent_dir.$_dir." ");
					fopen($parent_dir.$_dir.'/index.php', 'w');
				}
			}
			$parent_dir .= $_dir.'/';
		}
		if (!$file) {
			// if exist, return directory, if not those directoy have been created.
			return $directory;
		} else {
			$prefix = $parts['filename'].$hash;
			$new_file = $prefix.$parts['extension'];
			$i = 0;
			while (file_exists($directory.$new_file)) {
				$new_file = $prefix.'_'.++$i.$parts['extension'];
			}
		}
		return $new_file;
	}
}
if (!function_exists('set_setting')) {
	// Sets the value of a setting in the settings_inf table
	function set_setting($setting_name, $setting_value, $setting_inf) {
		$set_result = dbquery("SELECT settings_name FROM ".DB_SETTINGS_INF." WHERE settings_name='".$setting_name."' AND settings_inf='".$setting_inf."'");
		$return = TRUE;
		if (dbrows($set_result)) {
			$up_result = dbquery("UPDATE ".DB_SETTINGS_INF." SET settings_value='".$setting_value."' WHERE settings_name='".$setting_name."' AND settings_inf='".$setting_inf."'");
			if (!$up_result) {
				$return = FALSE;
			}
		} else {
			$in_result = dbquery("INSERT INTO ".DB_SETTINGS_INF." (settings_name, settings_value, settings_inf) VALUES ('".$setting_name."', '".$setting_value."', '".$setting_inf."')");
			if (!$in_result) {
				$return = FALSE;
			}
		}
		return $return;
	}
}

// Get the settings for the infusion from the settings_inf table
if (!function_exists('get_settings')) {
	function get_settings($setting_inf) {
		$settings_arr = array();
		$set_result = dbquery("SELECT settings_name, settings_value FROM ".DB_SETTINGS_INF." WHERE settings_inf='".$setting_inf."'");
		if (dbrows($set_result)) {
			while ($set_data = dbarray($set_result)) {
				$settings_arr[$set_data['settings_name']] = $set_data['settings_value'];
			}
			return $settings_arr;
		} else {
			return FALSE;
		}
	}
}

// Send PM to a user
if (!function_exists('send_pm')) {
	function send_pm($to, $from, $subject, $message, $smileys = "y") {
		global $settings;
		include LOCALE.LOCALESET."messages.php";
		require_once INCLUDES."sendmail_include.php";
		require_once INCLUDES."flood_include.php";
		$msg_settings = dbarray(dbquery("SELECT pm_inbox, pm_email_notify FROM ".DB_MESSAGES_OPTIONS." WHERE user_id='0'"));
		$smileys = preg_match("#(\[code\](.*?)\[/code\]|\[geshi=(.*?)\](.*?)\[/geshi\]|\[php\](.*?)\[/php\])#si", $message) ? "n" : $smileys;
		$error = 0;
		if (!flood_control("message_datestamp", DB_MESSAGES, "message_from='".$from."'")) {
			$result = dbquery("SELECT u.user_id, u.user_name, u.user_email, u.user_level, mo.pm_email_notify, COUNT(message_id) as message_count FROM ".DB_USERS." u
			LEFT JOIN ".DB_MESSAGES_OPTIONS." mo USING(user_id)
			LEFT JOIN ".DB_MESSAGES." ON message_to=u.user_id AND message_folder='0'
			WHERE u.user_id='$to' GROUP BY u.user_id");
			if (dbrows($result)) {
				$data = dbarray($result);
				$result = dbquery("SELECT user_id, user_name FROM ".DB_USERS." WHERE user_id='".$from."'");
				if (dbrows($result)) {
					$userdata = dbarray($result);
					if ($to != $from) {
						if ($data['user_id'] == 1 || $data['user_level'] > 101 || $msg_settings['pm_inbox'] == "0" || ($data['message_count']+1) <= $msg_settings['pm_inbox']) {
							$result = dbquery("INSERT INTO ".DB_MESSAGES." (message_to, message_from, message_subject, message_message, message_smileys, message_read, message_datestamp, message_folder) VALUES('".$data['user_id']."','".$userdata['user_id']."','".$subject."','".$message."','".$smileys."','0','".time()."','0')");
							$send_email = isset($data['pm_email_notify']) ? $data['pm_email_notify'] : $msg_settings['pm_email_notify'];
							if ($send_email == "1") {
								$message_content = str_replace("[SUBJECT]", $subject, $locale['626']);
								$message_content = str_replace("[USER]", $userdata['user_name'], $message_content);
								$template_result = dbquery("SELECT template_key, template_active FROM ".DB_EMAIL_TEMPLATES." WHERE template_key='PM' LIMIT 1");
								if (dbrows($template_result)) {
									$template_data = dbarray($template_result);
									if ($template_data['template_active'] == "1") {
										sendemail_template("PM", $subject, trimlink($message, 150), $userdata['user_name'], $data['user_name'], "", $data['user_email']);
									} else {
										sendemail($data['user_name'], $data['user_email'], $settings['siteusername'], $settings['siteemail'], $locale['625'], $data['user_name'].$message_content);
									}
								} else {
									sendemail($data['user_name'], $data['user_email'], $settings['siteusername'], $settings['siteemail'], $locale['625'], $data['user_name'].$message_content);
								}
							}
						} else {
							// Inbox is full
							$error = 1;
						}
					} else {
						// Reciever and sender are the same user
						$error = 2;
					}
				} else {
					// Sender does not exist in DB
					$error = 3;
				}
			} else {
				// Reciever does not exist in DB
				$error = 4;
			}
		} else {
			// Floodcontrol exceeded
			$error = 5;
		}
		return $error;
	}
}

// Upload file function
if (!function_exists('upload_file')) {
	function upload_file($source_file, $target_file = "", $target_folder = DOWNLOADS, $valid_ext = ".zip,.rar,.tar,.bz2,.7z", $max_size = "15000", $query = "") {
		if (is_uploaded_file($_FILES[$source_file]['tmp_name'])) {

			$valid_ext = explode(",", $valid_ext);
			$file = $_FILES[$source_file];
			if ($target_file == "" || preg_match("/[^a-zA-Z0-9_-]/", $target_file)) {
				$target_file = stripfilename(substr($file['name'], 0, strrpos($file['name'], ".")));
			}
			$file_ext = strtolower(strrchr($file['name'], "."));
			$file_dest = $target_folder;
			$upload_file = array("source_file" => $source_file,
				"source_size" => $file['size'],
				"source_ext" => $file_ext,
				"target_file" => $target_file.$file_ext,
				"target_folder" => $target_folder,
				"valid_ext" => $valid_ext,
				"max_size" => $max_size,
				"query" => $query,
				"error" => 0);
			if ($file['size'] > $max_size) {
				// Maximum file size exceeded
				$upload_file['error'] = 1;
			} elseif (!in_array($file_ext, $valid_ext)) {
				// Invalid file extension
				$upload_file['error'] = 2;
			} else {
				$target_file = filename_exists($target_folder, $target_file.$file_ext);
				$upload_file['target_file'] = $target_file;
				move_uploaded_file($file['tmp_name'], $target_folder.$target_file);
				if (function_exists("chmod")) {
					chmod($target_folder.$target_file, 0644);
				}
				if ($query && !dbquery($query)) {
					// Invalid query string
					$upload_file['error'] = 3;
					unlink($target_folder.$target_file);
				}
			}
		} else {
			// File not uploaded
			$upload_file = array("error" => 4);
		}
		return $upload_file;
	}
}

// Upload image function
if (!function_exists('upload_image')) {
	function upload_image($source_image, $target_name = "", $target_folder = IMAGES, $target_width = "1800", $target_height = "1600", $max_size = "150000", $delete_original = FALSE, $thumb1 = TRUE, $thumb2 = TRUE, $thumb1_ratio = 0, $thumb1_folder = IMAGES, $thumb1_suffix = "_t1", $thumb1_width = "100", $thumb1_height = "100", $thumb2_ratio = 0, $thumb2_folder = IMAGES, $thumb2_suffix = "_t2", $thumb2_width = "400", $thumb2_height = "300", $query = "") {
		if (strlen($target_folder) > 0 && substr($target_folder, -1) !== '/') {
			$target_folder .= '/';
		}
		if (is_uploaded_file($_FILES[$source_image]['tmp_name'])) {
			$image = $_FILES[$source_image];
			if ($target_name != "" && !preg_match("/[^a-zA-Z0-9_-]/", $target_name)) {
				$image_name = $target_name;
			} else {
				$image_name = stripfilename(substr($image['name'], 0, strrpos($image['name'], ".")));
			}
			$image_ext = strtolower(strrchr($image['name'], "."));
			if (filesize($image['tmp_name']) > 10 && @getimagesize($image['tmp_name'])) {
				$image_res = @getimagesize($image['tmp_name']);
				$image_info = array("image" => FALSE,
					"image_name" => $image_name.$image_ext,
					"image_ext" => $image_ext,
					"image_size" => $image['size'],
					"image_width" => $image_res[0],
					"image_height" => $image_res[1],
					"thumb1" => FALSE,
					"thumb1_name" => "",
					"thumb2" => FALSE,
					"thumb2_name" => "",
					"error" => 0,
					"query" => $query);
				if ($image_ext == ".gif") {
					$filetype = 1;
				} elseif ($image_ext == ".jpg") {
					$filetype = 2;
				} elseif ($image_ext == ".png") {
					$filetype = 3;
				} else {
					$filetype = FALSE;
				}
				if ($image['size'] > $max_size) {
					// Invalid file size
					$image_info['error'] = 1;
				} elseif (!$filetype || !verify_image($image['tmp_name'])) {
					// Unsupported image type
					$image_info['error'] = 2;
				} elseif ($image_res[0] > $target_width || $image_res[1] > $target_height) {
					// Invalid image resolution
					$image_info['error'] = 3;
				} else {
					if (!file_exists($target_folder)) {
						mkdir($target_folder, 0755);
					}
					$image_name_full = filename_exists($target_folder, $image_name.$image_ext);
					$image_name = substr($image_name_full, 0, strrpos($image_name_full, "."));
					$image_info['image_name'] = $image_name_full;
					$image_info['image'] = TRUE;
					move_uploaded_file($image['tmp_name'], $target_folder.$image_name_full);
					if (function_exists("chmod")) {
						chmod($target_folder.$image_name_full, 0755);
					}
					if ($query && !dbquery($query)) {
						// Invalid query string
						$image_info['error'] = 4;
						unlink($target_folder.$image_name_full);
					} elseif ($thumb1 || $thumb2) {
						require_once INCLUDES."photo_functions_include.php";
						$noThumb = FALSE;
						if ($thumb1) {
							if ($image_res[0] <= $thumb1_width && $image_res[1] <= $thumb1_height) {
								$noThumb = TRUE;
								$image_info['thumb1_name'] = $image_info['image_name'];
								$image_info['thumb1'] = TRUE;
							} else {
								if (!file_exists($thumb1_folder)) {
									mkdir($thumb1_folder, 0755, TRUE);
								}
								$image_name_t1 = filename_exists($thumb1_folder, $image_name.$thumb1_suffix.$image_ext);
								$image_info['thumb1_name'] = $image_name_t1;
								$image_info['thumb1'] = TRUE;
								if ($thumb1_ratio == 0) {
									createthumbnail($filetype, $target_folder.$image_name_full, $thumb1_folder.$image_name_t1, $thumb1_width, $thumb1_height);
								} else {
									createsquarethumbnail($filetype, $target_folder.$image_name_full, $thumb1_folder.$image_name_t1, $thumb1_width);
								}
							}
						}
						if ($thumb2) {
							if ($image_res[0] < $thumb2_width && $image_res[1] < $thumb2_height) {
								$noThumb = TRUE;
								$image_info['thumb2_name'] = $image_info['image_name'];
								$image_info['thumb2'] = TRUE;
							} else {
								if (!file_exists($thumb2_folder)) {
									mkdir($thumb2_folder, 0755, TRUE);
								}
								$image_name_t2 = filename_exists($thumb2_folder, $image_name.$thumb2_suffix.$image_ext);
								$image_info['thumb2_name'] = $image_name_t2;
								$image_info['thumb2'] = TRUE;
								if ($thumb2_ratio == 0) {
									createthumbnail($filetype, $target_folder.$image_name_full, $thumb2_folder.$image_name_t2, $thumb2_width, $thumb2_height);
								} else {
									createsquarethumbnail($filetype, $target_folder.$image_name_full, $thumb2_folder.$image_name_t2, $thumb2_width);
								}
							}
						}
						if ($delete_original && !$noThumb) {
							unlink($target_folder.$image_name_full);
							$image_info['image'] = FALSE;
						}
					}
				}
			} else {
				// The image is invalid
				$image_info = array("error" => 2);
			}
		} else {
			// Image not uploaded
			$image_info = array("error" => 5);
		}
		return $image_info;
	}
}

// Download file from server
if (!function_exists('download_file')) {
	function download_file($file) {
		require_once INCLUDES."class.httpdownload.php";
		ob_end_clean();
		$object = new httpdownload;
		$object->set_byfile($file);
		$object->use_resume = TRUE;
		$object->download();
		exit;
	}
}

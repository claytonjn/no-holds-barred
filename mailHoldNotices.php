<?php

	//Include Database Connect File
	include_once "./includes/db_connect.inc";

	//Must include these PHPMailer files for the mailer to work correctly.
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	require_once __DIR__ . '/../includes/PHPMailer/src/Exception.php';
	require_once __DIR__ . '/../includes/PHPMailer/src/PHPMailer.php';
	require_once __DIR__ . '/../includes/PHPMailer/src/SMTP.php';
	
	//Include function for use of G Suite PHPMailer
	include_once __DIR__ . '/../includes/functions.php';

	//**This include file includes functions, such as check digit generator
	include_once ("./includes/functions.php");
	include_once ("./includes/constants.php");

	//Connect to the web_common database to pull library hours
	$hoursLink = web_hours_i() or die ("Cannot connect to server");
	$hoursQuery = "	SELECT 		mon1, tue2, wed3, thu4, fri5, sat6, sun7
					FROM		library_hours
					ORDER BY	startDate DESC
					LIMIT		1;";
	$hoursResult = mysqli_query($hoursLink, $hoursQuery) or die(mysqli_error($hoursLink));
	$hours = mysqli_fetch_row($hoursResult);
	mysqli_close($hoursLink);
    unset($hoursLink);
	$dowMap = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');

	$holdNoticeLink = db_auto_renew() or die ("Cannot connect to server"); //Connect to the auto_renewal database

	$mattypeQuery = "	SELECT		*
						FROM		mattypes;";
	$mattypeResult = mysqli_query($holdNoticeLink, $mattypeQuery) or die(mysqli_error($holdNoticeLink));
	$mattypes = mysqli_fetch_assoc($mattypeResult);

	$pickuplocationsQuery = "	SELECT		*
								FROM		pickuplocations;";
	$pickuplocationsResult = mysqli_query($holdNoticeLink, $pickuplocationsQuery) or die(mysqli_error($holdNoticeLink));
	$pickuplocations = mysqli_fetch_assoc($pickuplocationsResult);

	/*
		PULL AND SEND INITIAL HOLD NOTICE
	*/
	
	$emailQuery = "	SELECT		patron_num, email, first_name, middle_name, last_name
					FROM		holdnotice
					WHERE       (email_datetime = ''
								OR email_datetime = '0000-00-00 00:00:00'
								OR email_datetime IS NULL)
					AND			trigger_date >= DATE(NOW()) - INTERVAL '7' DAY
					GROUP BY	patron_num, email, first_name, middle_name, last_name;";

	$emailResult = mysqli_query($holdNoticeLink, $emailQuery) or die(mysqli_error($holdNoticeLink));

	//Set Time Limit for Execution Time
	set_time_limit(600+(mysqli_num_rows($emailResult) * 6)); //Allow time for processing all items

	// Instantiation and passing `true` enables exceptions
	$phpMailer = new PHPMailer(true);
	$phpMailer->SMTPKeepAlive = true;
	
	//Set email constants before traversing the loop of patron emails
	$mailSubject = "West Bloomfield Library (Ready for Pickup)";

    while($patron = mysqli_fetch_assoc($emailResult)) {

		$mailMessage = "<!DOCTYPE html>
						<html>
							<head>
								<meta name='viewport' content='width=device-width, initial-scale=1.0'>
								<style>
										.announcement {
											color:				blue;
											font-size:			18px;
										}
										#holds, .hold.bib {
											width:				100%;
										}
										.cover {
											width: 				80px;
										}
										.buttons form {
											display:			inline-block;
										}
										.button {
											border:				none;
											padding:			12px 24px;
											text-align:			center;
											text-decoration:	none;
											display:			inline-block;
											font-size:			16px;
											margin:				4px 2px;
											cursor:				pointer;
										}
										.button.cancel {
											background-color: 	red;
											color:				white;
										}
										#hours {
											margin-left:		auto;
											margin-right:		auto;
										}
										#hours td {
											padding-right:		10px;
										}
									</style>
							</head>
						";

		$mailMessage .= "{$patron['first_name']} {$patron['middle_name']} {$patron['last_name']}<br><br><b span class='announcement'>READY FOR PICKUP!</b> The item(s) below are being held in your name at the pickup location indicated, until the date listed.\r\n<br>\r\n<br><table id='holds'>";

		//Pull emails in an array so we can send the same email to multiple addresses if desired
		$mailTo = explode(",", $patron['email']);

		//Get Titles for sending out Emails
		$emailSubQuery = "	SELECT		hold_id, title, best_author, trigger_date + INTERVAL '7' DAY AS exp_date, pickup_location_code, bcode2, bib_num, ident
							FROM		holdnotice
							WHERE       patron_num = '{$patron['patron_num']}'
                            AND         (email_datetime = ''
										OR email_datetime = '0000-00-00 00:00:00'
                                        OR email_datetime IS NULL)
							AND			trigger_date >= DATE(NOW()) - INTERVAL '7' DAY
							ORDER BY	pickup_location_code ASC, trigger_date ASC, title ASC;";

		$emailSubResult = mysqli_query($holdNoticeLink, $emailSubQuery) or die(mysqli_error($holdNoticeLink));

		while($iteminfo = mysqli_fetch_assoc($emailSubResult)) {

			$expdate =date("M d, Y", strtotime($iteminfo['exp_date']));
			$bnumber = substr($iteminfo['bib_num'], 0, -1);
			$pnumber = substr($patron['patron_num'], 1, -1);
			$pickupLocation = $pickuplocations[$iteminfo['pickup_location_code']];
			if($bnumber == 'b') {
				$link=$iteminfo['title'];
				$coverimg = "<img src='http://www.wblib.org/graphics/mel.png'>";
				$mattype = "MeL Item";
			}
			else {
				$link="<a href='https://encore.wblib.org/iii/encore/record/C__R{$bnumber}'>{$iteminfo['title']}</a>";
				$coverimg = "<a href='http://contentcafe2.btol.com/ContentCafe/Jacket.aspx?UserID={$contentCafeUserID}&Password={$contentCafePassword}&Return=T&Type=L&Value={$iteminfo['ident']}&erroroverride=T'><img src='http://contentcafe2.btol.com/ContentCafe/Jacket.aspx?UserID={$contentCafeUserID}&Password={$contentCafePassword}&Return=T&Type=S&Value={$iteminfo['ident']}&erroroverride=T' /></a>";
				$mattype = $mattypes[$iteminfo['bcode2']];
			}

			$mailMessage .= "	<tr>
									<td>
										<table class='hold bib'>
											<tr>
												<td class='cover' rowspan=5>{$coverimg}</td>
												<td class='title'>{$link}</td>
											</tr>
											<tr>
												<td class='edition_author'>{$iteminfo['best_author']}</td>
											</tr>
											<tr>
												<td class='bcode2'>{$mattype}</td>
											</tr>
											<tr>
												<td class='pickuplocation'><b>Pickup At: </b>{$pickupLocation}</td>
											</tr>
											<tr>
												<td class='expdate'><b>Pickup By: </b>{$expdate}</td>
											</tr>";
			if($bnumber != 'b') {
				$encodedCoverImg = htmlspecialchars($coverimg, ENT_QUOTES);
				$encodedLink = htmlspecialchars($link, ENT_QUOTES);
				$mailMessage .= "			<tr>
												<td class='buttons' colspan=2>
													<form action='./cancelHold.php' method='post' target='_blank'>
														<input type='hidden' name='patronId' value='{$pnumber}'>
														<input type='hidden' name='holdId' value='{$iteminfo['hold_id']}'>
														<input type='hidden' name='coverImg' value='{$encodedCoverImg}'>
														<input type='hidden' name='link' value='{$encodedLink}'>
														<input type='hidden' name='bestAuthor' value='{$bestAuthor}'>
														<input type='hidden' name='matType' value='{$mattype}'>
														<input type='hidden' name='pickupLocation' value='{$pickupLocation}'>
														<input type='hidden' name='expDate' value='{$expdate}'>
														<input class='button cancel' type='submit' value='Cancel' />
													</form>
												</td>
											</tr>";
			}
			$mailMessage .= "			</table>
										<hr>
									</td>
								</tr>";
		}

		$mailMessage .= "</table>";
		$mailMessage .= "\r\n<br><table id='hours' align='center'>
								<tr>
									<th colspan='2'>Library Hours</th>
								</tr>";
		$currentHour = $hours[0];
		$mailMessage .= "<tr><td>{$dowMap[0]}";
		for($i = 1; $i < count($hours); $i++) {
			if($hours[$i] != $currentHour) {
				$times = explode("-", $hours[$i-1]);
				$timeOpen = date("g a", strtotime($times[0]));
				$timeClose = date("g a", strtotime($times[1]));
				$mailMessage .= " - {$dowMap[$i-1]}</td><td>{$timeOpen} - {$timeClose}</td></tr>";
				$mailMessage .= "<tr><td>{$dowMap[$i]}";
				$currentHour = $hours[$i];
			}
		}
		$mailMessage .= " (Summer)</td><td>12 pm - 5 pm</td></tr>";
		$mailMessage .= "		<tr>
									<td>Sun (School Year)</td>
									<td>12 pm - 8 pm</td>
								</tr>
								<tr>
									<td>Sun (Westacres)</td>
									<td>12 pm - 5 pm</td>
								</tr>
							</table>";

		$mailMessage .= "For your convenience, manage your holds online at http://www.westbloomfieldlibrary.org/\r\n<br>If you have any questions, please reply to this email or call one of the phone numbers listed below.\r\n\r\n<br><br>";
		$mailMessage .= "</body></html>";

		//echo $mailMessage . "<div style='page-break-after:always;'> </div>"; continue;

		$mailResult = gsuiteMailer($phpMailer, $mailSubject, $mailMessage, $mailTo, ['email@domain.org', 'WBLIB Notices']);
		if ($mailResult == "Message has been sent.") {
			//Set email_datetime
			$emailedQuery = "	UPDATE      holdnotice
								SET         email_datetime = NOW()
								WHERE       patron_num = '{$patron['patron_num']}'
								AND         (email_datetime = ''
											OR email_datetime = '0000-00-00 00:00:00'
											OR email_datetime IS NULL);";

			mysqli_query($holdNoticeLink, $emailedQuery) or die(mysqli_error($holdNoticeLink));
		}
	}

	$phpMailer->SmtpClose();

	/*
		PULL AND SEND COURTESY HOLD NOTICE
	*/

	//Pull all patrons with holds triggered between 5 and 7 days ago
	//who also haven't already received a courtesy hold notice
	$courtesyQuery = "	SELECT		hold_id
						FROM		holdnotice
						WHERE       (courtesy_datetime = ''
									OR courtesy_datetime = '0000-00-00 00:00:00'
									OR courtesy_datetime IS NULL)
						AND			(	DATE(trigger_date) + INTERVAL '5' DAY < DATE(NOW())
									AND	trigger_date >= DATE(NOW()) - INTERVAL '7' DAY )
						GROUP BY	hold_id;";

	$courtesyResult = mysqli_query($holdNoticeLink, $courtesyQuery) or die(mysqli_error($holdNoticeLink));
	
	//Build string to pass hold IDs into SierraDNA
	//to make sure that the item is still on the holdshelf
	$holdIds = "";
	while($holdId = mysqli_fetch_assoc($courtesyResult)) {
		$holdIds .= "('{$holdId['hold_id']}'),";
	}
	$holdIds = rtrim($holdIds, ',');

	//Connect to SierraDNA
	$sierraDNAconn = db_sierradna();

	//Dump hold IDs into Postgres
	$sierraCourtesyQuery = "	DROP TABLE IF EXISTS courtesy_holds;";
	$sierraCourtesyQuery .= "	CREATE TEMP TABLE courtesy_holds
								(
									hold_id bigint
								);";
	$sierraCourtesyQuery .= "	INSERT INTO courtesy_holds
								VALUES {$holdIds};";
	
	//Limit to holds that still exist and pull necessary data
	$sierraCourtesyQuery .= "	DROP TABLE IF EXISTS holds;";
	$sierraCourtesyQuery .= "	CREATE TEMP TABLE holds
								(
									bib_record_id bigint,
									patron_record_id bigint,
									hold_id bigint,
									trigger_date date,
									patron_num int,
									pickup_location_code varchar(5),
									bib_num int,
									bcode2 varchar(3),
									first_name varchar(500),
									middle_name varchar(500),
									last_name varchar(500),
									best_author varchar(1000),
									best_title varchar(1000)
								);";
	$sierraCourtesyQuery .= "	INSERT INTO	holds
								SELECT		brirl.bib_record_id, h.patron_record_id, h.id AS hold_id, rm.record_last_updated_gmt::DATE AS trigger_date, pv.record_num AS patron_num, h.pickup_location_code, bv.record_num AS bib_num, bv.bcode2, prf.first_name, prf.middle_name, prf.last_name, brp.best_author, brp.best_title
								FROM		sierra_view.hold AS h
								INNER JOIN	courtesy_holds AS ch
											ON h.id = ch.hold_id
								LEFT JOIN	sierra_view.record_metadata AS rm
											ON h.record_id = rm.id
								LEFT JOIN	sierra_view.patron_view AS pv
											ON h.patron_record_id = pv.id
								LEFT JOIN	sierra_view.item_record AS ir
											ON h.record_id = ir.id
								LEFT JOIN	sierra_view.bib_record_item_record_link AS brirl
											ON h.record_id = brirl.item_record_id
								LEFT JOIN	sierra_view.bib_view AS bv
											ON brirl.bib_record_id = bv.id
								LEFT JOIN	sierra_view.patron_record_fullname AS prf
											ON h.patron_record_id = prf.patron_record_id
								LEFT JOIN	sierra_view.bib_record_property AS brp
											ON brirl.bib_record_id = brp.bib_record_id;";
	$sierraCourtesyQuery .= "	DROP TABLE IF EXISTS varfields;";
	$sierraCourtesyQuery .= "	CREATE TEMP TABLE varfields
								(
									record_id bigint,
									varfield_type_code char(1),
									marc_tag varchar(3),
									occ_num int,
									field_content varchar(20001)
								);";
	$sierraCourtesyQuery .= "	INSERT INTO varfields
								SELECT		v.record_id, v.varfield_type_code, v.marc_tag, v.occ_num, v.field_content
								FROM		sierra_view.varfield v
								INNER JOIN	(SELECT	DISTINCT(bib_record_id)
											FROM	holds) AS h
											ON h.bib_record_id = v.record_id;";
	$sierraCourtesyQuery .= "	INSERT INTO varfields
								SELECT		v.record_id, v.varfield_type_code, v.marc_tag, v.occ_num, v.field_content
								FROM		sierra_view.varfield v
								INNER JOIN	(SELECT DISTINCT(patron_record_id)
											FROM	holds) AS h
											ON h.patron_record_id = v.record_id;";
	$sierraCourtesyQuery .= "	SELECT		h.hold_id, h.trigger_date + INTERVAL '7' DAY AS exp_date, h.patron_num, h.pickup_location_code, h.bib_num, h.bcode2, ve.email, h.first_name, h.middle_name, h.last_name, h.best_author,
											CASE	WHEN	vt.title != ''
													THEN		vt.title
													ELSE		CONCAT('|a', h.best_title)
											END AS title,
											CASE WHEN		h.bcode2 = 'b'	/*	Book on CD (5)		*/
														OR	h.bcode2 = 'a'	/*	Book (7)			*/
														OR	h.bcode2 = 'l'	/*	Large Print (10)	*/
														OR	h.bcode2 = 'k'	/*	eAudio (13)			*/
														OR	h.bcode2 = 'i'	/*	Book on Tape (15)	*/
														OR	h.bcode2 = 'p'	/*	Book on MP3 (23)	*/
														OR	h.bcode2 = 'z'	/*	eBook (24)			*/
														OR	h.bcode2 = 'm'	/*	Discovery Tablet	*/
														OR	h.bcode2 = 'o'	/*	ReadAlong			*/
														OR	h.bcode2 = '$'	/*	Rental (25)			*/
												THEN
													(SELECT 	DISTINCT ON (v.record_id) v.field_content
													FROM 		varfields v
													WHERE 		v.record_id = h.bib_record_id
													AND			v.marc_tag = '020'
													ORDER BY 	v.record_id, v.occ_num ASC)
												ELSE
													(SELECT 	DISTINCT ON (v.record_id) v.field_content
													FROM 		varfields v
													WHERE 		v.record_id = h.bib_record_id
													AND			v.marc_tag = '024'
													ORDER BY 	v.record_id, v.occ_num ASC)
											END AS ident
								FROM		holds AS h
								INNER JOIN	(SELECT DISTINCT ON (record_id) record_id, field_content AS email
											FROM varfields
											WHERE varfield_type_code = 'z') AS ve
											ON h.patron_record_id = ve.record_id
								LEFT JOIN	(SELECT 	DISTINCT ON (record_id) record_id, field_content AS title
											FROM 		varfields
											WHERE 		marc_tag = '245'
											ORDER BY 	record_id, occ_num ASC) vt
											ON h.bib_record_id = vt.record_id
								ORDER BY	h.patron_num;";

	$sierraCourtesyResult = pg_query($sierraDNAconn, $sierraCourtesyQuery) or die('Query failed: ' . pg_last_error());

	pg_close($sierraDNAconn);

	$sierraCourtesyHolds = pg_fetch_all($sierraCourtesyResult);

	$courtesyCount = sizeof($sierraCourtesyHolds);

	//Set Time Limit for Execution Time
	set_time_limit(600+($courtesyCount * 6)); //Allow time for processing all items

	// Instantiation and passing `true` enables exceptions
	$phpMailer = new PHPMailer(true);
	$phpMailer->SMTPKeepAlive = true;
	
	//Set email constants before traversing the loop of patron emails
	$courtesySubject = "West Bloomfield Library (Pickup Reminder)";

	//Loop through results this way so we can compare adjacent rows
	for($i=0; $i<sizeof($sierraCourtesyHolds); $i) {
		$courtesyMessage = "<!DOCTYPE html>
							<html>
								<head>
									<meta name='viewport' content='width=device-width, initial-scale=1.0'>
									<style>
										.announcement {
											color:				orange;
											font-size:			18px;
										}
										#holds, .hold.bib {
											width:				100%;
										}
										.cover {
											width: 				80px;
										}
										.buttons form {
											display:			inline-block;
										}
										.button {
											border:				none;
											padding:			12px 24px;
											text-align:			center;
											text-decoration:	none;
											display:			inline-block;
											font-size:			16px;
											margin:				4px 2px;
											cursor:				pointer;
										}
										.button.extend {
											background-color: 	green;
											color:				white;
										}
										.button.cancel {
											background-color: 	red;
											color:				white;
										}
										#hours {
											margin-left:		auto;
											margin-right:		auto;
										}
										#hours td {
											padding-right:		10px;
										}
									</style>
								</head>
							";

		$courtesyMessage .= "{$sierraCourtesyHolds[$i]['first_name']} {$sierraCourtesyHolds[$i]['middle_name']} {$sierraCourtesyHolds[$i]['last_name']}<br><br><b span class='announcement'>READY FOR PICKUP!</b> Just a reminder, you have holds ready to be picked up. These items will only be available until the date listed. If you do not pick up the items, they will be placed back on the shelf or given to the next patron on the hold list.\r\n<br>\r\n<br><table id='holds'>";

		//Pull emails in an array so we can send the same email to multiple addresses if desired
		$mailTo = explode(",", $sierraCourtesyHolds[$i]['email']);

		//Combine multiple holds for a patron into one email
		$j = $i;
		while($j < $courtesyCount && $sierraCourtesyHolds[$i]['patron_num'] == $sierraCourtesyHolds[$j]['patron_num']) {
			$patronNum = "p" . $sierraCourtesyHolds[$j]['patron_num'] . getCheckDigit($sierraCourtesyHolds[$j]['patron_num']);
			$bnumber = "b" . $sierraCourtesyHolds[$j]['bib_num'];
			$bestAuthor = cleanFromSierra("best_author", $sierraCourtesyHolds[$j]['best_author']);
			$title = cleanFromSierra("title", $sierraCourtesyHolds[$j]['title']);
			$ident = cleanFromSierra("ident", $sierraCourtesyHolds[$j]['ident']);
			$expdate=date("M d, Y", strtotime($sierraCourtesyHolds[$j]['exp_date']));
			$pickupLocation = $pickuplocations[$sierraCourtesyHolds[$j]['pickup_location_code']];
			if($bnumber == 'b') {
				$link=$title;
				$coverimg = "<img src='http://www.wblib.org/graphics/mel.png'>";
				$mattype = "MeL Item";
			}
			else {
				$link="<a href='https://encore.wblib.org/iii/encore/record/C__R{$bnumber}'>{$title}</a>";
				$coverimg = "<a href='http://contentcafe2.btol.com/ContentCafe/Jacket.aspx?UserID={$contentCafeUserID}&Password={$contentCafePassword}&Return=T&Type=L&Value={$ident}&erroroverride=T'><img src='http://contentcafe2.btol.com/ContentCafe/Jacket.aspx?UserID={$contentCafeUserID}&Password={$contentCafePassword}&Return=T&Type=S&Value={$ident}&erroroverride=T' /></a>";
				$mattype = $mattypes[$sierraCourtesyHolds[$j]['bcode2']];
			}

			$courtesyMessage .= "	<tr>
										<td>
											<table class='hold bib'>
												<tr>
													<td class='cover' rowspan=5>{$coverimg}</td>
													<td class='title'>{$link}</td>
												</tr>
												<tr>
													<td class='edition_author'>{$bestAuthor}</td>
												</tr>
												<tr>
													<td class='bcode2'>{$mattype}</td>
												</tr>
												<tr>
													<td class='pickuplocation'><b>Pickup At: </b>{$pickupLocation}</td>
												</tr>
												<tr>
													<td class='expdate'><b>Pickup By: <u>{$expdate}</u></b></td>
												</tr>";
			if($bnumber != 'b') {
				$encodedCoverImg = htmlspecialchars($coverimg, ENT_QUOTES);
				$encodedLink = htmlspecialchars($link, ENT_QUOTES);
				$courtesyMessage .= "			<tr>
													<td class='buttons' colspan=2>
														<form action='./cancelHold.php' method='post' target='_blank'>
															<input type='hidden' name='patronId' value='{$sierraCourtesyHolds[$j]['patron_num']}'>
															<input type='hidden' name='holdId' value='{$sierraCourtesyHolds[$j]['hold_id']}'>
															<input type='hidden' name='coverImg' value='{$encodedCoverImg}'>
															<input type='hidden' name='link' value='{$encodedLink}'>
															<input type='hidden' name='bestAuthor' value='{$bestAuthor}'>
															<input type='hidden' name='matType' value='{$mattype}'>
															<input type='hidden' name='pickupLocation' value='{$pickupLocation}'>
															<input type='hidden' name='expDate' value='{$expdate}'>
															<input class='button cancel' type='submit' value='Cancel' />
														</form>
														<form action='./extendHold.php' method='get' target='_blank'>
															<input type='hidden' name='patronId' value='{$sierraCourtesyHolds[$j]['patron_num']}'>
															<input type='hidden' name='holdId' value='{$sierraCourtesyHolds[$j]['hold_id']}'>
															<input class='button extend' type='submit' value='Extend' />
														</form>
													</td>
												</tr>";
			}
			$courtesyMessage .= "			</table>
											<hr>
										</td>
									</tr>";
			$j++;
		}

		$i = $j;

		$courtesyMessage .= "</table>";
		$courtesyMessage .= "\r\n<br><table id='hours' align='center'>
								<tr>
									<th colspan='2'>Library Hours</th>
								</tr>";
		$currentHour = $hours[0];
		$courtesyMessage .= "<tr><td>{$dowMap[0]}";
		for($k = 1; $k < count($hours); $k++) {
			if($hours[$k] != $currentHour) {
				$times = explode("-", $hours[$k-1]);
				$timeOpen = date("g a", strtotime($times[0]));
				$timeClose = date("g a", strtotime($times[1]));
				$courtesyMessage .= " - {$dowMap[$k-1]}</td><td>{$timeOpen} - {$timeClose}</td></tr>";
				$courtesyMessage .= "<tr><td>{$dowMap[$k]}";
				$currentHour = $hours[$k];
			}
		}
		$courtesyMessage .= " (Summer)</td><td>12 pm - 5 pm</td></tr>";
		$courtesyMessage .= "		<tr>
									<td>Sun (School Year)</td>
									<td>12 pm - 8 pm</td>
								</tr>
								<tr>
									<td>Sun (Westacres)</td>
									<td>12 pm - 5 pm</td>
								</tr>
							</table>";

		$courtesyMessage .= "\r\n<br>For your convenience, manage your holds online at http://www.westbloomfieldlibrary.org/\r\n<br>If you have any questions, please reply to this email or call one of the phone numbers listed below.\r\n\r\n<br><br>";
		$courtesyMessage .= "</body></html>";

		//echo $courtesyMessage . "<div style='page-break-after:always;'> </div>"; continue;

		$courtesyResult = gsuiteMailer($phpMailer, $courtesySubject, $courtesyMessage, $mailTo, ['email@domain.org', 'WBLIB Notices']);
		if ($courtesyResult == "Message has been sent.") {
			//Set email_datetime
			$emailedQuery = "	UPDATE      holdnotice
								SET         courtesy_datetime = NOW()
								WHERE       patron_num = '{$patronNum}'
								AND         (courtesy_datetime = ''
											OR courtesy_datetime = '0000-00-00 00:00:00'
											OR courtesy_datetime IS NULL);";

			mysqli_query($holdNoticeLink, $emailedQuery) or die(mysqli_error($holdNoticeLink));
		}
	}

	$phpMailer->SmtpClose();

	mysqli_close($holdNoticeLink);
	unset($holdNoticeLink);

?>
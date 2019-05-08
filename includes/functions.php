<?php

	function cleanFromSierra($field, $string) {
		switch ($field) {
			case "author":
				$pattern = "/([^\d,\.]*,?[^\d,\.]*)(?:,|\.)?.*/";
				$replacement = "$1";
				break;
			case "title":
				$pattern = "/\|a(((?!\/\||\||\/$).)*)\/?|\|b(((?!\/\||\||\/$).)*)\/?|\|c(((?!\/\||\||\/$).)*)\/?|\|f(((?!\/\||\||\/$).)*)\/?|\|g(((?!\/\||\||\/$).)*)\/?|\|h(((?!\/\||\||\/$).)*)\/?|\|k(((?!\/\||\||\/$).)*)\/?|\|n(((?!\/\||\||\/$).)*)\/?|\|p(((?!\/\||\||\/$).)*)\/?|\|s(((?!\/\||\||\/$).)*)\/?/";
				$replacement = "$1 $3 $11 $15 $17 $19";
				break;
			case "ident":
				$pattern = "/\|a(\w*).*|\|c(\w*).*|\|d(\w*).*|\|z(\w*).*/";
				$replacement = "$1";
				break;
			case "edition":
				$pattern = "/\|a(((?!\|).)*)|\|b(((?!\|).)*)/";
				$replacement = "$1 $3";
				break;
		}

		$string = preg_replace($pattern, $replacement, $string);
		$string = preg_replace('/\s+/S', " ", $string); //collapse multiple spaces
		$string = trim($string);
		return $string;
	}
	
	function holdCount($sierraDNAconn, $holdId) {
		//Check to see if there are any Bib Level holds for that item
		$holdQuery = "	DROP TABLE IF EXISTS item_held;";
		$holdQuery .= "	CREATE TEMP TABLE item_held
						(
							id bigint,
							record_id bigint,
							bib_record_id bigint
						);";
		$holdQuery .= "	INSERT INTO	item_held
						SELECT		h.id, h.record_id, brirl.bib_record_id
						FROM		sierra_view.hold AS h
						LEFT JOIN	sierra_view.bib_record_item_record_link AS brirl
									ON brirl.item_record_id = h.record_id
						WHERE		h.id='{$holdId}';";
		$holdQuery .= "	SELECT		count(h.record_id), bv.record_num
						FROM		item_held as ih
						LEFT JOIN	sierra_view.hold as h
									ON ih.bib_record_id = h.record_id
						LEFT JOIN	sierra_view.bib_view as bv
									ON bv.id = ih.bib_record_id
						GROUP BY	bv.record_num";

		$sierraHoldResult = pg_query($sierraDNAconn, $holdQuery) or die('Query failed: ' . pg_last_error());

		return pg_fetch_assoc($sierraHoldResult);
	}

	function getAccessToken() {
		include "constants.php";

		// Get cURL resource
		$curl = curl_init();
		curl_setopt_array($curl, array(
				CURLOPT_POST => TRUE,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSL_VERIFYPEER => 0,
				CURLOPT_URL => "{$apiurl}token",
				CURLOPT_HTTPHEADER => array(
						'Host: '.$hosturl,
						'Authorization: Basic '.$encauth,
						'Content-Type: application/x-www-form-urlencoded'
				),
				CURLOPT_POSTFIELDS => "grant_type=client_credentials"
		));

				// Send the request & save response to $resp
		$resp = curl_exec($curl);

		//Check if CURL REQUEST FAILED TO PROCESS
		$err = NULL;
		if($resp === FALSE)
			$err = curl_error($curl);

		// Close request to clear up some resources
		curl_close($curl);

		if($err)
			return $err;
		else {
			$tokenData = json_decode($resp, true);
			if(is_null($tokenData)) {
				echo "Could not retrieve token from server.<br>";
				return false;
			}

			return $tokenData["access_token"];	//Sends back Token
		}

	}
	
	function getHold($token, $holdId){
   
		include "constants.php";

		// Get cURL resource
		$curl = curl_init();
		// Set some options - we are passing in a useragent too here
		curl_setopt_array($curl, array(
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYHOST=> 0,
			CURLOPT_SSL_VERIFYPEER=> 0,
			CURLOPT_URL => "{$apiurl}patrons/holds/{$holdId}?fields=patron,pickupLocation,note,record",
			CURLOPT_HTTPHEADER => array(
					'Host: '.$hosturl,
					'Authorization: Bearer '.$token,
					'Content-Type: application/json'
			)
		));
		// Send the request & save response to $resp
		$resp = curl_exec($curl);

		//Check if CURL REQUEST FAILED TO PROCESS
		$err = NULL;
		if($resp === FALSE)
			$err = curl_error($curl);

		// Close request to clear up some resources
		curl_close($curl);

		if($err)
			return $err;
		else
			return $resp;
		
	}

	function deleteHold($token, $holdId) {
		include "constants.php";

		// Get cURL resource
		$curl = curl_init();
		// Set some options - we are passing in a useragent too here
		curl_setopt_array($curl, array(
			CURLOPT_CUSTOMREQUEST => 'DELETE',
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYHOST=> 0,
			CURLOPT_SSL_VERIFYPEER=> 0,
			CURLOPT_URL => "{$apiurl}patrons/holds/{$holdId}",
			CURLOPT_HTTPHEADER => array(
					'Host: '.$hosturl,
					'Authorization: Bearer '.$token
			)
		));
		// Send the request & save response to $resp
		$resp = curl_exec($curl);

		//Check if CURL REQUEST FAILED TO PROCESS
		$err = NULL;
		if($resp === FALSE)
			$err = curl_error($curl);

		// Close request to clear up some resources
		curl_close($curl);

		if($err)
			return $err;
		else
			return $resp;

	}

	function placeHold($token, $id, $body) {
		include "constants.php";

		// Get cURL resource
		$curl = curl_init();
		// Set some options - we are passing in a useragent too here
		curl_setopt_array($curl, array(
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYHOST=> 0,
			CURLOPT_SSL_VERIFYPEER=> 0,
			CURLOPT_URL => "{$apiurl}patrons/{$id}/holds/requests",
			CURLOPT_HTTPHEADER => array(
					'Host: '.$hosturl,
					'Authorization: Bearer '.$token,
					'Content-Type: application/json',
					'Content-Length: ' . strlen($body)
			),
			CURLOPT_POSTFIELDS => $body
		));
		// Send the request & save response to $resp
		$resp = curl_exec($curl);

		//Check if CURL REQUEST FAILED TO PROCESS
		$err = NULL;
		if($resp === FALSE)
			$err = curl_error($curl);

		// Close request to clear up some resources
		curl_close($curl);

		if($err)
			return $err;
		else
			return $resp;

	}


?>